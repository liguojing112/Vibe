<?php

declare(strict_types=1);

namespace App\Service\Cl\Vip;

use App\Exception\ClBusinessException;
use App\Model\Cl\User;
use App\Model\Cl\VipOrder;
use App\Model\Cl\VipPlan;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Repository\Cl\UserRepository;
use App\Repository\Cl\VipOrderRepository;
use App\Repository\Cl\VipPlanRepository;
use App\Service\Cl\Chat\Concerns\RetriesTransactions;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\CoinWalletService;
use Hyperf\Carbon\Carbon;

/**
 * 会员（docs/06 第 6 节，P12）。
 *
 * **只有男用户能开通**：女用户 App 里根本没有会员中心入口（docs/01 P12 标注「男」），
 * 所以这里和金币钱包一样先挡一道 —— 免得有人直接打接口。
 *
 * 开通就是三件事，一个事务里做完：扣金币（`type = VIP`）→ 延长 `vip_expire_at` → 写会员订单。
 * 「有效会员」= `vip_expire_at > 现在`（`User::isVip()`），没有单独的会员表。
 *
 * 会员**不抵扣礼物**（docs/06 第 6 节末），礼物那条路不查会员 —— 不是漏了。
 */
final class VipService
{
    /**
     * 金币流水的 `ref_type`：`ref_id` 指向 `cl_vip_order.id`。
     *
     * 订单必须先落库再扣钱（`CoinWalletService::decrease` 要拿订单 id 当 ref 做去重），
     * 所以事务里的顺序是「写订单 → 扣金币 → 改到期时间」，和文档第 6 节写的顺序不完全一致，
     * 但**同一个事务**，出任何错都一起回滚。
     */
    public const REF_TYPE = 'vip_order';

    /**
     * P12 「会员与聊天费用」提示卡（docs/01 P12 的原文）。
     *
     * 和充值页的说明文字一样属于固定文案，不放配置里：`docs/06` 第 2.4 节给会员列的可配项
     * 只有「每日免费条数」和「女用户免会员看访客」两个。
     */
    private const NOTICE = '会员不抵扣礼物费用；每日额外免费私信用完后按单价扣费';

    public function __construct(
        private readonly VipPlanRepository $planRepository,
        private readonly VipOrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly CoinWalletService $coinWalletService,
        private readonly AppConfigService $configService,
    ) {}

    use RetriesTransactions;

    /**
     * `/vip/info`：套餐、权益、我的到期时间。
     *
     * @return array<string,mixed>
     */
    public function info(User $user): array
    {
        $this->assertMale($user);

        $plans = [];
        foreach ($this->planRepository->enabledOrdered() as $plan) {
            $plans[] = $this->planPayload($plan);
        }

        return [
            'is_vip' => $user->isVip(),
            // 过期了就不返回（返回一个过去的日期，页面还要自己判断是不是会员，容易写错）
            'expire_at' => $user->isVip() ? $user->vip_expire_at?->toDateTimeString() : null,
            'plans' => $plans,
            'benefits' => $this->benefits(),
            'balance' => $this->coinWalletService->balanceOf($user->id),
            'notice' => self::NOTICE,
        ];
    }

    /**
     * `/vip/buy`：用金币买套餐（余额不足 → 20001，P30）。
     *
     * 续费是在**原到期时间**上加天数；已经过期了就从现在开始算（docs/06 第 6 节）。
     *
     * @return array<string,mixed>
     */
    public function buy(User $user, int $planId, string $clientOrderId): array
    {
        $this->assertMale($user);

        $plan = $this->planRepository->findEnabled($planId);
        if ($plan === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '会员套餐不存在或已下架');
        }

        // 幂等快路：网络重试打进来时不用开事务
        $existing = $this->orderRepository->findByOrderNo($clientOrderId);
        if ($existing !== null) {
            return $this->buyPayload($user->id, $existing);
        }

