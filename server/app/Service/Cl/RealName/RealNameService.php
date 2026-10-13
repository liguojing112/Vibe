<?php

declare(strict_types=1);

namespace App\Service\Cl\RealName;

use App\Exception\ClBusinessException;
use App\Model\Cl\Notice;
use App\Model\Cl\RealName;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\RealNameRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Notice\NoticeService;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;

/**
 * 实名认证（docs/04 第 1 节、`docs/01` P29）。
 *
 * **身份证号一个字的明文都不落库**：
 * - `id_card_encrypted` 是 AES-256-GCM 密文（密钥在 `.env` 的 `CL_ID_CARD_KEY`）；
 * - `id_card_mask` 是打码后的 `110***********1234`，页面回显用这个；
 * - `id_card_hash` 是 SHA-256，只给后台**提示**「这号别的账号也用过」，**不拦截**
 *   （一家人共用一张身份证是合理的，拦了反而是 bug）。
 *
 * 状态值和 `cl_user.real_name_status` 同步维护：App 读 `cl_user` 那一列，
 * 后台看 `cl_real_name.status` 和驳回原因。
 */
final class RealNameService
{
    /** 加密算法与参数（GCM 自带完整性校验，比 CBC 更适合存身份证号）。 */
    private const CIPHER = 'aes-256-gcm';

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    /** GB 11643-1999 的加权因子与校验码表（第 18 位）。 */
    private const WEIGHTS = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

    private const CHECK_CODES = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

    private const STATUS_TEXTS = [
        User::REAL_NAME_NONE => '未认证',
        User::REAL_NAME_AUDITING => '审核中',
        User::REAL_NAME_PASSED => '已通过',
        User::REAL_NAME_REJECTED => '已驳回',
    ];

    public function __construct(
        private readonly RealNameRepository $realNameRepository,
        private readonly UserRepository $userRepository,
        private readonly NoticeService $noticeService,
        private readonly ConfigInterface $config,
    ) {}

    /**
     * `/realname`：实名状态（P29）。
     *
     * 没提交过也给一份完整结构（`status = 0`），客户端不用为「还没提交」写一个分支。
     *
     * @return array<string,mixed>
     */
    public function info(User $user): array
    {
        $row = $this->realNameRepository->findByUserId($user->id);
        // 有申请记录就以**记录**为准（它才是这次提交的状态），没有才退回 cl_user 那一列。
        // 两边本来就不该不一致（只有 submit/approve/reject 会写它们），但演示数据是手写的、
        // 后台以后也可能直接改 cl_user —— 不一致时不能给客户端一个自相矛盾的包
        // （「状态：已通过」+「还能提交」）。
        $status = $row === null ? (int) $user->real_name_status : (int) $row->status;

        return [
            'status' => $status,
            'status_text' => self::STATUS_TEXTS[$status] ?? '未认证',
            // 姓名不敏感（是用户自己的名字），只有身份证号打码
            'real_name' => $row === null ? '' : (string) $row->real_name,
            'id_card_mask' => $row === null ? '' : (string) $row->id_card_mask,
            'reject_reason' => $row?->reject_reason,
            'submitted_at' => $row?->created_at?->toDateTimeString(),
            'audited_at' => $row?->audited_at?->toDateTimeString(),
            // 未认证和被驳回可以提交；审核中和已通过不行（和上面报出去的 status 同一个来源）
            'can_submit' => $status === User::REAL_NAME_NONE || $status === User::REAL_NAME_REJECTED,
        ];
    }

