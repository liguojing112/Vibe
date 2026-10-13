<?php

declare(strict_types=1);

namespace App\Service\Cl\Task;

use App\Exception\ClBusinessException;
use App\Model\Cl\Task;
use App\Model\Cl\TaskLog;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Model\Enums\Cl\PointLogType;
use App\Repository\Cl\MessageRepository;
use App\Repository\Cl\SignInRepository;
use App\Repository\Cl\TaskLogRepository;
use App\Repository\Cl\TaskRepository;
use App\Repository\Cl\UserPhotoRepository;
use App\Service\Cl\Chat\Concerns\RetriesTransactions;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;

/**
 * 每日任务（docs/06 第 7 节，P11）。
 *
 * **完成条件全部由服务端现算**（`docs/01` P11：App 只负责点「领取」），
 * 因为照片审核、发过消息这些客户端说了不算。
 *
 * 奖励只有一条路发钱：私有的 [grantOnce]。`/task/claim` 和 `/task/sign-in` 都走它，
 * 所以「同一周期只发一次」这条规则只写了一遍（唯一索引兜底）。
 *
 * 领奖**发金币还是积分看人的性别**（男金币、女积分），不是看任务 —— 所以 `cl_task` 里没有单位字段，
 * 同一件事男女各一行配置。
 *
 * 任务奖励积分**不计入**升级用的累计收益（`docs/06` 第 10 节场景 20）：
 * 这里只动钱包，不碰 `cl_user.total_earned_points`（那一列只统计私信和礼物分成）。
 */
final class TaskService
{
    /** 钱包流水的 `ref_type`：`ref_id` 指向 `cl_task_log.id`。 */
    public const REF_TYPE = 'task';

    /** 列表里每个任务的按钮要干什么（客户端据此决定点了之后走哪条路）。 */
    public const ACTION_SIGN_IN = 'sign_in';

    public const ACTION_CLAIM = 'claim';

    public const ACTION_EDIT_PROFILE = 'edit_profile';

    public const ACTION_DISCOVER = 'discover';

