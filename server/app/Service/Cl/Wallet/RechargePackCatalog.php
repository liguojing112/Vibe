<?php

declare(strict_types=1);

namespace App\Service\Cl\Wallet;

use App\Service\Cl\Config\AppConfigService;

/**
 * 充值档位（`recharge.packs`）。
 *
 * P22 充值页展示、在线支付下单都要用同一份档位，所以从 `WalletQueryService` 里提出来。
 * 档位是后台填的 JSON，写错了也不能让 App 崩 —— 这里逐个挑字段。
 */
final class RechargePackCatalog
{
    public function __construct(
        private readonly AppConfigService $configService,
    ) {}

    /**
     * @return array<int,array{coins: int, yuan: int}>
     */
    public function all(): array
    {
        $raw = $this->configService->get('recharge.packs', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return [];
        }

        $packs = [];
        foreach ($raw as $pack) {
            if (! is_array($pack) || ! isset($pack['coins'], $pack['yuan'])) {
                continue;
            }
            $coins = (int) $pack['coins'];
            $yuan = (int) $pack['yuan'];
            if ($coins <= 0 || $yuan <= 0) {
                continue;
            }
            $packs[] = ['coins' => $coins, 'yuan' => $yuan];
        }

        return $packs;
    }

    /**
     * 按下标取档位。App 只传下标，金额和金币都由服务端定，不信任客户端传的金额。
     *
     * @return null|array{coins: int, yuan: int}
     */
    public function find(int $index): ?array
    {
        return $this->all()[$index] ?? null;
    }
}
