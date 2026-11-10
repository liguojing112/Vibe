<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AiPersona;
use App\Model\Cl\BotChatLog;
use App\Model\Cl\BotChatPair;
use App\Model\Cl\BotChatRule;
use App\Model\Cl\BotChatTarget;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\AiPersonaRepository;
use App\Repository\Cl\BotChatLogRepository;
use App\Repository\Cl\BotChatRuleRepository;
use App\Repository\Cl\BotChatTargetRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Ai\BotChatService;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\DbConnection\Db;

/**
 * 后台「AI 互聊」：机器人男号给指定女号发消息的规则管理。
 *
 * 规则存的是「谁发给谁 + 节奏 + 上限」，真正的发送由 `BotChatCrontab` 每轮驱动。
 * 这里只负责增删改查和把男号人设写进 `cl_ai_persona`。
 */
final class AdminBotChatService
{
    /** 单条规则最多指定多少个男号/女号（防止一次配置把任务拖死）。 */
    private const MAX_MALES = 500;

    private const MAX_FEMALES = 200;

    public function __construct(
        private readonly BotChatRuleRepository $ruleRepository,
        private readonly BotChatTargetRepository $targetRepository,
        private readonly BotChatLogRepository $logRepository,
        private readonly AiPersonaRepository $personaRepository,
        private readonly UserRepository $userRepository,
        private readonly AppConfigService $configService,
        private readonly BotChatService $botChatService,
    ) {}

    /** 全局总开关：关掉后定时任务一条都不发，规则本身不变。 */
    public function globalEnabled(): bool
    {
        return (bool) $this->configService->get('bot_chat.enabled', false);
    }

    public function setGlobalEnabled(bool $enabled): void
    {
        $this->configService->set('bot_chat.enabled', $enabled ? '1' : '0');
    }

    /**
     * @param array<string,mixed> $filters keyword / enabled
     * @return array{list: array<int,array<string,mixed>>, total: int, global_enabled: bool}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = $this->ruleRepository->getQuery();

        if (! empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where('name', 'like', '%' . $kw . '%');
        }
        if (isset($filters['enabled']) && $filters['enabled'] !== '') {
            $query->where('enabled', (int) $filters['enabled']);
        }

        $total = (clone $query)->count();
        $rules = $query->orderByDesc('id')
            ->offset(max(0, ($page - 1) * $pageSize))
            ->limit($pageSize)
            ->get();

        $list = [];
        foreach ($rules as $rule) {
            /** @var BotChatRule $rule */
            $targets = $this->targetRepository->getQuery()->where('rule_id', $rule->id)->get();
            $today = date('Y-m-d');
            $pointsToday = 0;
            foreach ($targets as $target) {
                /** @var BotChatTarget $target */
                if ((string) $target->stat_date === $today) {
                    $pointsToday += (int) $target->virtual_points_today;
                }
            }

            // 15 元上限是按「男号 + 女号」一对算的，所以这里数的是**到顶的配对**，
            // 不是「被停掉的女号」（女号本身不会因为某个男号到顶就整体停掉）。
            $limitPoints = $this->botChatService->limitPoints($rule);
            $limitReached = (int) BotChatPair::query()
                ->where('rule_id', $rule->id)
                ->where('stat_date', $today)
                ->where('virtual_points_today', '>=', $limitPoints)
                ->count();

