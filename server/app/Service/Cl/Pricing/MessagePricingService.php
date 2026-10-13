<?php

declare(strict_types=1);

namespace App\Service\Cl\Pricing;

use App\Model\Cl\LevelConfig;
use App\Model\Cl\User;
use App\Repository\Cl\FollowRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Config\AppConfigService;

/**
 * 私信报价（docs/06 第 3.2 节）。**按顺序判断，命中就停**：
 *
 * ```
 * 1. 互相关注 且 chat.mutual_follow_free = 1        → 免费 MUTUAL_FOLLOW
 * 2. 这对男女之间男方已发条数 < chat.free_count_per_pair → 免费 PAIR_FREE
 * 3. 有效会员 且 今天会员免费没用完                  → 免费 VIP_FREE
 * 4. 都不是                                          → 收费 = 女方当前等级的私信单价
 * ```
 *
 * 顺序很重要：**第 1 步命中了就不消耗会员额度**（场景 5）。所以这里绝不「先扣额度再判断」。
 *
 * 价格只由这里算，客户端只显示（铁律 6）。
 */
final class MessagePricingService
{
    public function __construct(
        private readonly AppConfigService $configService,
        private readonly FollowRepository $followRepository,
        private readonly LevelConfigRepository $levelConfigRepository,
        private readonly VipFreeQuota $vipFreeQuota,
    ) {}

    /**
     * 报价。
     *
     * @param int $maleSentCount 这对男女之间男方**已发送的普通消息条数**（存在会话表的 `male_sent_count`）。
     *                          会话表是 P4 的 T4.1 才建的，所以这里由调用方传进来：
     *                          还没聊过就传 0（默认值），T4.2 发消息时传真实条数。
     */
    public function quote(User $male, User $female, int $maleSentCount = 0): MessageQuote
    {
        $level = $this->levelOf($female);
        $pairFreeCount = max(0, (int) $this->configService->get('chat.free_count_per_pair', 0));
        $pairRemaining = max(0, $pairFreeCount - max(0, $maleSentCount));

        // 1. 互相关注免费
        if ((bool) $this->configService->get('chat.mutual_follow_free', 1)
            && $this->followRepository->exists($male->id, $female->id)
            && $this->followRepository->exists($female->id, $male->id)
        ) {
            return new MessageQuote(
                price: 0,
                freeReason: MessageQuote::FREE_MUTUAL_FOLLOW,
                pairFreeRemaining: $pairRemaining,
                vipFreeRemaining: $this->vipRemaining($male),
                receiverLevel: $level->level,
                unitPrice: $level->message_price,
            );
        }

        // 2. 这对男女的免费条数还没用完
        if ($pairRemaining > 0) {
            return new MessageQuote(
                price: 0,
                freeReason: MessageQuote::FREE_PAIR,
                pairFreeRemaining: $pairRemaining,
                vipFreeRemaining: $this->vipRemaining($male),
                receiverLevel: $level->level,
                unitPrice: $level->message_price,
            );
        }

        // 3. 会员每天额外免费条数
        $vipRemaining = $this->vipRemaining($male);
        if ($vipRemaining > 0) {
            return new MessageQuote(
                price: 0,
                freeReason: MessageQuote::FREE_VIP,
                pairFreeRemaining: 0,
                vipFreeRemaining: $vipRemaining,
                receiverLevel: $level->level,
                unitPrice: $level->message_price,
            );
        }

        // 4. 收费
        return new MessageQuote(
            price: (int) $level->message_price,
            freeReason: null,
            pairFreeRemaining: 0,
            vipFreeRemaining: 0,
            receiverLevel: $level->level,
            unitPrice: $level->message_price,
        );
    }

