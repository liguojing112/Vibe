<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Model\Cl\Tag;
use App\Repository\Cl\CityRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Repository\Cl\TagRepository;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation as OA;
use Hyperf\Validation\ValidationException;

/**
 * App 启动配置与协议文本（docs/05 第 4.1 节）。这两个接口免鉴权。
 */
#[OA\HyperfServer(name: 'http')]
final class AppConfigController
{
    public function __construct(
        private readonly AppConfigService $configService,
        private readonly TagRepository $tagRepository,
        private readonly CityRepository $cityRepository,
        private readonly LevelConfigRepository $levelConfigRepository,
    ) {}

    #[OA\Get(
        path: '/api/v1/app/config',
        operationId: 'clAppConfig',
        summary: 'App 启动配置',
        tags: ['app:config']
    )]
    public function config(): ApiResult
    {
        $callEnabled = (bool) $this->configService->get('feature.call_enabled', false);
        $callPrices = $this->levelConfigRepository->callPriceRange();

        return ApiResult::success([
            'call_enabled' => $callEnabled,
            // 「示例价格」（P01 的两张快捷卡、P02 的提示、P16 的横幅）：
            // 给的是各等级里的最低单价，页面上写「X 币/分钟起」。
            // **开关关着时不返回**（给 0）—— 和 P13 一个口径：入口都不显示，价格更不该露出来。
            'voice_price_min' => $callEnabled ? $callPrices['voice_min'] : 0,
            'video_price_min' => $callEnabled ? $callPrices['video_min'] : 0,
            'compliance_disclosure' => (string) $this->configService->get('compliance.disclosure', ''),
            'service' => [
                'wechat' => (string) $this->configService->get('service.wechat', ''),
                'qrcode_url' => (string) $this->configService->get('service.qrcode_url', ''),
                'phone' => (string) $this->configService->get('service.phone', ''),
            ],
            'chat_max_length' => (int) $this->configService->get('chat.message_max_length', 500),
            'image_max_mb' => (int) $this->configService->get('chat.image_max_mb', 5),
            'cities' => $this->cityRepository->enabledList()->pluck('name')->values()->all(),
            'tags' => $this->tagRepository->enabledList()
                ->map(static fn (Tag $tag): array => ['id' => $tag->id, 'name' => $tag->name])
                ->values()
                ->all(),
            'version' => (string) $this->configService->get('app.version', '1.0.0'),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/app/agreement/{type}',
        operationId: 'clAppAgreement',
        summary: '协议文本（type = user / privacy）',
        tags: ['app:config']
    )]
    public function agreement(RequestInterface $request): ApiResult
    {
        $type = (string) $request->route('type');
        if (! in_array($type, ['user', 'privacy'], true)) {
            throw ValidationException::withMessages(['type' => ['协议类型不正确']]);
        }

        return ApiResult::success([
            'type' => $type,
            'content' => (string) $this->configService->get('agreement.' . $type, ''),
        ]);
    }
}
