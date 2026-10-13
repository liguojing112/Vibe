<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\User;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<User>
 */
final class UserRepository extends IRepository
{
    public function __construct(
        protected readonly User $model
    ) {}

    public function findByAccount(string $account): ?User
    {
        return $this->model->newQuery()->where('account', $account)->first();
    }

    public function findByPhone(string $phone): ?User
    {
        return $this->model->newQuery()->where('phone', $phone)->first();
    }

    /**
     * 登录用：账号或手机号都能登录（docs/05 第 4.1 节）。
     */
    public function findByAccountOrPhone(string $value): ?User
    {
        return $this->model->newQuery()
            ->where('account', $value)
            ->orWhere('phone', $value)
            ->first();
    }

    /**
     * @return null|User
     */
    public function findById(mixed $id): mixed
    {
        return $this->model->newQuery()->whereKey($id)->first();
    }

    /**
     * 事务里锁住用户行再读（开会员续费时要）。
     *
     * `vip_expire_at` 是这一行上的字段：两个并发续费请求都不锁的话，两边都读到同一个旧的到期时间、
     * 各自加一次天数，后提交的会把先提交的覆盖掉 —— 用户付了两次钱只拿到一次天数。
     */
    public function lockById(int $id): ?User
    {
        /** @var null|User $user */
        $user = $this->model->newQuery()->whereKey($id)->lockForUpdate()->first();

        return $user;
    }

    /**
     * 按 id 批量取用户（关系列表把 id 换成资料用），返回以 id 为键的集合。
     *
     * @param array<int,int> $ids
     * @return Collection<int,User>
     */
    public function listByIds(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection([]);
        }

        /** @var Collection<int,User> $users */
        $users = $this->model->newQuery()->whereIn('id', $ids)->get();

        return $users->keyBy('id');
    }
}
