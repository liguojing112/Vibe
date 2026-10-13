<?php

declare(strict_types=1);

namespace App\Service\Cl\Wallet;

use App\Exception\ClBusinessException;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Repository\Cl\CoinLogRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Config\AppConfigService;

/**
 * 金币钱包的**查询**（docs/05 第 4.7 节，P13/P22/P24 三个页面）。
 *
 * 「改钱」在 `CoinWalletService` 里，这里只读不写。
 *
 * 价格一律**从等级配置现算**（`cl_level_config`），不在代码里写死范围 ——
 * 后台把 Lv1 单价从 5 改成 8，P13 上的「按对方等级 X~Y 币/条」立刻就跟着变。
 */
final class WalletQueryService
{
    /** P24 每页条数（docs/05 第 1 节分页约定 20）。 */
    public const PAGE_SIZE = 20;

    /**
     * P22 的说明文字（docs/01 P22 的原文）。
     *
     * 写在这里而不是配置里：`docs/06` 第 2.6 节给充值页列的 4 个可配项是「档位 / 客服 / 合规声明」，
     * 这句说明不在其中，属于固定文案。**合规声明**才是后台可改的那个（`compliance.disclosure`）。
     */
    private const RECHARGE_NOTICE = '选好档位后联系客服充值，客服确认后金币到账';

    public function __construct(
        private readonly CoinWalletService $walletService,
        private readonly CoinLogRepository $coinLogRepository,
        private readonly LevelConfigRepository $levelConfigRepository,
        private readonly AppConfigService $configService,
    ) {}

    /**
     * `/wallet/coin`：余额 + 单价范围。
     *
     * @return array<string,mixed>
     */
    public function coin(User $user): array
    {
        $this->assertMale($user);
        $callEnabled = (bool) $this->configService->get('feature.call_enabled', false);

        $messagePrices = [];
        $voicePrices = [];
        $videoPrices = [];
        foreach ($this->levelConfigRepository->allOrdered() as $level) {
            $messagePrices[] = (int) $level->message_price;
            $voicePrices[] = (int) $level->voice_price;
            $videoPrices[] = (int) $level->video_price;
        }

        $data = [
            'balance' => $this->walletService->balanceOf($user->id),
            // 私信按对方等级计价，所以给的是区间（最低等级单价 ~ 最高等级单价）
            'message_price_min' => $messagePrices === [] ? 0 : min($messagePrices),
            'message_price_max' => $messagePrices === [] ? 0 : max($messagePrices),
            'call_enabled' => $callEnabled,
        ];

        if ($callEnabled) {
            // 开关关着时不返回语音/视频价格（docs/01 第 4 节：入口都不显示，价格更不该露出来）
            $data['voice_price_min'] = $voicePrices === [] ? 0 : min($voicePrices);
            $data['voice_price_max'] = $voicePrices === [] ? 0 : max($voicePrices);
            $data['video_price_min'] = $videoPrices === [] ? 0 : min($videoPrices);
            $data['video_price_max'] = $videoPrices === [] ? 0 : max($videoPrices);
        }

        return $data;
    }

    /**
     * `/wallet/coin/logs`：收支明细（P24 的 全部 / 充值 / 消费 三个 Tab）。
     *
     * @param string $type all / recharge / spend
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function coinLogs(User $user, string $type, int $page): array
    {
        $this->assertMale($user);
        $types = $this->logTypes($type);

        return [
            'list' => $this->coinLogRepository->pageForUser(
                $user->id,
                $types,
                max(0, ($page - 1) * self::PAGE_SIZE),
                self::PAGE_SIZE,
            ),
            'total' => $this->coinLogRepository->countForUser($user->id, $types),
            'page' => $page,
        ];
    }

    /**
     * `/wallet/recharge-info`：充值档位、客服信息、合规声明、说明文字（P22）。
     *
     * @return array<string,mixed>
     */
    public function rechargeInfo(User $user): array
    {
        $this->assertMale($user);

        return [
            'balance' => $this->walletService->balanceOf($user->id),
            'packs' => $this->packs(),
            'service' => [
                'wechat' => (string) $this->configService->get('service.wechat', ''),
                'qrcode_url' => (string) $this->configService->get('service.qrcode_url', ''),
                'phone' => (string) $this->configService->get('service.phone', ''),
            ],
            // 铁律 13：充值页必须固定显示合规声明，服务端保证它不会为空
            'compliance_disclosure' => $this->complianceDisclosure(),
            'notice' => self::RECHARGE_NOTICE,
        ];
    }

    /**
     * 充值档位（`recharge.packs` 是 JSON 数组）。后台填错了也不能让 App 崩，所以这里逐个挑字段。
     *
     * @return array<int,array{coins: int, yuan: int}>
     */
    private function packs(): array
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
            $packs[] = ['coins' => (int) $pack['coins'], 'yuan' => (int) $pack['yuan']];
        }

        return $packs;
    }

    /** 合规声明：后台可改文字但不能改成空（docs/06 第 2.6 节）。这里再兜一层底。 */
    private function complianceDisclosure(): string
    {
        $text = trim((string) $this->configService->get('compliance.disclosure', ''));

        return $text === '' ? '本平台为付费聊天服务。' : $text;
    }

    /**
     * @return array<int,string>
     */
    private function logTypes(string $type): array
    {
        return match ($type) {
            'recharge' => [CoinLogType::RECHARGE->value],
            // 后台扣减也算「消费」（超级管理员手动扣的，用户看到的也是少了一笔）
            'spend' => [
                CoinLogType::MESSAGE->value,
                CoinLogType::GIFT->value,
                CoinLogType::VIP->value,
                CoinLogType::ADMIN_DEDUCT->value,
            ],
            default => [],
        };
    }

    /** 金币是男用户的（docs/05 第 4.7 节标题就是「金币（男）」）；女用户 App 里根本没有这些入口。 */
    private function assertMale(User $user): void
    {
        if (! $user->isMale()) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '金币钱包只有男用户有');
        }
    }
}
