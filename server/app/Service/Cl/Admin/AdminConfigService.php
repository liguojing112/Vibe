<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\LevelConfig;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\ConfigRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Config\AppConfigService;

/**
 * 后台运营配置（docs/07 第 3.15、3.16、3.19、3.21、3.23 节）。
 *
 * 两件事必须守住：
 * 1. **存完立刻让 App 看到**：`AppConfigService` 把配置缓存在 Redis 里，
 *    所以每次写完都要 `forget()` —— 不然会出现「后台改了免费条数，App 收费条还是老的」
 *    （`07` 第 3.15 节的验收就是这条）；
 * 2. **不能存坏值**：汇率 0、分成 200% 这种存进去，App 那边就是除零/多给钱。
 *    按 key 分类校验，而不是只看 `value_type`。
 */
final class AdminConfigService
{
    /** 配置分组（docs/07 第 3.15 节的 billing/share/chat/vip，加上后面几节自己的组）。 */
    public const GROUPS = [
        'billing' => '计费与分成 · 基础',
        'share' => '计费与分成 · 分成',
        'chat' => '聊天规则',
        'vip' => '会员',
        'withdraw' => '提现',
        'recharge' => '充值档位与客服',
        'agreement' => '协议与声明',
        // `compliance.disclosure`（合规声明）在种子里是单独一组，不是 agreement ——
        // 验收明确要求它「不能存空」，所以这一组必须也能在后台改
        'compliance' => '协议与声明 · 合规声明',
        'feature' => '功能开关',
    ];

    /** 「协议与声明」那个页面要看的两个分组（`07` 第 3.21 节）。 */
    public const AGREEMENT_GROUPS = ['agreement', 'compliance'];

    /**
     * 「计费与分成」那个页面要一次看完的分组。
     *
     * `07` 第 3.15 节列的是 billing / share / chat / vip 四个，这里**多带了 `withdraw`**：
     * 提现的档位、手续费、每日次数也是计费规则，但 `07` 那 11 个页面里没有一页管它
     * （§3.13 只说「提现档位后台配置」却没给地方），不加进来就只能改种子文件。
     */
    public const BILLING_GROUPS = ['billing', 'share', 'chat', 'vip', 'withdraw'];

    /** 万分比：0~10000（10000 = 100%）。 */
    private const RATIO_KEYS = ['share.unverified_factor', 'share.gift_rate', 'withdraw.fee_rate'];

    /** 必须 > 0 的整数（0 会让 App 那边除零 / 换算炸掉）。 */
    private const POSITIVE_KEYS = ['coin.per_yuan', 'point.per_yuan'];

    /** 不能为空的文本。 */
    private const NON_EMPTY_KEYS = ['compliance.disclosure', 'agreement.user', 'agreement.privacy'];

