<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\User;
use App\Repository\IRepository;
use Hyperf\Carbon\Carbon;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection as ModelCollection;

/**
 * 发现页的用户查询（docs/05 第 4.3 节 P01）。
 *
 * 「谁能被看到」这件事有好几条硬规则，集中放在这里，服务层只管拼条件：
 * 异性、未封禁、**有头像**（= 资料审核通过）、不是任一方向拉黑、展示开关。
 */
final class DiscoverRepository extends IRepository
{
    /** 每页 20 条（docs/01 P01）。 */
    public const PAGE_SIZE = 20;

    /** 多久没活跃就不算「在线」。docs 没写，见 PROGRESS Q0-19。 */
    public const ONLINE_MINUTES = 15;

    /** 「新人」= 注册 7 天内（docs/01 P01）。 */
    public const NEW_USER_DAYS = 7;

    public function __construct(
        protected readonly User $model
    ) {}

    /**
     * @param array<string,mixed> $filters
     * @return array{list: Collection<int,User>, total: int}
     */
    public function paginate(array $filters, int $offset, int $limit): array
    {
        $total = $this->baseQuery($filters)->count();

        /** @var ModelCollection<int,User> $list */
        $list = $this->baseQuery($filters)
            // 在线优先、最近活跃在前（docs/01 P01「推荐」的排序）
            ->orderByRaw('last_active_at IS NULL')
            ->orderByDesc('last_active_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 两处都要用同一套条件，所以每次重新构建一个查询（不要 clone 后复用，容易踩绑定的坑）。
     *
     * @param array<string,mixed> $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $query = $this->model->newQuery();

        // 只返回异性
        $query->where('gender', '!=', (int) ($filters['viewer_gender'] ?? 0));

        // 只返回正常的、有头像的（没头像 = 资料没过审）
        $query->where('status', User::STATUS_NORMAL);
        $query->where('avatar', '!=', '');

        // 任一方向拉黑都不出现
        $exclude = $filters['exclude_ids'] ?? [];
        if ($exclude !== []) {
            $query->whereNotIn('id', $exclude);
        }

        $this->applyTab($query, $filters);
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function applyTab(Builder $query, array $filters): void
    {
        switch ($filters['tab'] ?? 'recommend') {
            case 'new':
                $query->where('created_at', '>=', Carbon::now()->subDays(self::NEW_USER_DAYS)->toDateTimeString());
                break;
            case 'city':
                // 同城：和我一个城市
                $city = (string) ($filters['viewer_city'] ?? '');
                if ($city === '') {
                    // 自己没填城市就没人可推荐，用 0=1 直接给空列表
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('city', $city);
                }
                break;
            case 'recommend':
                // 「推荐」尊重 show_in_recommend 开关（其它 tab 不看这个开关）
                $query->where('show_in_recommend', 1);
                break;
            default:
                // 搜索之类不按 tab 过滤
                break;
        }
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['city'])) {
            $query->where('city', (string) $filters['city']);
        }

        $ageMin = $filters['age_min'] ?? null;
        $ageMax = $filters['age_max'] ?? null;
        if ($ageMin !== null || $ageMax !== null) {
            // 生日越早年龄越大：age_max 对应「不能早于这天」，age_min 对应「不能晚于这天」
            $earliest = Carbon::now()->subYears((int) ($ageMax ?? 60) + 1)->addDay()->toDateString();
            $latest = Carbon::now()->subYears((int) ($ageMin ?? 18))->toDateString();
            $query->whereNotNull('birthday')->whereBetween('birthday', [$earliest, $latest]);
        }

        $purposes = $filters['purposes'] ?? [];
        if ($purposes !== []) {
            $query->whereIn('dating_purpose', $purposes);
        }

        if (! empty($filters['verified'])) {
            // 真人认证
            $query->where('real_name_status', 2);
        }

        if (! empty($filters['complete'])) {
            // 资料完善：有头像（上面已经要求）+ 有自我介绍 + 填了生日
            $query->where('bio', '!=', '')->whereNotNull('birthday');
        }

        if (! empty($filters['online_only'])) {
            $query->where('last_active_at', '>=', Carbon::now()->subMinutes(self::ONLINE_MINUTES)->toDateTimeString());
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $sub) use ($keyword): void {
                $sub->where('nickname', 'like', '%' . $keyword . '%');
                if (ctype_digit($keyword)) {
                    $sub->orWhere('id', (int) $keyword);
                }
            });
        }
    }
}