    /**
     * 给客户端看的免费提示（docs/05 第 4.2 节 `/user/{id}` 的 `free_hint`）。
     * 收费时返回 null —— 那种情况页面直接显示单价。
     */
    public function freeHint(MessageQuote $quote): ?string
    {
        return match ($quote->freeReason) {
            MessageQuote::FREE_MUTUAL_FOLLOW => '互相关注，私信免费',
            MessageQuote::FREE_PAIR => sprintf('还可免费发送 %d 条', $quote->pairFreeRemaining),
            MessageQuote::FREE_VIP => sprintf('会员今日还剩 %d 条免费', $quote->vipFreeRemaining),
            default => null,
        };
    }

    /**
     * 女方**实际**的分成比例（万分比）：未实名要乘未实名系数（docs/06 第 3.4 节）。
     *
     * @param int $levelRate 女方等级表里的分成（万分比）
     */
    public function shareRateOf(User $female, int $levelRate): int
    {
        if ($female->real_name_status === User::REAL_NAME_PASSED) {
            return $levelRate;
        }

        $factor = (int) $this->configService->get('share.unverified_factor', 10000);

        return $factor <= 0 ? 0 : intdiv($levelRate * $factor, 10000);
    }

    /**
     * 礼物分成比例（万分比，docs/06 第 4 节）：
     * `share.gift_rate`（默认 3400，不随等级变化），**只有** `share.unverified_apply_gift = 1` 时
     * 才乘未实名系数（默认不作用 —— 礼物和私信的口径不一样，别想当然套用）。
     */
    public function giftShareRateOf(User $female): int
    {
        $rate = (int) $this->configService->get('share.gift_rate', 0);
        if ($rate <= 0) {
            return 0;
        }

        $applyUnverified = (bool) $this->configService->get('share.unverified_apply_gift', false);
        if (! $applyUnverified) {
            return $rate;
        }

        return $this->shareRateOf($female, $rate);
    }

    /** 女方等级的私信分成（万分比），没算未实名系数。 */
    public function levelRateOf(User $female): int
    {
        return (int) $this->levelOf($female)->message_share_rate;
    }

    /**
     * 这一单女方能拿多少积分（docs/06 第 3.4 节）：
     *
     * ```
     * points = floor( price × rate × point.per_yuan ÷ ( 10000 × coin.per_yuan ) )
     * ```
     *
     * **全程整数运算，先乘后除最后取整**（用 `intdiv`，不准用浮点）。
     */
    public function pointsFor(int $price, int $rate): int
    {
        if ($price <= 0 || $rate <= 0) {
            return 0;
        }

        $pointPerYuan = (int) $this->configService->get('point.per_yuan', 0);
        $coinPerYuan = (int) $this->configService->get('coin.per_yuan', 0);
        $denominator = 10000 * $coinPerYuan;

        if ($pointPerYuan <= 0 || $denominator <= 0) {
            return 0;
        }

        return intdiv($price * $rate * $pointPerYuan, $denominator);
    }

    /**
     * 聊天页顶部那条收费文案（docs/05 第 4.5 节的 `fee.strip_text`，文案取自 `01` P06）。
     *
     * 全是服务端拼好的，客户端只管显示（铁律 6）。
     */
    public function stripText(MessageQuote $quote, int $balance): string
    {
        return match ($quote->freeReason) {
            MessageQuote::FREE_MUTUAL_FOLLOW => '互相关注，私信免费',
            MessageQuote::FREE_FEMALE_SEND => '女主可以免费发消息',
            MessageQuote::FREE_PAIR => sprintf(
                '还可免费发送 %d 条 · 之后 %d 币/条 · 余额 %d 币',
                $quote->pairFreeRemaining,
                $quote->unitPrice,
                $balance,
            ),
            MessageQuote::FREE_VIP => sprintf('会员今日还剩 %d 条免费 · 余额 %d 币', $quote->vipFreeRemaining, $balance),
            default => sprintf('私信 %d 币/条 · 余额 %d 币', $quote->unitPrice, $balance),
        };
    }

    /**
     * 收费说明弹窗的条目（docs/05 第 4.5 节 `/chat/fee-rules`，入口在 `01` P06 的收费条右侧）。
     *
     * **按当前配置现算**，不是写死的文案：后台把免费条数改成 0，这里第一句就跟着变。
     * 男女看到的不一样 —— 男方看「怎么扣钱」，女方看「怎么分成」。
     *
     * @return array<int,string>
     */
    public function feeRuleItems(User $user): array
    {
        return $user->isFemale() ? $this->femaleRuleItems() : $this->maleRuleItems();
    }

