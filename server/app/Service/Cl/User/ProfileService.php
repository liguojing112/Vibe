<?php

declare(strict_types=1);

namespace App\Service\Cl\User;

use App\Model\Cl\ProfileAudit;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Repository\Cl\ProfileAuditRepository;
use App\Repository\Cl\UserPhotoRepository;
use App\Service\Cl\Relation\RelationService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Collection\Arr;
use Hyperf\DbConnection\Db;
use Hyperf\Validation\ValidationException;

/**
 * 资料（docs/01 P14、docs/04 第 1 节、docs/05 第 4.2 节）。
 *
 * 核心规则：**头像、昵称、自我介绍、相册要审核**，提交后进 `cl_profile_audit`，
 * `cl_user` 里保存的仍是「已通过」的旧值 —— 所以本人看到新内容、别人看到旧内容。
 * 其余字段（生日、城市、身高、职业、学历、交友期待、标签）直接生效。
 */
final class ProfileService
{
    /** 相册最多 9 张（docs/01 P14）。 */
    public const MAX_PHOTOS = 9;

    /** 兴趣标签最多 5 个（docs/01 P14）。 */
    public const MAX_TAGS = 5;

    /** 自我介绍最多 200 字（docs/01 P14）。 */
    public const MAX_BIO_LENGTH = 200;

    /** 必须满 18 岁（docs/01 P14）。 */
    public const MIN_AGE = 18;

    /** 审核完直接生效、不经审核的字段。 */
    private const DIRECT_FIELDS = [
        'birthday', 'city', 'height', 'job', 'education', 'dating_purpose', 'tags',
    ];

    public function __construct(
        private readonly UserPhotoRepository $photoRepository,
        private readonly ProfileAuditRepository $auditRepository,
        private readonly CoinWalletService $coinWalletService,
        private readonly PointWalletService $pointWalletService,
        private readonly RelationService $relationService,
    ) {}

    /**
     * `/user/me`：我的完整资料，**含待审内容与审核状态**（自己看得到新内容）。
     *
     * @return array<string,mixed>
     */
    public function me(User $user): array
    {
        $pendingValues = [];
        foreach ($this->auditRepository->listPending($user->id) as $audit) {
            $pendingValues[$audit->field] = $audit->new_value;
        }

        return [
            'id' => $user->id,
            'account' => $user->account,
            'phone' => $user->phone,
            'gender' => $user->gender,
            'level' => $user->level,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'birthday' => $user->birthday?->format('Y-m-d'),
            'age' => $user->age(),
            'city' => $user->city,
            'height' => $user->height,
            'job' => $user->job,
            'education' => $user->education,
            'dating_purpose' => $user->dating_purpose,
            'tags' => $user->tags ?? [],
            'show_in_nearby' => $user->show_in_nearby,
            'show_in_recommend' => $user->show_in_recommend,
            'real_name_status' => $user->real_name_status,
            'vip_expire_at' => $user->vip_expire_at?->format('Y-m-d H:i:s'),
            'is_vip' => $user->isVip(),
            // 自己看到的是「我最近提交的那一版」相册（待审优先）
            'photos' => $this->photoList($user->id),
            // 待审的新内容，键是字段名
            // 注意：**必须转成对象**，PHP 空数组 json_encode 出来是 `[]`，
            // 客户端按 Map 解析会直接失败（当成网络错误）。同理适用于其它 map 字段。
            'pending' => (object) $pendingValues,
            // 正在审核中的字段名，页面据此打「审核中」角标
            'auditing' => array_keys($pendingValues),
            'coin_balance' => $user->isMale() ? $this->coinWalletService->balanceOf($user->id) : null,
            'point_balance' => $user->isFemale() ? $this->pointWalletService->summaryOf($user->id)['balance'] : null,
            // 关系统计（关注 / 好友 / 粉丝 / 访客），P04 我的页四格用
            'stats' => $this->relationService->stats($user),
        ];
    }