    /** 不能小于 0 的整数。 */
    private const NON_NEGATIVE_KEYS = [
        'chat.free_count_per_pair', 'vip.daily_free_messages', 'withdraw.min_yuan',
        'withdraw.daily_limit', 'chat.message_max_length', 'chat.image_max_mb',
        'chat.message_retention_months',
    ];

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly LevelConfigRepository $levelConfigRepository,
        private readonly AppConfigService $configService,
    ) {}

    /**
     * 某个分组的配置项（带 label / remark，界面直接铺表单）。
     *
     * @return array{group: string, items: array<int,array<string,mixed>>}
     */
    public function group(string $group): array
    {
        $this->assertGroup($group);

        $items = [];
        foreach ($this->configRepository->listByGroup($group) as $row) {
            $items[] = [
                'key' => (string) $row->key,
                'value' => (string) $row->value,
                'value_type' => (string) $row->value_type,
                'label' => (string) $row->label,
                'remark' => (string) ($row->remark ?? ''),
            ];
        }

        return ['group' => $group, 'items' => $items];
    }

    /**
     * 保存一个分组。**只认这个分组里已有的 key**，传别的 key 会被忽略 ——
     * 不然一个页面就能改到别的页面的配置。
     *
     * @param array<string,string> $values key => 新值
     */
    public function save(AdminUser $operator, string $group, array $values): void
    {
        $this->assertGroup($group);

        $allowed = [];
        foreach ($this->configRepository->listByGroup($group) as $row) {
            $allowed[(string) $row->key] = (string) $row->value_type;
        }

        // 传了这组没有的 key 就**直接报错**，不静默忽略。
        // 静默忽略会吞掉真问题：表单编码会把 key 里的 `.` 换成 `_`
        // （`coin.per_yuan` → `coin_per_yuan`），那样每个配置项都会被跳过，
        // 界面还显示「保存成功」——这个坑我踩过。
        $unknown = array_diff(array_keys($values), array_keys($allowed));
        if ($unknown !== []) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '没有这些配置项：' . implode('、', array_slice($unknown, 0, 3)));
        }

        foreach ($values as $key => $value) {
            $this->configService->set((string) $key, $this->validateValue((string) $key, (string) $value, $allowed[$key]));
        }

        // 存完必须清缓存，不然 App 那边还是老配置（`07` 第 3.15 节的验收就是这条）
        $this->configService->forget();
    }

    // ---------- 等级配置（P3.16）----------

    /**
     * 等级表（5 行）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function levels(): array
    {
        $list = [];
        foreach ($this->levelConfigRepository->allOrdered() as $level) {
            $list[] = [
                'level' => (int) $level->level,
                'name' => (string) $level->name,
                'threshold_points' => (int) $level->threshold_points,
                'message_price' => (int) $level->message_price,
                'message_share_rate' => (int) $level->message_share_rate,
                'voice_price' => (int) $level->voice_price,
                'video_price' => (int) $level->video_price,
            ];
        }

        return $list;
    }

    /**
     * 保存等级表。
     *
     * 校验（docs/07 第 3.16 节）：门槛必须递增、Lv1 门槛为 0、单价 ≥ 0、分成 0~10000。
     * **改门槛不会让已经升级的用户降级** —— 这里压根不碰 `cl_user`，升级只由
     * `LevelService::checkUpgrade` 在收到钱之后推，而它只升不降。
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function saveLevels(AdminUser $operator, array $rows): void
    {
        if ($rows === []) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '等级表不能为空');
        }

        $seen = [];
        $previousThreshold = -1;
        foreach ($rows as $row) {
            $level = (int) ($row['level'] ?? 0);
            $threshold = (int) ($row['threshold_points'] ?? 0);
            $price = (int) ($row['message_price'] ?? 0);
            $voice = (int) ($row['voice_price'] ?? 0);
            $video = (int) ($row['video_price'] ?? 0);
            $share = (int) ($row['message_share_rate'] ?? 0);

            if ($level < 1 || $level > 5 || isset($seen[$level])) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '等级只能是 1~5，且不能重复');
            }
            $seen[$level] = true;

            if ($level === 1 && $threshold !== 0) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, 'Lv1 的升级门槛必须是 0');
            }
            if ($threshold <= $previousThreshold) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '升级门槛必须一级比一级高');
            }
            $previousThreshold = $threshold;

            if ($price < 0 || $voice < 0 || $video < 0) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '单价不能是负数');
            }
            if ($share < 0 || $share > 10000) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '分成比例要在 0~10000 之间（万分比）');
            }
        }

        foreach ($rows as $row) {
            $level = $this->levelConfigRepository->findByLevel((int) $row['level']);
            if ($level === null) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '等级 ' . $row['level'] . ' 不存在');
            }

            $this->levelConfigRepository->saveById($level->id, [
                'name' => (string) ($row['name'] ?? $level->name),
                'threshold_points' => (int) $row['threshold_points'],
                'message_price' => (int) $row['message_price'],
                'message_share_rate' => (int) $row['message_share_rate'],
                'voice_price' => (int) $row['voice_price'],
                'video_price' => (int) $row['video_price'],
            ]);
        }

        $this->configService->forget();
    }

    // ---------- 内部 ----------

    private function assertGroup(string $group): void
    {
        if (! array_key_exists($group, self::GROUPS)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '没有这个配置分组');
        }
    }

    /** 按 `value_type` + 这个 key 的特殊规矩校验，返回要存的值（保持字符串）。 */
    private function validateValue(string $key, string $value, string $type): string
    {
        $value = trim($value);

        if (in_array($key, self::NON_EMPTY_KEYS, true) && $value === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $key . ' 不能为空');
        }

        return match ($type) {
            'bool' => in_array($value, ['0', '1'], true)
                ? $value
                : throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $key . ' 只能是 0 或 1'),
            'int' => (string) $this->intValue($key, $value),
            'json' => $this->jsonValue($key, $value),
            default => $value,
        };
    }

    private function intValue(string $key, string $value): int
    {
        if (! is_numeric($value)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $key . ' 必须是数字');
        }

        $number = (int) $value;
        if (in_array($key, self::POSITIVE_KEYS, true) && $number <= 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $key . ' 必须大于 0');
        }
        if (in_array($key, self::NON_NEGATIVE_KEYS, true) && $number < 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $key . ' 不能是负数');
        }
        if (in_array($key, self::RATIO_KEYS, true) && ($number < 0 || $number > 10000)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $key . ' 要在 0~10000 之间（万分比）');
        }

        return $number;
    }

    private function jsonValue(string $key, string $value): string
    {
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $key . ' 不是合法的 JSON');
        }

        // 数组里有负数也没意义（档位、等级这些）
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_numeric($item) && (int) $item < 0) {
                    throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $key . ' 里不能有负数');
                }
            }
        }

        return $value;
    }
}
