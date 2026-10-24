<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AiPersona;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\AiPersonaRepository;
use App\Repository\Cl\UserRepository;

/**
 * 后台「AI 代聊」配置：只对女用户开放。
 *
 * 一个女用户一套人设；没配过的人也能在列表里看到（默认关闭），方便运营按需开启。
 */
final class AdminAiChatService
{
    public function __construct(
        private readonly AiPersonaRepository $personaRepository,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * 女用户列表 + 各自的 AI 状态。
     *
     * @param array<string,mixed> $filters keyword / enabled
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = $this->userRepository->getQuery()
            ->where('gender', User::GENDER_FEMALE)
            ->where('status', User::STATUS_NORMAL);

        if (! empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw): void {
                $q->where('nickname', 'like', '%' . $kw . '%')
                    ->orWhere('account', 'like', '%' . $kw . '%')
                    ->orWhere('id', (int) $kw);
            });
        }

        // 「只看已开启」用子查询，避免 join 后分页 total 不准
        if (isset($filters['enabled']) && $filters['enabled'] !== '') {
            $enabled = (int) $filters['enabled'];
            $ids = $this->personaRepository->getQuery()
                ->where('enabled', $enabled)
                ->pluck('user_id')
                ->all();
            $query->whereIn('id', $ids === [] ? [-1] : array_map('intval', $ids));
        }

        $total = (clone $query)->count();
        $users = $query->orderByDesc('id')
            ->offset(max(0, ($page - 1) * $pageSize))
            ->limit($pageSize)
            ->get();

        $personas = $this->personaRepository->getQuery()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->get()
            ->keyBy('user_id');

        $list = [];
        foreach ($users as $user) {
            /** @var null|AiPersona $persona */
            $persona = $personas->get($user->id);
            $list[] = [
                'user_id' => (int) $user->id,
                'nickname' => (string) $user->nickname,
                'account' => (string) $user->account,
                'avatar' => (string) $user->avatar,
                'city' => (string) $user->city,
                'age' => $user->age(),
                'configured' => $persona instanceof AiPersona,
                'enabled' => $persona instanceof AiPersona ? $persona->enabled : 0,
                'proactive' => $persona instanceof AiPersona ? $persona->proactive : 0,
                'active_start' => $persona instanceof AiPersona ? $persona->active_start : 8,
                'active_end' => $persona instanceof AiPersona ? $persona->active_end : 23,
                'daily_limit' => $persona instanceof AiPersona ? $persona->daily_limit : 80,
                'replied_today' => $persona instanceof AiPersona ? $persona->replied_today : 0,
                'total_tokens' => $persona instanceof AiPersona
                    ? $persona->prompt_tokens + $persona->completion_tokens
                    : 0,
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 读一个人设（没配过就返回默认值，前端表单直接能填）。
     *
     * @return array<string,mixed>
     */
    public function detail(int $userId): array
    {
        $user = $this->requireFemale($userId);
        $persona = $this->personaRepository->findByUser($user->id);

        return [
            'user_id' => (int) $user->id,
            'nickname' => (string) $user->nickname,
            'avatar' => (string) $user->avatar,
            'city' => (string) $user->city,
            'job' => (string) $user->job,
            'bio' => (string) $user->bio,
            'enabled' => $persona instanceof AiPersona ? $persona->enabled : 0,
            'persona' => $persona instanceof AiPersona ? $persona->persona : '',
            'tone' => $persona instanceof AiPersona ? $persona->tone : '',
            'taboos' => $persona instanceof AiPersona ? $persona->taboos : '',
            'proactive' => $persona instanceof AiPersona ? $persona->proactive : 0,
            'active_start' => $persona instanceof AiPersona ? $persona->active_start : 8,
            'active_end' => $persona instanceof AiPersona ? $persona->active_end : 23,
            'daily_limit' => $persona instanceof AiPersona ? $persona->daily_limit : 80,
            'replied_today' => $persona instanceof AiPersona ? $persona->replied_today : 0,
            'prompt_tokens' => $persona instanceof AiPersona ? $persona->prompt_tokens : 0,
            'completion_tokens' => $persona instanceof AiPersona ? $persona->completion_tokens : 0,
        ];
    }

    /**
     * 保存人设（没有就建）。
     *
     * @param array<string,mixed> $data
     */
    public function save(int $userId, array $data): void
    {
        $user = $this->requireFemale($userId);

        $values = [
            'enabled' => (int) ($data['enabled'] ?? 0) === 1 ? 1 : 0,
            'persona' => mb_substr(trim((string) ($data['persona'] ?? '')), 0, 1000),
            'tone' => mb_substr(trim((string) ($data['tone'] ?? '')), 0, 300),
            'taboos' => mb_substr(trim((string) ($data['taboos'] ?? '')), 0, 300),
            'proactive' => max(0, min(3, (int) ($data['proactive'] ?? 0))),
            'active_start' => max(0, min(23, (int) ($data['active_start'] ?? 8))),
            'active_end' => max(0, min(23, (int) ($data['active_end'] ?? 23))),
            'daily_limit' => max(1, min(500, (int) ($data['daily_limit'] ?? 80))),
            'status' => AiPersona::STATUS_NORMAL,
        ];

        $persona = $this->personaRepository->findByUser($user->id);
        if ($persona instanceof AiPersona) {
            $this->personaRepository->updateById($persona->id, $values);

            return;
        }

        $this->personaRepository->create(array_merge($values, ['user_id' => $user->id]));
    }

    /** 快速开关（列表里点一下）。 */
    public function toggle(int $userId, bool $enabled): void
    {
        $user = $this->requireFemale($userId);
        $persona = $this->personaRepository->findByUser($user->id);

        if ($persona instanceof AiPersona) {
            $this->personaRepository->updateById($persona->id, ['enabled' => $enabled ? 1 : 0]);

            return;
        }

        // 没配过也能一键开：给一套能直接用的默认人设
        $this->personaRepository->create([
            'user_id' => $user->id,
            'enabled' => $enabled ? 1 : 0,
            'persona' => sprintf(
                '在%s生活的%s，%s。平时喜欢分享日常，聊天比较随意。',
                (string) $user->city,
                (string) $user->job,
                (string) $user->bio,
            ),
            'tone' => '像发微信一样，短句，偶尔带点语气词',
            'taboos' => '政治、血腥恐怖、催婚',
            'proactive' => 0,
            'active_start' => 8,
            'active_end' => 23,
            'daily_limit' => 80,
            'status' => AiPersona::STATUS_NORMAL,
        ]);
    }

    private function requireFemale(int $userId): User
    {
        $user = $this->userRepository->findById($userId);
        if (! $user instanceof User) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }
        if (! $user->isFemale()) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, 'AI 代聊只对女用户开放');
        }

        return $user;
    }
}
