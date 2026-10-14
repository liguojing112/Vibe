<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AdminActionLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Notice;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\RealName;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminAuditService;
use App\Service\Cl\RealName\RealNameService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 后台审核中心（docs/07 第 3.4、3.5 节）。
 *
 * 这里盯的是**审完之后用户那边变成了什么样**：
 * 通过才换资料、驳回一个字都不动、相册是「换掉旧的」而不是「改个字段」、
 * 以及身份证明文只能看一眼（看一次留一条流水）。
 *
 * @internal
 * @coversNothing
 */
final class AdminAuditServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private AdminAuditService $service;

    private AdminUser $operator;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->service = make(AdminAuditService::class);
        $this->operator = AdminUser::create(['username' => 'shenhe', 'password' => '123456', 'nickname' => '审核员']);
    }

    protected function tearDown(): void
    {
        $this->clear();
        AdminUser::query()->where('username', 'shenhe')->delete();
        parent::tearDown();
    }

    // ---------- 资料审核 ----------

    public function testProfileListShowsPendingWithFieldLabel(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $this->makeAudit($female, ProfileAudit::FIELD_NICKNAME, '晚晴', '晚晴呀');

        $result = $this->service->profileList(ProfileAudit::STATUS_PENDING, 1, 20);

        self::assertSame(1, $result['total']);
        self::assertSame('晚晴呀', $result['list'][0]['new_value']);
        self::assertSame('昵称', $result['list'][0]['field_label']);
        self::assertSame('晚晴', $result['list'][0]['user_nickname'], '列表要带上是哪个用户');
    }

    public function testProfileListResolvesPhotoIdsToUrls(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $old = $this->makePhoto($female, 'https://cdn/old.jpg', UserPhoto::AUDIT_APPROVED);
        $new = $this->makePhoto($female, 'https://cdn/new.jpg', UserPhoto::AUDIT_PENDING);
        $this->makeAudit($female, ProfileAudit::FIELD_PHOTO, (string) $old->id, (string) $new->id);

        $row = $this->service->profileList(ProfileAudit::STATUS_PENDING, 1, 20)['list'][0];

        self::assertSame('相册', $row['field_label']);
        self::assertSame(['https://cdn/old.jpg'], array_column($row['old_photos'], 'url'));
        self::assertSame(['https://cdn/new.jpg'], array_column($row['new_photos'], 'url'));
    }

    public function testApproveProfileAppliesChangeOnlyOnApprove(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $audit = $this->makeAudit($female, ProfileAudit::FIELD_NICKNAME, '晚晴', '晚晴呀');

        self::assertSame('晚晴', $female->refresh()->nickname, '审核期间用户看到的还是旧昵称');

        $this->service->approveProfile($this->operator, (int) $audit->id);

        self::assertSame('晚晴呀', $female->refresh()->nickname, '通过之后才换新的');
        self::assertSame(ProfileAudit::STATUS_APPROVED, (int) $audit->refresh()->status);
        self::assertSame($this->operator->id, (int) $audit->auditor_id);

        $notice = Notice::query()->where('user_id', $female->id)->firstOrFail();
        self::assertStringContainsString('昵称', $notice->content . $notice->title);
    }

    public function testRejectProfileKeepsUserUntouchedAndCarriesReason(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $audit = $this->makeAudit($female, ProfileAudit::FIELD_NICKNAME, '晚晴', '违规昵称');

        $this->service->rejectProfile($this->operator, (int) $audit->id, '昵称含违规词');

        self::assertSame('晚晴', $female->refresh()->nickname, '驳回不动 cl_user');
        self::assertSame(ProfileAudit::STATUS_REJECTED, (int) $audit->refresh()->status);
        self::assertSame('昵称含违规词', $audit->reject_reason);

        $notice = Notice::query()->where('user_id', $female->id)->firstOrFail();
        self::assertStringContainsString('昵称含违规词', (string) $notice->content, '驳回原因要让用户看到');
    }

    public function testApprovePhotoAuditSwapsAlbums(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $old = $this->makePhoto($female, 'https://cdn/old.jpg', UserPhoto::AUDIT_APPROVED);
        $new = $this->makePhoto($female, 'https://cdn/new.jpg', UserPhoto::AUDIT_PENDING);
        $audit = $this->makeAudit($female, ProfileAudit::FIELD_PHOTO, (string) $old->id, (string) $new->id);

        $this->service->approveProfile($this->operator, (int) $audit->id);

        self::assertSame(UserPhoto::AUDIT_APPROVED, (int) $new->refresh()->audit_status, '新相册置为已通过');
        self::assertNull(UserPhoto::query()->whereKey($old->id)->first(), '旧相册被换掉了');
    }

    public function testRejectPhotoAuditDropsOnlyThePendingOnes(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $old = $this->makePhoto($female, 'https://cdn/old.jpg', UserPhoto::AUDIT_APPROVED);
        $new = $this->makePhoto($female, 'https://cdn/new.jpg', UserPhoto::AUDIT_PENDING);
        $audit = $this->makeAudit($female, ProfileAudit::FIELD_PHOTO, (string) $old->id, (string) $new->id);

        $this->service->rejectProfile($this->operator, (int) $audit->id, '照片不清晰');

        self::assertNull(UserPhoto::query()->whereKey($new->id)->first(), '待审的那批丢掉');
        self::assertNotNull(UserPhoto::query()->whereKey($old->id)->first(), '旧相册保持原样');
    }

    public function testAuditingTwiceIsRejected(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $audit = $this->makeAudit($female, ProfileAudit::FIELD_NICKNAME, '晚晴', '晚晴呀');

        $this->service->approveProfile($this->operator, (int) $audit->id);

        $this->expectException(ClBusinessException::class);
        $this->service->approveProfile($this->operator, (int) $audit->id);
    }

    /** 批量里有一条已经审过了，不该把其它几条一起带失败（审核员勾了 20 条别白做）。 */
    public function testBatchProfileSkipsAlreadyAuditedOnes(): void
    {
        $first = $this->makeAudit($this->makeUser(User::GENDER_FEMALE, '晚晴'), ProfileAudit::FIELD_NICKNAME, '晚晴', '晚晴呀');
        $second = $this->makeAudit($this->makeUser(User::GENDER_FEMALE, '星野'), ProfileAudit::FIELD_NICKNAME, '星野', '星野呀');
        $this->service->approveProfile($this->operator, (int) $first->id);

        $result = $this->service->batchProfile(
            $this->operator,
            [(int) $first->id, (int) $second->id],
            true,
            '',
        );

        self::assertSame(1, $result['success']);
        self::assertSame([(int) $first->id], $result['failed']);
        self::assertSame(ProfileAudit::STATUS_APPROVED, (int) $second->refresh()->status, '另一条照常通过');
    }

    public function testBatchRejectRequiresReason(): void
    {
        $audit = $this->makeAudit($this->makeUser(User::GENDER_FEMALE, '晚晴'), ProfileAudit::FIELD_NICKNAME, '晚晴', '晚晴呀');

        $result = $this->service->batchProfile($this->operator, [(int) $audit->id], false, '');

        self::assertSame(0, $result['success']);
        self::assertSame(ProfileAudit::STATUS_PENDING, (int) $audit->refresh()->status);
    }

    // ---------- 实名审核 ----------

    public function testRealNameListMasksIdCardAndCountsDuplicates(): void
    {
        $first = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $second = $this->makeUser(User::GENDER_FEMALE, '星野');
        $realName = make(RealNameService::class);

        // 两个号用了同一张身份证 —— 只提示不拦截（docs/07 第 3.5 节）
        $realName->submit($first, '晚晴', '110101199003071233');
        $realName->submit($second, '星野', '110101199003071233');

        $result = $this->service->realNameList(RealName::STATUS_AUDITING, 1, 20);

        self::assertSame(2, $result['total']);
        $row = $result['list'][0];
        self::assertStringContainsString('*', (string) $row['id_card_mask'], '列表里只给打码号');
        self::assertArrayNotHasKey('id_card_hash', $row, 'hash 不外传');
        self::assertSame(1, $row['duplicate_count'], '另一个号也用了这张身份证');
    }

    public function testApproveRealNameFlipsUserStatus(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $row = $this->submitRealName($female);

        $this->service->approveRealName($this->operator, (int) $row->id);

        self::assertSame(User::REAL_NAME_PASSED, (int) $female->refresh()->real_name_status);
        self::assertSame(RealName::STATUS_PASSED, (int) $row->refresh()->status);
    }

    public function testRejectRealNameCarriesReason(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $row = $this->submitRealName($female);

        $this->service->rejectRealName($this->operator, (int) $row->id, '证件照片模糊');

        self::assertSame(User::REAL_NAME_REJECTED, (int) $female->refresh()->real_name_status);
        self::assertSame('证件照片模糊', $row->refresh()->reject_reason);
    }

    public function testPlainIdCardIsAudited(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $row = $this->submitRealName($female);

        $result = $this->service->plainIdCard($this->operator, (int) $row->id);

        self::assertSame('110101199003071233', $result['id_card'], '明文只在这一次返回里');
        self::assertSame('晚晴', $result['real_name']);

        $log = AdminActionLog::query()->where('user_id', $female->id)->firstOrFail();
        self::assertStringContainsString('身份证明文', $log->detail);
        self::assertSame('shenhe', $log->operator_name, '谁看过要能查出来');
    }

    // ---------- 工具 ----------

    private function makeAudit(User $user, string $field, ?string $old, string $new): ProfileAudit
    {
        /** @var ProfileAudit $audit */
        $audit = ProfileAudit::query()->create([
            'user_id' => $user->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'status' => ProfileAudit::STATUS_PENDING,
        ]);

        return $audit;
    }

    private function makePhoto(User $user, string $url, int $status): UserPhoto
    {
        return UserPhoto::query()->create([
            'user_id' => $user->id,
            'url' => $url,
            'sort' => ++$this->sequence,
            'audit_status' => $status,
        ]);
    }

    private function submitRealName(User $user): RealName
    {
        make(RealNameService::class)->submit($user, '晚晴', '110101199003071233');

        return RealName::query()->where('user_id', $user->id)->firstOrFail();
    }

    private function makeUser(int $gender, string $nickname): User
    {
        $phone = '13600000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $nickname,
            'avatar' => 'https://cdn.example.com/a.jpg',
            'status' => User::STATUS_NORMAL,
            'source' => 1,
        ]);
        CoinWallet::query()->create(['user_id' => $user->id]);
        PointWallet::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function clear(): void
    {
        AdminActionLog::query()->delete();
        Notice::query()->delete();
        ProfileAudit::query()->delete();
        RealName::query()->delete();
        UserPhoto::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
