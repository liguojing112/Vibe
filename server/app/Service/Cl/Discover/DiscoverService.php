<?php

declare(strict_types=1);

namespace App\Service\Cl\Discover;

use App\Model\Cl\LevelConfig;
use App\Model\Cl\User;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\DiscoverRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\Carbon\Carbon;
use Hyperf\Collection\Collection;

/**
 * 发现页（docs/01 P01、docs/05 第 4.3 节「广场」= 发现页）。
 *
 * 列表项的「在线 / 新人 / 已认证」都是**算出来的**，不是存的字段；
 * 男用户看到的女用户卡片要带**她等级对应的价格**（价格只显示服务端算好的数，铁律 6）。
 */
final class DiscoverService
{
    public function __construct(
        private readonly DiscoverRepository $discoverRepository,
        private readonly BlockRepository $blockRepository,
        private readonly LevelConfigRepository $levelConfigRepository,
        private readonly AppConfigService $configService,
    ) {}

    /**
     * @param array<string,mixed> $params tab / city / age_min / age_max / purposes / verified / complete / online_only
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function list(User $viewer, array $params, int $page): array
    {
        $filters = $this->buildFilters($viewer, $params);
        $result = $this->discoverRepository->paginate($filters, $this->offset($page), DiscoverRepository::PAGE_SIZE);

        return [
            'list' => $this->buildItems($viewer, $result['list']),
            'total' => $result['total'],
            'page' => $page,
        ];
    }

    /**
     * 按昵称或 ID 搜异性（docs/05 第 4.3 节）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function search(User $viewer, string $keyword, int $page): array
    {
        $filters = $this->buildFilters($viewer, ['tab' => 'search', 'keyword' => $keyword]);
        $result = $this->discoverRepository->paginate($filters, $this->offset($page), DiscoverRepository::PAGE_SIZE);

        return [
            'list' => $this->buildItems($viewer, $result['list']),
            'total' => $result['total'],
            'page' => $page,
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function buildFilters(User $viewer, array $params): array
    {
        return [
            'viewer_gender' => $viewer->gender,
            'viewer_city' => $viewer->city,
            // 任一方向拉黑的人都不出现（docs/01 P01）
            'exclude_ids' => $this->blockRepository->relatedBlockedIds($viewer->id),
            'tab' => (string) ($params['tab'] ?? 'recommend'),
            'city' => $params['city'] ?? null,
            'age_min' => $params['age_min'] ?? null,
            'age_max' => $params['age_max'] ?? null,
            'purposes' => array_values(array_filter((array) ($params['purpose'] ?? []))),
            'verified' => ! empty($params['verified']),
            'complete' => ! empty($params['complete']),
            'online_only' => ! empty($params['online_only']),
            'keyword' => $params['keyword'] ?? '',
        ];
    }

    /**
     * @param Collection<int,User> $users
     * @return array<int,array<string,mixed>>
     */
    private function buildItems(User $viewer, Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        // 价格按等级查一次就够，别在循环里查库
        $levels = $this->levelConfigRepository->allOrdered()->keyBy('level');
        $callEnabled = (bool) $this->configService->get('feature.call_enabled', false);
        // 只有男用户看女用户时才显示价格（docs/01 P01）
        $withPrice = $viewer->isMale();
        $onlineSince = Carbon::now()->subMinutes(DiscoverRepository::ONLINE_MINUTES);
        $newSince = Carbon::now()->subDays(DiscoverRepository::NEW_USER_DAYS);

        $items = [];
        foreach ($users as $user) {
            $item = [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
                'city' => $user->city,
                'age' => $user->age(),
                'job' => $user->job,
                'bio' => $user->bio,
                'level' => $user->level,
                'online' => $user->last_active_at !== null && $user->last_active_at->greaterThanOrEqualTo($onlineSince),
                'is_new' => $user->created_at !== null && $user->created_at->greaterThanOrEqualTo($newSince),
                'verified' => $user->real_name_status === 2,
            ];

            if ($withPrice) {
                /** @var null|LevelConfig $level */
                $level = $levels->get($user->level);
                $item['message_price'] = $level === null ? 0 : $level->message_price;
                if ($callEnabled) {
                    // 语音/视频开关关着的时候不返回这两项（docs/01 第 4 节）
                    $item['voice_price'] = $level === null ? 0 : $level->voice_price;
                    $item['video_price'] = $level === null ? 0 : $level->video_price;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    private function offset(int $page): int
    {
        return max(0, ($page - 1) * DiscoverRepository::PAGE_SIZE);
    }
}