    /**
     * `/realname/submit`：提交实名（P29）。
     *
     * 被驳回之后可以重新提交（**覆盖**旧记录，不是新增一条）。
     *
     * @return array<string,mixed>
     */
    public function submit(User $user, string $realName, string $idCard): array
    {
        $realName = trim($realName);
        $idCard = strtoupper(trim($idCard));

        if ($realName === '') {
            throw new ClBusinessException(BizCode::REAL_NAME_FORMAT_ERROR, '请填写真实姓名');
        }
        if (! $this->isValidIdCard($idCard)) {
            throw new ClBusinessException(BizCode::REAL_NAME_FORMAT_ERROR);
        }

        $existing = $this->realNameRepository->findByUserId($user->id);
        if ($existing !== null && $existing->status === RealName::STATUS_AUDITING) {
            throw new ClBusinessException(BizCode::PROFILE_AUDITING, '实名认证审核中，请勿重复提交');
        }
        if ($existing !== null && $existing->status === RealName::STATUS_PASSED) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '已通过实名认证，无需重复提交');
        }

        $payload = [
            'user_id' => $user->id,
            'real_name' => $realName,
            'id_card_encrypted' => $this->encrypt($idCard),
            'id_card_mask' => $this->mask($idCard),
            'id_card_hash' => hash('sha256', $idCard),
            'status' => RealName::STATUS_AUDITING,
            // 重新提交要把上一次的驳回原因和审核痕迹清掉
            'reject_reason' => null,
            'auditor_id' => null,
            'audited_at' => null,
        ];

        Db::transaction(function () use ($existing, $payload, $user): void {
            if ($existing === null) {
                $this->realNameRepository->create($payload);
            } else {
                $this->realNameRepository->saveById($existing->id, $payload);
            }

            $this->userRepository->saveById($user->id, [
                'real_name_status' => User::REAL_NAME_AUDITING,
            ]);
        });

        return $this->info($user->refresh());
    }

    /**
     * 后台审核通过（`docs/08` T7 接 HTTP，先在这里做出来）。
     *
     * 通过之后分成比例**立刻**按实名的算（`MessagePricingService::shareRateOf`），不用再动别的。
     */
    public function approve(User $user, int $auditorId): void
    {
        $row = $this->requireAuditing($user);

        Db::transaction(function () use ($row, $user, $auditorId): void {
            $this->realNameRepository->saveById($row->id, [
                'status' => RealName::STATUS_PASSED,
                'reject_reason' => null,
                'auditor_id' => $auditorId,
                'audited_at' => date('Y-m-d H:i:s'),
            ]);
            $this->userRepository->saveById($user->id, ['real_name_status' => User::REAL_NAME_PASSED]);

            $this->noticeService->send(
                $user->id,
                Notice::CATEGORY_SYSTEM,
                '实名认证已通过',
                '你的身份信息已通过审核，分成比例从现在起按实名的标准计算',
                'REAL_NAME',
                (int) $row->id,
            );
        });
    }

    /** 后台驳回（要填原因，App 上 P29 会显示）。 */
    public function reject(User $user, int $auditorId, string $reason): void
    {
        $row = $this->requireAuditing($user);
        $reason = trim($reason);
        if ($reason === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '驳回必须填写原因');
        }

        Db::transaction(function () use ($row, $user, $auditorId, $reason): void {
            $this->realNameRepository->saveById($row->id, [
                'status' => RealName::STATUS_REJECTED,
                'reject_reason' => $reason,
                'auditor_id' => $auditorId,
                'audited_at' => date('Y-m-d H:i:s'),
            ]);
            $this->userRepository->saveById($user->id, ['real_name_status' => User::REAL_NAME_REJECTED]);

            $this->noticeService->send(
                $user->id,
                Notice::CATEGORY_SYSTEM,
                '实名认证被驳回',
                $reason,
                'REAL_NAME',
                (int) $row->id,
            );
        });
    }

    /**
     * 解密身份证号（**只给后台审核看**，App 永远只拿到打码的）。
     */
    public function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || \strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return '';
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $cipher = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt($cipher, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    /**
     * 18 位身份证：格式 + **校验位**（docs/01 P29）。
     *
     * 校验位算法：前 17 位各自乘加权因子求和，除以 11 取余数，查表得到第 18 位
     * （余数 2 对应 'X'）。
     */
    private function isValidIdCard(string $idCard): bool
    {
        if (preg_match('/^\d{17}[\dX]$/', $idCard) !== 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 17; ++$i) {
            $sum += ((int) $idCard[$i]) * self::WEIGHTS[$i];
        }

        return self::CHECK_CODES[$sum % 11] === $idCard[17];
    }

    /** `110***********1234`：留前 3 后 4（docs/01 P29 的格式）。 */
    private function mask(string $idCard): string
    {
        $length = \strlen($idCard);
        if ($length <= 7) {
            return $idCard;
        }

        return substr($idCard, 0, 3) . str_repeat('*', $length - 7) . substr($idCard, -4);
    }

    /** AES-256-GCM：`base64(IV | TAG | 密文)`。 */
    private function encrypt(string $plain): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($cipher === false) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '实名信息保存失败');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    /**
     * 从配置里的密钥推出 32 字节的 AES 密钥。
     *
     * **不直接用配置字符串**：`.env` 里那串长度不确定（示例值是 base64 的、有人会直接写一句口令），
     * 先 SHA-256 成固定 32 字节，换什么长度的密钥都不会把加密搞坏。
     */
    private function key(): string
    {
        $configured = trim((string) $this->config->get('cl.id_card_key', ''));
        if ($configured === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '实名功能未配置加密密钥');
        }

        return hash('sha256', $configured, true);
    }

    private function requireAuditing(User $user): RealName
    {
        $row = $this->realNameRepository->findByUserId($user->id);
        if ($row === null || $row->status !== RealName::STATUS_AUDITING) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '没有待审核的实名申请');
        }

        return $row;
    }
}