    /** @return array<int,string> */
    private function maleRuleItems(): array
    {
        $items = [];

        $pairFree = max(0, (int) $this->configService->get('chat.free_count_per_pair', 0));
        $items[] = $pairFree > 0
            ? sprintf('每对用户之间，前 %d 条私信免费', $pairFree)
            : '每对用户之间不设免费条数，第 1 条即计费';

        if ((bool) $this->configService->get('chat.mutual_follow_free', 1)) {
            $items[] = '互相关注后，私信免费';
        }

        $vipFree = max(0, (int) $this->configService->get('vip.daily_free_messages', 0));
        if ($vipFree > 0) {
            $items[] = sprintf('会员每天额外 %d 条免费私信（不限对象）', $vipFree);
        }

        $prices = [];
        foreach ($this->levelConfigRepository->allOrdered() as $level) {
            $prices[] = sprintf('Lv%d %d 币/条', (int) $level->level, (int) $level->message_price);
        }
        if ($prices !== []) {
            $items[] = '私信按对方等级计价：' . implode('、', $prices);
        }

        if ((bool) $this->configService->get('chat.image_same_price', 1)) {
            $items[] = '图片消息与文字消息同价';
        }

        $coinPerYuan = (int) $this->configService->get('coin.per_yuan', 0);
        if ($coinPerYuan > 0) {
            $items[] = sprintf('充值比例：1 元 = %d 金币，余额不足时无法发送', $coinPerYuan);
        }

        return $items;
    }

    /** @return array<int,string> */
    private function femaleRuleItems(): array
    {
        $items = ['女用户发送私信免费，不扣金币'];

        $rates = [];
        foreach ($this->levelConfigRepository->allOrdered() as $level) {
            $rates[] = sprintf('Lv%d %s', (int) $level->level, $this->percent((int) $level->message_share_rate));
        }
        if ($rates !== []) {
            $items[] = '收到男用户的付费私信，按你的等级分成：' . implode('、', $rates);
        }

        $factor = (int) $this->configService->get('share.unverified_factor', 10000);
        if ($factor > 0 && $factor < 10000) {
            $items[] = sprintf('未完成实名认证时，分成按 %s 计算', $this->percent($factor));
        }

        $pointPerYuan = (int) $this->configService->get('point.per_yuan', 0);
        if ($pointPerYuan > 0) {
            $items[] = sprintf('分成以积分结算，%d 积分 = 1 元，可在「我的收益」提现', $pointPerYuan);
        }

        return $items;
    }

    /** 万分比 → 百分数文案（3000 → `30%`，3456 → `34.56%`）。只用于显示。 */
    private function percent(int $rate): string
    {
        return rtrim(rtrim(number_format($rate / 100, 2, '.', ''), '0'), '.') . '%';
    }

    private function vipRemaining(User $male): int
    {
        if (! $male->isVip()) {
            return 0;
        }

        $daily = max(0, (int) $this->configService->get('vip.daily_free_messages', 0));

        return max(0, $daily - $this->vipFreeQuota->usedToday($male->id));
    }

    private function levelOf(User $female): LevelConfig
    {
        // `level` 理论上一定有值（表默认 1），但内存里新建的 User 可能还没这个属性，兜底成 1
        $levelNumber = (int) ($female->level ?? 1);
        $level = $this->levelConfigRepository->findByLevel($levelNumber);
        if ($level instanceof LevelConfig) {
            return $level;
        }

        // 配置缺失时不抛错：按 Lv1 兜底，避免整条链路因为一条配置挂掉
        $fallback = $this->levelConfigRepository->findByLevel(1);
        if ($fallback instanceof LevelConfig) {
            return $fallback;
        }

        return new LevelConfig(['level' => 1, 'message_price' => 0]);
    }
}
