<?php

declare(strict_types=1);

namespace App\Crontab;

use App\Model\Cl\AiPersona;
use App\Repository\Cl\AiPersonaRepository;
use App\Service\Cl\Ai\AiChatService;
use Hyperf\Crontab\Annotation\Crontab;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * AI 代聊主动搭话（每 10 分钟一次）。
 *
 * 不是每个开着代聊的女号每次都发 —— 按 `proactive` 强度给概率：
 * 低 1/6、中 1/3、高 1/2。这样几十个女号也不会在同一分钟集体骚扰同一批男用户。
 */
final class AiProactiveCrontab
{
    /** 一次最多处理几个人设（防止某一轮把男用户全刷一遍）。 */
    private const BATCH = 3;

    public function __construct(
        private readonly AiPersonaRepository $personaRepository,
        private readonly AiChatService $aiChatService,
        private readonly LoggerInterface $logger,
    ) {}

    #[Crontab(
        rule: '*/10 * * * *',
        name: 'cl-ai-proactive',
        callback: 'execute',
        memo: 'AI 代聊：按活跃时段给男用户主动搭话',
    )]
    public function execute(): void
    {
        $personas = $this->personaRepository->listProactive(self::BATCH);

        foreach ($personas as $persona) {
            if (! $this->shouldFire($persona)) {
                continue;
            }

            try {
                $this->aiChatService->proactive($persona);
            } catch (Throwable $e) {
                $this->logger->warning('AI 主动搭话失败：' . $e->getMessage());
            }
        }
    }

    private function shouldFire(AiPersona $persona): bool
    {
        [$numerator, $denominator] = match ($persona->proactive) {
            AiPersona::PROACTIVE_LOW => [1, 6],
            AiPersona::PROACTIVE_MID => [1, 3],
            AiPersona::PROACTIVE_HIGH => [1, 2],
            default => [0, 1],
        };

        if ($numerator === 0) {
            return false;
        }

        return random_int(1, $denominator) <= $numerator;
    }
}