    public const ACTION_NONE = 'none';

    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskLogRepository $logRepository,
        private readonly SignInRepository $signInRepository,
        private readonly UserPhotoRepository $photoRepository,
        private readonly MessageRepository $messageRepository,
        private readonly CoinWalletService $coinWalletService,
        private readonly PointWalletService $pointWalletService,
    ) {}

    use RetriesTransactions;

    /**
     * `/task/list`：适用我性别的任务 + 状态 + 进度。
     *
     * @return array{list: array<int,array<string,mixed>>}
     */
    public function list(User $user): array
    {
        $items = [];
        foreach ($this->taskRepository->enabledForGender($user->gender) as $task) {
            $items[] = $this->payload($user, $task);
        }

        return ['list' => $items];
    }

    /**
     * `/task/sign-in`：签到，并把签到任务的奖励一起发了。
     *
     * 一次点击就拿到奖励（设计图 11 上签到卡只有一个「签到」按钮，没有再点一次「领取」）。
     * 重复签到不报错，直接把今天的状态回给客户端 —— 用户连点两下不该看到红灯。
     *
     * @return array<string,mixed>
     */
    public function signIn(User $user): array
    {
        $task = $this->signInTaskOf($user);
        $today = Carbon::now()->toDateString();

        $this->runTransaction(function () use ($user, $task, $today): void {
            // 并发点两次：第二个协程在这里就看见第一条签到记录了
            if (! $this->signInRepository->existsOn($user->id, $today)) {
                $this->signInRepository->create([
                    'user_id' => $user->id,
                    'sign_date' => $today,
                ]);
            }
            $this->grantOnce($user, $task);
        });

        return [
            'signed' => true,
            'reward' => (int) $task->reward,
            'reward_type' => $this->rewardTypeOf($user),
            'reward_text' => $this->rewardText($user, (int) $task->reward),
            'balance' => $this->balanceOf($user),
        ];
    }

    /**
     * `/task/claim`：领奖。**领之前服务端重新判断一次完成条件**
     * （`docs/06` 第 7 节：条件由服务端判断）。
     *
     * @return array<string,mixed>
     */
    public function claim(User $user, int $taskId): array
    {
        $task = $this->taskRepository->findEnabled($taskId);
        if ($task === null || ! $task->appliesTo($user->gender)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '任务不存在或已下架');
        }
        if ($task->type === Task::TYPE_SIGN_IN) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '签到请点「签到」');
        }
        if (! $this->isCompleted($user, $task)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '任务还没完成');
        }

        $log = $this->runTransaction(fn (): ?TaskLog => $this->grantOnce($user, $task));
        if ($log === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '这个任务本期已经领过了');
        }

        return [
            'task_id' => (int) $task->id,
            'reward' => (int) $log->reward,
            'reward_type' => (int) $log->reward_type,
            'reward_text' => $this->rewardText($user, (int) $log->reward),
            'balance' => $this->balanceOf($user),
        ];
    }

    /**
     * 发奖（**只有这一处发钱**）。调用方保证在事务里。
     *
     * 已经领过就返回 null —— 不抛异常，让调用方自己决定是「静默跳过」（签到）
     * 还是「告诉用户今天领过了」（领取）。
     */
    private function grantOnce(User $user, Task $task): ?TaskLog
    {
        $periodKey = $this->periodKeyOf($task);

        if ($this->logRepository->exists($user->id, (int) $task->id, $periodKey)) {
            return null;
        }

        $reward = max(0, (int) $task->reward);
        $rewardType = $this->rewardTypeOf($user);

        $log = $this->logRepository->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'period_key' => $periodKey,
            'reward' => $reward,
            'reward_type' => $rewardType,
        ]);

        if ($reward <= 0) {
            return $log;
        }

        $title = '任务奖励 · ' . $task->name;
        if ($rewardType === TaskLog::REWARD_COIN) {
            $this->coinWalletService->increase(
                $user->id,
                $reward,
                CoinLogType::TASK,
                self::REF_TYPE,
                (int) $log->id,
                $title,
            );
        } else {
            // 注意：这里**不碰 total_earned_points**（任务奖励不算累计收益，场景 20）
            $this->pointWalletService->increase(
                $user->id,
                $reward,
                PointLogType::TASK,
                self::REF_TYPE,
                (int) $log->id,
                $title,
            );
        }

        return $log;
    }

    /**
     * 一个任务在列表里的样子。
     *
     * 按钮文案由服务端拼（和聊天页收费条一个做法），客户端只看 `action` 决定点了干什么。
     *
     * @return array<string,mixed>
     */
    private function payload(User $user, Task $task): array
    {
        $claimed = $this->logRepository->exists($user->id, (int) $task->id, $this->periodKeyOf($task));
        $target = max(1, (int) $task->target);
        $progress = min($this->progressOf($user, $task), $target);
        $completed = $progress >= $target;

        [$status, $buttonText, $action] = match (true) {
            $claimed => ['claimed', '已完成', self::ACTION_NONE],
            // 签到卡片的按钮就是「签到」：点一下既签到又拿奖，没有再点一次「领取」
            $task->type === Task::TYPE_SIGN_IN => ['todo', '签到', self::ACTION_SIGN_IN],
            $completed => ['claimable', '领取', self::ACTION_CLAIM],
            // 「去完成」的两项都跳编辑资料（P14）：传照片和写自我介绍都在那一页
            $task->type === Task::TYPE_PROFILE_PHOTO,
            $task->type === Task::TYPE_PROFILE_BIO => ['todo', '去完成', self::ACTION_EDIT_PROFILE],
            default => ['todo', '去认识', self::ACTION_DISCOVER],
        };

        return [
            'id' => (int) $task->id,
            'name' => (string) $task->name,
            'description' => (string) $task->description,
            'icon' => (string) $task->icon,
            'type' => (string) $task->type,
            'period' => (int) $task->period,
            'target' => $target,
            'progress' => $progress,
            'reward' => (int) $task->reward,
            'reward_type' => $this->rewardTypeOf($user),
            'reward_text' => $this->rewardText($user, (int) $task->reward),
            'status' => $status,
            'button_text' => $buttonText,
            'action' => $action,
        ];
    }

    /** 各类型的完成进度（服务端现算）。 */
    private function progressOf(User $user, Task $task): int
    {
        return match ($task->type) {
            Task::TYPE_SIGN_IN => $this->signInRepository->existsOn($user->id, Carbon::now()->toDateString()) ? 1 : 0,
            Task::TYPE_PROFILE_PHOTO => $this->photoRepository->countApproved($user->id),
            // 自我介绍要审核通过才会写进 cl_user.bio，所以这一列非空就说明通过了
            Task::TYPE_PROFILE_BIO => trim((string) $user->bio) === '' ? 0 : 1,
            Task::TYPE_SEND_MESSAGE => $this->todayPartners($user),
            default => 0,
        };
    }

    private function isCompleted(User $user, Task $task): bool
    {
        return $this->progressOf($user, $task) >= max(1, (int) $task->target);
    }

    /** 今天给多少个不同的人发过私信（自然日按 Asia/Shanghai）。 */
    private function todayPartners(User $user): int
    {
        $start = Carbon::now()->startOfDay();

        return $this->messageRepository->countPartnersBetween(
            $user->id,
            $start->toDateTimeString(),
            $start->copy()->addDay()->toDateTimeString(),
        );
    }

    /** 周期键：每日任务跟着日期走（换一天就换一个 key，天然「第二天重新算」）。 */
    private function periodKeyOf(Task $task): string
    {
        return $task->isDaily() ? Carbon::now()->format('Ymd') : TaskLog::PERIOD_ONCE_KEY;
    }

    private function signInTaskOf(User $user): Task
    {
        foreach ($this->taskRepository->enabledForGender($user->gender) as $task) {
            if ($task->type === Task::TYPE_SIGN_IN) {
                return $task;
            }
        }

        throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '签到任务未开启');
    }

    private function rewardTypeOf(User $user): int
    {
        return $user->isMale() ? TaskLog::REWARD_COIN : TaskLog::REWARD_POINT;
    }

    /** 「+5 金币」/「+20 积分」（设计图 11 的奖励文案）。 */
    private function rewardText(User $user, int $reward): string
    {
        return sprintf('+%d %s', $reward, $user->isMale() ? '金币' : '积分');
    }

    private function balanceOf(User $user): int
    {
        return $user->isMale()
            ? $this->coinWalletService->balanceOf($user->id)
            : (int) $this->pointWalletService->summaryOf($user->id)['balance'];
    }
}