            $list[] = [
                'id' => (int) $rule->id,
                'name' => (string) $rule->name,
                'enabled' => (int) $rule->enabled,
                'auto_send' => (int) $rule->auto_send,
                'male_count' => count($rule->manualMaleIds()) + (int) $rule->pool_male_count,
                'target_count' => $targets->count(),
                'round_limit_per_pair' => (int) $rule->round_limit_per_pair,
                'male_daily_limit' => (int) $rule->male_daily_limit,
                'interval_seconds' => (int) $rule->interval_seconds,
                'session_gap_seconds' => (int) $rule->session_gap_seconds,
                'window_start' => (int) $rule->window_start,
                'window_end' => (int) $rule->window_end,
                'daily_amount_limit_fen' => (int) $rule->daily_amount_limit_fen,
                'points_today' => $pointsToday,
                'amount_today_fen' => $this->fenOf($pointsToday),
                'limit_reached_count' => $limitReached,
                'created_at' => $rule->created_at?->toDateTimeString(),
            ];
        }

        return ['list' => $list, 'total' => $total, 'global_enabled' => $this->globalEnabled()];
    }

    /**
     * @return array<string,mixed>
     */
    public function detail(int $id): array
    {
        $rule = $this->requireRule($id);
        $targets = $this->targetRepository->getQuery()
            ->where('rule_id', $rule->id)
            ->orderBy('id')
            ->get();

        $males = $this->maleList($rule->manualMaleIds());
        $females = [];
        $today = date('Y-m-d');
        foreach ($targets as $target) {
            /** @var BotChatTarget $target */
            $user = $this->userRepository->findById($target->female_id);
            $females[] = [
                'user_id' => (int) $target->female_id,
                'nickname' => $user instanceof User ? (string) $user->nickname : '—',
                'account' => $user instanceof User ? (string) $user->account : '',
                'avatar' => $user instanceof User ? (string) $user->avatar : '',
                'enabled' => (int) $target->enabled,
                'virtual_points_today' => (string) $target->stat_date === $today ? (int) $target->virtual_points_today : 0,
                'amount_today_fen' => $this->fenOf((string) $target->stat_date === $today ? (int) $target->virtual_points_today : 0),
                'rounds_today' => (string) $target->stat_date === $today ? (int) $target->rounds_today : 0,
                'pairs_limit_reached' => $this->pairsAtLimit($rule, (int) $target->female_id, $today),
                'last_sent_at' => $target->last_sent_at?->toDateTimeString(),
            ];
        }

        return [
            'id' => (int) $rule->id,
            'name' => (string) $rule->name,
            'enabled' => (int) $rule->enabled,
            'auto_send' => (int) $rule->auto_send,
            'male_user_ids' => $rule->manualMaleIds(),
            'pool_male_count' => (int) $rule->pool_male_count,
            'window_start' => (int) $rule->window_start,
            'window_end' => (int) $rule->window_end,
            'round_limit_per_pair' => (int) $rule->round_limit_per_pair,
            'male_daily_limit' => (int) $rule->male_daily_limit,
            'interval_seconds' => (int) $rule->interval_seconds,
            'session_gap_seconds' => (int) $rule->session_gap_seconds,
            'daily_amount_limit_fen' => (int) $rule->daily_amount_limit_fen,
            'context_messages' => (int) $rule->context_messages,
            'males' => $males,
            'females' => $females,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function save(?int $id, array $data, ?int $operatorId): int
    {
        $maleIds = $this->normalizeMaleIds($data['male_user_ids'] ?? []);
        $femaleIds = $this->normalizeFemaleIds($data['female_user_ids'] ?? []);

        if ($maleIds === [] && (int) ($data['pool_male_count'] ?? 0) <= 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '至少要指定一个男号，或设置从号池补足数量');
        }
        if ($femaleIds === []) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '至少要指定一个目标女号');
        }

        $values = [
            'name' => mb_substr(trim((string) ($data['name'] ?? '')), 0, 64),
            'enabled' => (int) ($data['enabled'] ?? 0) === 1 ? 1 : 0,
            'auto_send' => (int) ($data['auto_send'] ?? BotChatRule::AUTO_SEND_ON) === 1
                ? BotChatRule::AUTO_SEND_ON
                : BotChatRule::AUTO_SEND_OFF,
            'male_user_ids' => $maleIds,
            'pool_male_count' => max(0, min(self::MAX_MALES, (int) ($data['pool_male_count'] ?? 0))),
            'window_start' => max(0, min(23, (int) ($data['window_start'] ?? 0))),
            'window_end' => max(0, min(23, (int) ($data['window_end'] ?? 0))),
            'round_limit_per_pair' => max(1, min(2000, (int) ($data['round_limit_per_pair'] ?? 20))),
            'male_daily_limit' => max(0, min(100000, (int) ($data['male_daily_limit'] ?? 0))),
            'interval_seconds' => max(0, min(86400, (int) ($data['interval_seconds'] ?? 30))),
            'session_gap_seconds' => max(60, min(86400, (int) ($data['session_gap_seconds'] ?? 300))),
            'daily_amount_limit_fen' => max(1, min(1000000, (int) ($data['daily_amount_limit_fen'] ?? 1500))),
            'context_messages' => max(2, min(50, (int) ($data['context_messages'] ?? 10))),
        ];

        if ($values['name'] === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '规则名称不能为空');
        }

        $ruleId = Db::transaction(function () use ($id, $values, $femaleIds, $data, $operatorId): int {
            if ($id !== null) {
                $this->ruleRepository->updateById($id, $values);
                $ruleId = $id;
            } else {
                /** @var BotChatRule $created */
                $created = $this->ruleRepository->create($values + ['operator_id' => $operatorId]);
                $ruleId = (int) $created->id;
            }

            $this->syncTargets($ruleId, $femaleIds);
            $this->syncMalePersonas($values['male_user_ids'], $data['male_personas'] ?? []);

            return $ruleId;
        });

        return $ruleId;
    }

    /**
     * 后台点「立即发送」：让这条规则马上给目标女号发一轮。
     *
     * 不受活跃时段和自动发送开关限制（这是人在按按钮），
     * 但轮数、间隔、每日上限照旧生效 —— 这个按钮不能变成绕开限流的后门。
     *
     * @return array{sent: bool, message: null|string, reason: null|string}
     */
    public function sendNow(int $id): array
    {
        $rule = $this->requireRule($id);

        return $this->botChatService->sendNow($rule, date('Y-m-d'));
    }

    /** 启动 / 暂停。全局开关没开时也能设置，但定时任务不会跑。 */
    public function toggle(int $id, bool $enabled): void
    {
        $this->requireRule($id);
        $this->ruleRepository->updateById($id, ['enabled' => $enabled ? 1 : 0]);
    }

    public function delete(int $id): void
    {
        $this->requireRule($id);
        Db::transaction(function () use ($id): void {
            $this->targetRepository->getQuery()->where('rule_id', $id)->delete();
            $this->ruleRepository->deleteById($id);
        });
    }

    /**
     * 目标女号明细（后台抽屉）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function targets(int $ruleId, int $page, int $pageSize): array
    {
        $rule = $this->requireRule($ruleId);
        $today = date('Y-m-d');

        $query = $this->targetRepository->getQuery()->where('rule_id', $ruleId);
        $total = (clone $query)->count();
        $rows = $query->orderBy('id')->offset(max(0, ($page - 1) * $pageSize))->limit($pageSize)->get();

        $list = [];
        foreach ($rows as $row) {
            /** @var BotChatTarget $row */
            $user = $this->userRepository->findById($row->female_id);
            $isToday = (string) $row->stat_date === $today;
            $list[] = [
                'user_id' => (int) $row->female_id,
                'nickname' => $user instanceof User ? (string) $user->nickname : '—',
                'account' => $user instanceof User ? (string) $user->account : '',
                'avatar' => $user instanceof User ? (string) $user->avatar : '',
                'virtual_points_today' => $isToday ? (int) $row->virtual_points_today : 0,
                'amount_today_fen' => $this->fenOf($isToday ? (int) $row->virtual_points_today : 0),
                'rounds_today' => $isToday ? (int) $row->rounds_today : 0,
                'pairs_limit_reached' => $this->pairsAtLimit($rule, (int) $row->female_id, $today),
                'last_sent_at' => $row->last_sent_at?->toDateTimeString(),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 发送记录。
     *
     * @param array<string,mixed> $filters female_id / status / date_from / date_to
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function logs(int $ruleId, array $filters, int $page, int $pageSize): array
    {
        $this->requireRule($ruleId);

        $query = $this->logRepository->getQuery()->where('rule_id', $ruleId);

        if (! empty($filters['female_id'])) {
            $query->where('female_id', (int) $filters['female_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', (string) $filters['date_from'] . ' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', (string) $filters['date_to'] . ' 23:59:59');
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->offset(max(0, ($page - 1) * $pageSize))->limit($pageSize)->get();

        $userIds = [];
        foreach ($rows as $row) {
            $userIds[] = (int) $row->male_id;
            $userIds[] = (int) $row->female_id;
        }
        $users = [];
        if ($userIds !== []) {
            foreach ($this->userRepository->getQuery()->whereIn('id', array_unique($userIds))->get() as $u) {
                /** @var User $u */
                $users[(int) $u->id] = $u;
            }
        }

        $messages = [];
        $messageIds = array_values(array_filter(array_map(static fn ($r): int => (int) $r->message_id, $rows->all())));
        if ($messageIds !== []) {
            foreach (Db::table('cl_message')->whereIn('id', $messageIds)->get() as $m) {
                $messages[(int) $m->id] = (string) $m->content;
            }
        }

        $list = [];
        foreach ($rows as $row) {
            /** @var BotChatLog $row */
            $male = $users[(int) $row->male_id] ?? null;
            $female = $users[(int) $row->female_id] ?? null;
            $list[] = [
                'id' => (int) $row->id,
                'male_id' => (int) $row->male_id,
                'male_nickname' => $male instanceof User ? (string) $male->nickname : '—',
                'male_avatar' => $male instanceof User ? (string) $male->avatar : '',
                'female_id' => (int) $row->female_id,
                'female_nickname' => $female instanceof User ? (string) $female->nickname : '—',
                'female_avatar' => $female instanceof User ? (string) $female->avatar : '',
                'content' => mb_substr($messages[(int) $row->message_id] ?? '', 0, 60),
                'role' => (string) $row->role,
                'virtual_points' => (int) $row->virtual_points,
                'status' => (int) $row->status,
                'fail_reason' => $row->fail_reason,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    /** 后台搜索男号（下拉用）。 */
    public function searchMales(string $keyword, int $limit = 20): array
    {
        return $this->searchUsers($keyword, User::GENDER_MALE, $limit);
    }

    /** 后台搜索女号（下拉用）。 */
    public function searchFemales(string $keyword, int $limit = 20): array
    {
        return $this->searchUsers($keyword, User::GENDER_FEMALE, $limit);
    }

    // ---------- 内部 ----------

    private function searchUsers(string $keyword, int $gender, int $limit): array
    {
        $query = $this->userRepository->getQuery()
            ->where('gender', $gender)
            ->where('status', User::STATUS_NORMAL);

        $kw = trim($keyword);
        if ($kw !== '') {
            $query->where(function ($q) use ($kw): void {
                $q->where('nickname', 'like', '%' . $kw . '%')
                    ->orWhere('account', 'like', '%' . $kw . '%');
                if (ctype_digit($kw)) {
                    $q->orWhere('id', (int) $kw);
                }
            });
        }

        $rows = $query->orderByDesc('id')->limit($limit)->get();

        $list = [];
        foreach ($rows as $user) {
            /** @var User $user */
            $list[] = [
                'user_id' => (int) $user->id,
                'nickname' => (string) $user->nickname,
                'account' => (string) $user->account,
                'avatar' => (string) $user->avatar,
                'city' => (string) $user->city,
            ];
        }

        return $list;
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    private function maleList(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->userRepository->getQuery()->whereIn('id', $ids)->get();
        $byId = [];
        foreach ($rows as $user) {
            /** @var User $user */
            $byId[(int) $user->id] = $user;
        }

        $personas = $this->personaRepository->getQuery()->whereIn('user_id', $ids)->get()->keyBy('user_id');

        $list = [];
        foreach ($ids as $id) {
            $user = $byId[$id] ?? null;
            if (! $user instanceof User) {
                continue;
            }
            /** @var null|AiPersona $persona */
            $persona = $personas->get($id);
            $list[] = [
                'user_id' => $id,
                'nickname' => (string) $user->nickname,
                'account' => (string) $user->account,
                'avatar' => (string) $user->avatar,
                'persona' => $persona instanceof AiPersona ? (string) $persona->persona : '',
                'tone' => $persona instanceof AiPersona ? (string) $persona->tone : '',
                'taboos' => $persona instanceof AiPersona ? (string) $persona->taboos : '',
                'examples' => $persona instanceof AiPersona ? (string) ($persona->examples ?? '') : '',
            ];
        }

        return $list;
    }

    /** @param array<int,int> $ids */
    private function syncTargets(int $ruleId, array $ids): void
    {
        $existing = $this->targetRepository->getQuery()
            ->where('rule_id', $ruleId)
            ->pluck('female_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $toAdd = array_values(array_diff($ids, $existing));
        $toRemove = array_values(array_diff($existing, $ids));

        if ($toRemove !== []) {
            $this->targetRepository->getQuery()
                ->where('rule_id', $ruleId)
                ->whereIn('female_id', $toRemove)
                ->delete();
        }

        foreach ($toAdd as $femaleId) {
            $this->targetRepository->create([
                'rule_id' => $ruleId,
                'female_id' => $femaleId,
                'enabled' => 1,
                'stat_date' => null,
                'virtual_points_today' => 0,
                'rounds_today' => 0,
            ]);
        }
    }

    /**
     * 男号人设：有就更新，没有就建。只写人设字段，不动女号的 AI 代聊配置。
     *
     * @param array<int,int> $maleIds
     * @param array<int,array<string,string>> $personas userId => {persona, tone, taboos, examples}
     */
    private function syncMalePersonas(array $maleIds, array $personas): void
    {
        foreach ($maleIds as $userId) {
            $input = $personas[$userId] ?? $personas[(string) $userId] ?? null;
            if (! is_array($input)) {
                continue;
            }

            $values = [
                'persona' => mb_substr(trim((string) ($input['persona'] ?? '')), 0, 1000),
                'tone' => mb_substr(trim((string) ($input['tone'] ?? '')), 0, 300),
                'taboos' => mb_substr(trim((string) ($input['taboos'] ?? '')), 0, 300),
                'examples' => mb_substr(trim((string) ($input['examples'] ?? '')), 0, 2000),
            ];

            $persona = $this->personaRepository->findByUser($userId);
            if ($persona instanceof AiPersona) {
                $this->personaRepository->updateById($persona->id, $values);

                continue;
            }

            // 男号新建人设：`enabled` 保持 0，避免被 `AiProactiveCrontab` 当成女号主动搭话
            $this->personaRepository->create($values + [
                'user_id' => $userId,
                'enabled' => 0,
                'proactive' => AiPersona::PROACTIVE_OFF,
                'active_start' => 8,
                'active_end' => 23,
                'daily_limit' => 80,
                'status' => AiPersona::STATUS_NORMAL,
            ]);
        }
    }

    /**
     * @param mixed $ids
     * @return array<int,int>
     */
    private function normalizeMaleIds(mixed $ids): array
    {
        $list = is_array($ids) ? $ids : [];
        $clean = array_values(array_unique(array_filter(array_map('intval', $list), static fn (int $id): bool => $id > 0)));

        if (count($clean) > self::MAX_MALES) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, sprintf('一条规则最多指定 %d 个男号', self::MAX_MALES));
        }

        if ($clean === []) {
            return [];
        }

        $map = [];
        foreach ($this->userRepository->getQuery()->whereIn('id', $clean)->get() as $user) {
            /** @var User $user */
            $map[(int) $user->id] = $user;
        }

        foreach ($clean as $id) {
            $user = $map[$id] ?? null;
            if (! $user instanceof User) {
                throw new ClBusinessException(BizCode::USER_NOT_FOUND, sprintf('男号 %d 不存在', $id));
            }
            if (! $user->isMale()) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, sprintf('%s 不是男号', $user->nickname));
            }
            if ($user->status !== User::STATUS_NORMAL) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, sprintf('%s 账号状态异常', $user->nickname));
            }
        }

        return $clean;
    }

    /**
     * @param mixed $ids
     * @return array<int,int>
     */
    private function normalizeFemaleIds(mixed $ids): array
    {
        $list = is_array($ids) ? $ids : [];
        $clean = array_values(array_unique(array_filter(array_map('intval', $list), static fn (int $id): bool => $id > 0)));

        if (count($clean) > self::MAX_FEMALES) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, sprintf('一条规则最多指定 %d 个女号', self::MAX_FEMALES));
        }

        if ($clean === []) {
            return [];
        }

        $map = [];
        foreach ($this->userRepository->getQuery()->whereIn('id', $clean)->get() as $user) {
            /** @var User $user */
            $map[(int) $user->id] = $user;
        }

        foreach ($clean as $id) {
            $user = $map[$id] ?? null;
            if (! $user instanceof User) {
                throw new ClBusinessException(BizCode::USER_NOT_FOUND, sprintf('女号 %d 不存在', $id));
            }
            if (! $user->isFemale()) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, sprintf('%s 不是女号', $user->nickname));
            }
            if ($user->status !== User::STATUS_NORMAL) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, sprintf('%s 账号状态异常', $user->nickname));
            }
        }

        return $clean;
    }

    /** 这个女号今天有几个男号已经聊到 15 元上限（到顶的配对数量）。 */
    private function pairsAtLimit(BotChatRule $rule, int $femaleId, string $today): int
    {
        return (int) BotChatPair::query()
            ->where('rule_id', $rule->id)
            ->where('female_id', $femaleId)
            ->where('stat_date', $today)
            ->where('virtual_points_today', '>=', $this->botChatService->limitPoints($rule))
            ->count();
    }

    /** 积分 → 分（按 point.per_yuan 换算：100 积分 = 1 元 = 100 分）。 */
    private function fenOf(int $points): int
    {
        $pointPerYuan = max(1, (int) $this->configService->get('point.per_yuan', 100));

        return intdiv($points * 100, $pointPerYuan);
    }

    private function requireRule(int $id): BotChatRule
    {
        $rule = $this->ruleRepository->findById($id);
        if (! $rule instanceof BotChatRule) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '规则不存在');
        }

        return $rule;
    }
}