        $order = $this->runTransaction(function () use ($user, $plan, $clientOrderId): VipOrder {
            // 先锁住用户行：`vip_expire_at` 是这一行上的字段，不锁的话两个并发续费
            // 会各自基于同一个旧到期时间加天数，后提交的把先提交的覆盖掉（用户付两次只拿一次）。
            $locked = $this->userRepository->lockById($user->id);
            if ($locked === null) {
                throw new ClBusinessException(BizCode::USER_NOT_FOUND);
            }

            // 拿到锁之后再查一次：并发打同一个订单号时，第二个协程在这里就看见第一条订单了
            $duplicated = $this->orderRepository->findByOrderNo($clientOrderId);
            if ($duplicated !== null) {
                return $duplicated;
            }

            $expireBefore = $locked->vip_expire_at;
            if ($expireBefore !== null && ! $expireBefore->isFuture()) {
                $expireBefore = null;
            }
            $expireAfter = self::extendExpire($expireBefore, (int) $plan->days);

            $order = $this->orderRepository->create([
                'order_no' => $clientOrderId,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'days' => (int) $plan->days,
                'price_coins' => (int) $plan->price_coins,
                'source' => VipOrder::SOURCE_COIN,
                'expire_before' => $expireBefore?->toDateTimeString(),
                'expire_after' => $expireAfter->toDateTimeString(),
            ]);

            // 余额不足抛 20001，整个事务回滚（订单、金币都不会留下）
            $this->coinWalletService->decrease(
                $user->id,
                (int) $plan->price_coins,
                CoinLogType::VIP,
                self::REF_TYPE,
                (int) $order->id,
                '开通会员 · ' . $plan->name,
            );

            $this->userRepository->saveById($user->id, [
                'vip_expire_at' => $expireAfter->toDateTimeString(),
            ]);

            return $order;
        });

        return $this->buyPayload($user->id, $order);
    }

    /**
     * 算延长之后的会员到期时间：**没过期就在原到期时间上加，过期了（或从来没买过）就从现在算**。
     *
     * App 买会员（[buy]）和后台开通会员（`AdminUserService::grantVip`）都要这个算法，
     * 所以抽出来共用 —— 各写一份迟早会走岔，而后台那份要是误写成「从现在算」，
     * 用户会平白丢掉还没用完的会员时间。
     *
     * 参数不收 `?Carbon` 而收 `DateTimeInterface`：模型上的 `vip_expire_at` 到底是
     * `Carbon\Carbon` 还是 `Hyperf\Carbon\Carbon` 会跟着底层 cast 变，钉死具体类名两边就传不过来了。
     */
    public static function extendExpire(mixed $current, int $days): Carbon
    {
        $now = Carbon::now();
        if (! $current instanceof \DateTimeInterface || $current <= $now) {
            return $now->addDays($days);
        }

        // 原样保留原来的时刻（含时区），只加天数
        return $now->setTimestamp($current->getTimestamp())->addDays($days);
    }

    /**
     * P12 的权益网格（docs/01 P12；docs/05 说「从配置生成」—— 第 4 项的条数就来自配置）。
     *
     * 文案和图标都由服务端给，客户端只管铺格子（和聊天页收费条一个做法）。
     *
     * 第 4 项的**标题要短**：页面是两列网格，标题只有一行、放不下就省略号截掉了，
     * 那正好会把「多少条」这个从配置来的数字截没；条数放到第二行（能放两行）。
     *
     * @return array<int,array{icon: string, title: string, desc: string}>
     */
    public function benefits(): array
    {
        return [
            ['icon' => '👀', 'title' => '查看访客', 'desc' => '谁来看过你，一个不漏'],
            ['icon' => '💖', 'title' => '查看喜欢我的人', 'desc' => '完整看到谁对你动了心'],
            ['icon' => '💎', 'title' => '会员标识', 'desc' => '资料页展示会员专属标识'],
            [
                'icon' => '💬',
                'title' => '每日免费私信',
                'desc' => sprintf('额外 %d 条，所有对象合计，每天零点重置', $this->dailyFreeMessages()),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function planPayload(VipPlan $plan): array
    {
        $coinPerYuan = (int) $this->configService->get('coin.per_yuan', 0);
        $price = (int) $plan->price_coins;

        return [
            'id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'days' => (int) $plan->days,
            'price_coins' => $price,
            // 设计图 12 的横幅写的是「¥39 / 月」，所以换算也放服务端算（铁律 6）
            'price_yuan' => $coinPerYuan > 0 ? intdiv($price, $coinPerYuan) : 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buyPayload(int $userId, VipOrder $order): array
    {
        return [
            'expire_at' => $order->expire_after->toDateTimeString(),
            'days' => (int) $order->days,
            'price_coins' => (int) $order->price_coins,
            'balance' => $this->coinWalletService->balanceOf($userId),
        ];
    }

    private function dailyFreeMessages(): int
    {
        return max(0, (int) $this->configService->get('vip.daily_free_messages', 0));
    }

    /** 会员是男用户的功能（docs/01 P12 标注「男」，女用户没有会员中心入口）。 */
    private function assertMale(User $user): void
    {
        if (! $user->isMale()) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '会员只有男用户可以开通');
        }
    }
}
