<?php

declare(strict_types=1);

namespace App\Repository\Cl\Admin;

use App\Model\Cl\User;
use App\Repository\IRepository;
use Hyperf\Database\Model\Builder;

/**
 * 后台用户列表的查询（docs/07 第 3.2 节）。
 *
 * 和 App 侧的 `UserRepository` 分开：那边只按 id / 账号查单个用户，
 * 这边是「带钱包余额和一堆筛选项的分页列表」，两者的关注点不一样。
 *
 * 只查 `cl_user`：**钱包余额不在这里 join** ——
 * 钱包的读写一律走 `CoinWalletService` / `PointWalletService`（`scripts/check-money.sh` 盯着这条），
 * 所以余额由 `AdminUserService` 拿到用户 id 之后再批量补上（多一条查询，但整页一次查完，不是 N+1）。
 *
 * @extends IRepository<User>
 */
final class AdminUserRepository extends IRepository
{
    /** 列表和导出共用同一套筛选项，避免两边的条件写歪。 */
    public const FILTER_KEYS = [
        'id', 'account', 'phone', 'nickname', 'status', 'source', 'batch_no',
        'level', 'real_name_status', 'is_vip', 'created_from', 'created_to',
    ];

    public function __construct(
        protected readonly User $model
    ) {}

    public function pageList(array $filters, int $page, int $pageSize): array
    {
        $total = $this->build($filters)->count();
        $rows = $this->build($filters)
            ->orderByDesc('cl_user.id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        return ['list' => $this->toRows($rows), 'total' => $total];
    }

    /**
     * 导出用：同一套筛选，但不分页。上限由调用方控制（防一次拉爆）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters, int $limit): array
    {
        return $this->toRows(
            $this->build($filters)->orderByDesc('cl_user.id')->limit($limit)->get()
        );
    }

    private function build(array $filters): Builder
    {
        // `@var`：phpstan 认为 `leftJoin` 之后是 Query\Builder，但运行期一直是模型的 Builder
        // （join 出来的行照样当模型取），不写这一行它会拿错的类型去要求下面几个方法
        /** @var Builder $query */
        $query = $this->model->newQuery()
            ->select([
                'cl_user.id', 'cl_user.account', 'cl_user.phone', 'cl_user.nickname', 'cl_user.avatar',
                'cl_user.gender', 'cl_user.city', 'cl_user.status', 'cl_user.source', 'cl_user.batch_no',
                'cl_user.level', 'cl_user.level_locked', 'cl_user.total_earned_points',
                'cl_user.vip_expire_at', 'cl_user.real_name_status',
                'cl_user.ban_until', 'cl_user.ban_reason',
                'cl_user.created_at', 'cl_user.last_active_at',
            ])
            // 男女列表是两个页面，所以 gender 是必传的
            ->where('cl_user.gender', (int) ($filters['gender'] ?? User::GENDER_MALE));

        $this->exact($query, 'cl_user.id', $filters, 'id');
        $this->like($query, 'cl_user.account', $filters, 'account');
        $this->like($query, 'cl_user.phone', $filters, 'phone');
        $this->like($query, 'cl_user.nickname', $filters, 'nickname');
        $this->exact($query, 'cl_user.status', $filters, 'status');
        $this->exact($query, 'cl_user.source', $filters, 'source');
        $this->exact($query, 'cl_user.batch_no', $filters, 'batch_no');
        $this->exact($query, 'cl_user.level', $filters, 'level');
        $this->exact($query, 'cl_user.real_name_status', $filters, 'real_name_status');

        $isVip = $filters['is_vip'] ?? null;
        if ($isVip !== null && $isVip !== '') {
            $now = date('Y-m-d H:i:s');
            if ((int) $isVip === 1) {
                $query->where('cl_user.vip_expire_at', '>', $now);
            } else {
                $query->where(static fn (Builder $q) => $q->whereNull('cl_user.vip_expire_at')->orWhere('cl_user.vip_expire_at', '<=', $now));
            }
        }

        if (! empty($filters['created_from'])) {
            $query->where('cl_user.created_at', '>=', $this->dayStart((string) $filters['created_from']));
        }
        if (! empty($filters['created_to'])) {
            $query->where('cl_user.created_at', '<=', $this->dayEnd((string) $filters['created_to']));
        }

        return $query;
    }

    /** 前端传的是「哪一天」（`2026-10-14`），要比就补成当天的 00:00:00。 */
    private function dayStart(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 00:00:00' : $value;
    }

    /** 「截止到某天」要**包含那一天**，所以补成 23:59:59，不然那天的数据会被整片漏掉。 */
    private function dayEnd(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 23:59:59' : $value;
    }

    /** 只要传了值就按等值筛；空串 / null 当没传（前端清掉搜索框就会传空串）。 */
    private function exact(Builder $query, string $column, array $filters, string $key): void
    {
        $value = $filters[$key] ?? null;
        if ($value === null || $value === '') {
            return;
        }
        $query->where($column, $value);
    }

    private function like(Builder $query, string $column, array $filters, string $key): void
    {
        $value = $filters[$key] ?? null;
        if ($value === null || $value === '') {
            return;
        }
        $query->where($column, 'like', '%' . $value . '%');
    }

    /**
     * 统一成数组再交出去（`$row['id']` 那种写法在模型上能用，但数组最稳）。
     *
     * @return array<int,array<string,mixed>>
     */
    private function toRows(iterable $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            /** @var object $row */
            $list[] = method_exists($row, 'toArray') ? $row->toArray() : (array) $row;
        }

        return $list;
    }
}
