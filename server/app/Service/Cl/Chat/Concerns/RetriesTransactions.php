<?php

declare(strict_types=1);

namespace App\Service\Cl\Chat\Concerns;

use Hyperf\Database\Exception\QueryException;
use Hyperf\DbConnection\Db;

/**
 * 「死锁 / 唯一冲突自动重试一次」的事务包装（发私信和送礼物共用）。
 *
 * 为什么必须重试：两个人同时给同一个人发第一条消息（或送第一个礼物）时，两个协程会同时
 * `insert cl_conversation`，撞上唯一索引/间隙锁，MySQL 会挑一个当事务回滚（1213 死锁）。
 * 重试时对方已经建好了会话，这一轮就能读到它继续走。
 */
trait RetriesTransactions
{
    /** 最多试几次（重试一次基本就能过，留三次兜底）。 */
    private const MAX_TRANSACTION_ATTEMPTS = 3;

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function runTransaction(callable $callback): mixed
    {
        $attempt = 0;

        while (true) {
            ++$attempt;
            try {
                return Db::transaction($callback);
            } catch (QueryException $e) {
                if ($attempt >= self::MAX_TRANSACTION_ATTEMPTS || ! $this->isRetryable($e)) {
                    throw $e;
                }
                // 随机退避一下再试，避免两个协程又同时撞上
                usleep(random_int(1000, 20000));
            }
        }
    }

    private function isRetryable(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Deadlock')
            || str_contains($message, 'Lock wait timeout')
            || str_contains($message, 'Duplicate entry');
    }
}