    /**
     * `/user/profile`：改资料。
     *
     * 审核字段写 `cl_profile_audit`（不动 `cl_user`）；其余字段直接生效。
     * 性别、等级、状态、实名状态这些**不接受客户端传值**（传了也不生效）。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function update(User $user, array $data): array
    {
        // 白名单：只认下面这些字段。性别、等级、状态、实名状态、账号这些
        // 客户端传了也不生效（docs/01 第 1 节：性别注册后不可改）。
        $data = Arr::only($data, array_merge(ProfileAudit::AUDIT_FIELDS, self::DIRECT_FIELDS));

        foreach (ProfileAudit::AUDIT_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $new = trim((string) $data[$field]);
            $old = (string) ($user->{$field} ?? '');

            if ($new === $old) {
                // 改回了原值：撤销待审，别让用户白等一次审核
                $this->auditRepository->deletePending($user->id, $field);
                continue;
            }
            $this->auditRepository->upsertPending($user->id, $field, $old, $new);
        }

        $direct = Arr::only($data, self::DIRECT_FIELDS);
        if ($direct !== []) {
            $user->update($direct);
        }

        return $this->me($user->refresh());
    }

    /**
     * `/user/photos`：相册整体提交（URL 数组，顺序即展示顺序）。
     *
     * 整批进审核；**已经通过的旧相册先留着**，等这批审核通过再由后台替换（docs/01 P14）。
     *
     * @param array<int,string> $urls
     * @return array<int,array<string,mixed>>
     */
    public function replacePhotos(User $user, array $urls): array
    {
        $urls = $this->normalizeUrls($urls);
        if (count($urls) > self::MAX_PHOTOS) {
            throw ValidationException::withMessages([
                'urls' => ['相册最多 ' . self::MAX_PHOTOS . ' 张'],
            ]);
        }

        if ($this->currentUrls($user->id) === $urls) {
            // 没真改，不重新走审核
            return $this->photoList($user->id);
        }

        $oldIds = array_column($this->currentPhotos($user->id), 'id');

        $newIds = Db::transaction(function () use ($user, $urls): array {
            // 上一批还没审完的丢掉，只保留一条「当前提交」
            $this->photoRepository->deleteByUser($user->id, UserPhoto::AUDIT_PENDING);

            $ids = [];
            foreach ($urls as $index => $url) {
                $photo = $this->photoRepository->createRow(
                    $user->id,
                    $url,
                    $index,
                    UserPhoto::AUDIT_PENDING,
                );
                $ids[] = (int) $photo->id;
            }

            return $ids;
        });

        $this->auditRepository->upsertPending(
            $user->id,
            ProfileAudit::FIELD_PHOTO,
            $oldIds === [] ? null : implode(',', $oldIds),
            implode(',', $newIds),
        );

        return $this->photoList($user->id);
    }

    /**
     * `/user/settings`：附近的人、推荐里是否展示（直接生效，不进审核）。
     *
     * @param array<string,mixed> $data
     */
    public function updateSettings(User $user, array $data): void
    {
        $update = Arr::only($data, ['show_in_nearby', 'show_in_recommend']);
        if ($update !== []) {
            $user->update($update);
        }
    }

    /**
     * 我最近提交的那一版相册：有待审就用待审的，否则用已通过的。
     *
     * @return array<int,array<string,mixed>>
     */
    public function photoList(int $userId): array
    {
        return array_map(
            static fn (array $row): array => [
                'id' => $row['id'],
                'url' => $row['url'],
                'sort' => $row['sort'],
                'audit_status' => $row['audit_status'],
                'reject_reason' => $row['reject_reason'],
            ],
            $this->currentPhotos($userId),
        );
    }

    /**
     * @return array<int,array{id: int, url: string, sort: int, audit_status: int, reject_reason: null|string}>
     */
    private function currentPhotos(int $userId): array
    {
        $pending = [];
        $approved = [];
        foreach ($this->photoRepository->listByUser($userId) as $photo) {
            if ($photo->audit_status === UserPhoto::AUDIT_PENDING) {
                $pending[] = $photo;
            } elseif ($photo->audit_status === UserPhoto::AUDIT_APPROVED) {
                $approved[] = $photo;
            }
        }

        // 有待审就显示待审那批（自己看得到刚提交的），否则显示已通过的
        $rows = $pending !== [] ? $pending : $approved;

        return array_map(
            static fn (UserPhoto $photo): array => [
                'id' => (int) $photo->id,
                'url' => (string) $photo->url,
                'sort' => (int) $photo->sort,
                'audit_status' => (int) $photo->audit_status,
                'reject_reason' => $photo->reject_reason,
            ],
            $rows,
        );
    }

    /**
     * @return array<int,string>
     */
    private function currentUrls(int $userId): array
    {
        return array_column($this->currentPhotos($userId), 'url');
    }

    /**
     * 去空、去重、保持顺序。
     *
     * @param array<int,mixed> $urls
     * @return array<int,string>
     */
    private function normalizeUrls(array $urls): array
    {
        $clean = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url !== '' && ! in_array($url, $clean, true)) {
                $clean[] = $url;
            }
        }
        return $clean;
    }
}
