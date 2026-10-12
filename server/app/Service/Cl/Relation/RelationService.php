<?php

declare(strict_types=1);

namespace App\Service\Cl\Relation;

use App\Exception\ClBusinessException;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\FollowRepository;
use App\Repository\Cl\UserRepository;
use App\Repository\Cl\VisitRepository;
use App\Service\Cl\Config\AppConfigService;

/**
 * 关系（docs/01 第 7 节、docs/05 第 4.4 节）：关注、拉黑、访客。
 *
 * 三条规则：
 * 1. 关注是单向的，**双方都关注 = 互关（好友）**；
 * 2. **拉黑后互相不可见** —— 两个人之间任一方向有拉黑，所有关系列表里都互不出现，
 *    关注接口也当对方不存在（不然拉黑等于没用）；
 * 3. 访客**同一人一天只算一条**，重复访问只更新时间（docs/04 第 2 节）。
 */
final class RelationService
{
    /** 关系列表每页条数（docs/05 第 1 节分页约定）。 */
    public const PAGE_SIZE = 20;

    public function __construct(
        private readonly FollowRepository $followRepository,
        private readonly BlockRepository $blockRepository,
        private readonly VisitRepository $visitRepository,
        private readonly UserRepository $userRepository,
        private readonly AppConfigService $configService,
    ) {}

    // ---------- 关注 ----------

    /**
     * 关注。返回关注后的关系快照 —— 页面要立刻知道是不是变成互关了
     * （互关的按钮文案不一样，客户端不该自己猜）。
     *
     * @return array{is_following: bool, is_mutual: bool}
     */
    public function follow(User $user, int $targetId): array
    {
        $this->assertNotSelf($user, $targetId);
        if ($this->blockedEitherWay($user->id, $targetId)) {
            // 拉黑后互相不可见：就当对方不存在，别暴露"你被拉黑了"
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }
        $this->requireUser($targetId);

        $this->followRepository->add($user->id, $targetId);

        return $this->followState($user->id, $targetId);
    }

    /**
     * 取消关注。
     *
     * @return array{is_following: bool, is_mutual: bool}
     */
    public function unfollow(User $user, int $targetId): array
    {
        $this->assertNotSelf($user, $targetId);

        $this->followRepository->remove($user->id, $targetId);

        return $this->followState($user->id, $targetId);
    }

    /**
     * @return array{is_following: bool, is_mutual: bool}
     */
    public function followState(int $userId, int $targetId): array
    {
        $isFollowing = $this->isFollowing($userId, $targetId);

        return [
            'is_following' => $isFollowing,
            'is_mutual' => $isFollowing && $this->isFollowing($targetId, $userId),
        ];
    }

    public function isFollowing(int $userId, int $targetId): bool
    {
        return $this->followRepository->exists($userId, $targetId);
    }

    /** 互关（好友）：两个方向都关注了。 */
    public function isMutual(int $a, int $b): bool
    {
        return $this->followRepository->exists($a, $b) && $this->followRepository->exists($b, $a);
    }

    // ---------- 拉黑 ----------

    public function block(User $user, int $targetId): void
    {
        $this->assertNotSelf($user, $targetId);
        $this->requireUser($targetId);

        $this->blockRepository->add($user->id, $targetId);
    }

    public function unblock(User $user, int $targetId): void
    {
        $this->blockRepository->remove($user->id, $targetId);
    }

    /** 两个人之间有没有任一方向的拉黑。 */
    public function blockedEitherWay(int $a, int $b): bool
    {
        if ($a === $b) {
            return false;
        }

        return $this->blockRepository->blockedEitherWay($a, $b);
    }

    // ---------- 访客 ----------

    /** 访问别人的主页时记一条（访问自己不算，docs/01 第 7 节）。 */
    public function recordVisit(int $visitorId, int $targetId): void
    {
        if ($visitorId === $targetId || $visitorId <= 0 || $targetId <= 0) {
            return;
        }

        $this->visitRepository->record($visitorId, $targetId);
    }

    // ---------- 列表 ----------

    /**
     * 我关注的。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function following(User $user, int $page): array
    {
        $blocked = $this->blockRepository->relatedBlockedIds($user->id);
        $ids = $this->followRepository->followingIds($user->id, $blocked, $this->offset($page), self::PAGE_SIZE);

        return [
            'list' => $this->buildItems($user, $ids, false),
            'total' => $this->followRepository->countFollowing($user->id, $blocked),
            'page' => $page,
        ];
    }

    /** 互关（好友）。 */
    public function friends(User $user, int $page): array
    {
        $blocked = $this->blockRepository->relatedBlockedIds($user->id);
        $ids = $this->followRepository->friendIds($user->id, $blocked, $this->offset($page), self::PAGE_SIZE);

        return [
            'list' => $this->buildItems($user, $ids, false),
            'total' => $this->followRepository->countFriends($user->id, $blocked),
            'page' => $page,
        ];
    }

    /** 喜欢我的人（= 关注了我的人）。非会员（男用户，或女用户且开关关了）拿到打码数据。 */
    public function fans(User $user, int $page): array
    {
        $blocked = $this->blockRepository->relatedBlockedIds($user->id);
        $ids = $this->followRepository->followerIds($user->id, $blocked, $this->offset($page), self::PAGE_SIZE);

        return [
            'list' => $this->buildItems($user, $ids, $this->shouldMask($user)),
            'total' => $this->followRepository->countFollowers($user->id, $blocked),
            'page' => $page,
        ];
    }

    /**
     * 最近访客。**读一次就把未读清零**（docs/05 第 4.4 节）。
     */
    public function visitors(User $user, int $page): array
    {
        $blocked = $this->blockRepository->relatedBlockedIds($user->id);
        $recent = $this->visitRepository->recentVisitors($user->id, $blocked, $this->offset($page), self::PAGE_SIZE);
        $visitorIds = array_keys($recent);

        $list = $this->buildItems($user, $visitorIds, $this->shouldMask($user));
        foreach ($list as $index => $item) {
            $list[$index]['visited_at'] = $recent[$item['id']] ?? null;
        }

        $this->visitRepository->markAllRead($user->id);

        return [
            'list' => $list,
            'total' => $this->visitRepository->countDistinctVisitors($user->id, $blocked),
            'page' => $page,
        ];
    }

    /** 黑名单（我拉黑的人）。 */
    public function blocks(User $user, int $page): array
    {
        $ids = $this->blockRepository->blockedIds($user->id, $this->offset($page), self::PAGE_SIZE);

        return [
            'list' => $this->buildItems($user, $ids, false),
            'total' => $this->blockRepository->countBlocked($user->id),
            'page' => $page,
        ];
    }

    /**
     * `/user/me` 的统计数与未读访客。
     *
     * @return array{following: int, friends: int, fans: int, unread_visitors: int, visitors: int}
     */
    public function stats(User $user): array
    {
        $blocked = $this->blockRepository->relatedBlockedIds($user->id);

        return [
            'following' => $this->followRepository->countFollowing($user->id, $blocked),
            'friends' => $this->followRepository->countFriends($user->id, $blocked),
            'fans' => $this->followRepository->countFollowers($user->id, $blocked),
            'visitors' => $this->visitRepository->countDistinctVisitors($user->id, $blocked),
            'unread_visitors' => $this->visitRepository->countUnread($user->id),
        ];
    }

    /**
     * 非会员能不能看到完整资料：**男用户打码**；女用户由 `vip.female_free_view` 决定（默认 1 = 可看）。
     */
    public function shouldMask(User $viewer): bool
    {
        if ($viewer->isVip()) {
            return false;
        }
        if ($viewer->isFemale()) {
            return ! (bool) $this->configService->get('vip.female_free_view', 1);
        }

        return true;
    }

    // ---------- 内部 ----------

    /**
     * 把 id 列表换成列表项。打码时**昵称只留首字 + `*`**，头像照旧返回但带 `avatar_blur`
     * （模糊交给客户端做，服务端不重算图）。
     *
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    private function buildItems(User $viewer, array $ids, bool $masked): array
    {
        if ($ids === []) {
            return [];
        }

        $users = $this->userRepository->listByIds($ids);
        $iFollowThem = $this->followRepository->followingAmong($viewer->id, $ids);
        $theyFollowMe = $this->followRepository->followedByAmong($viewer->id, $ids);

        $items = [];
        foreach ($ids as $id) {
            $user = $users->get($id);
            if (! $user instanceof User) {
                continue;
            }

            $isFollowing = in_array($id, $iFollowThem, true);
            $items[] = [
                'id' => $id,
                'nickname' => $masked ? $this->maskNickname($user->nickname) : $user->nickname,
                'avatar' => $user->avatar,
                'avatar_blur' => $masked,
                'city' => $user->city,
                'age' => $user->age(),
                'gender' => $user->gender,
                'level' => $user->level,
                'is_following' => $isFollowing,
                'is_mutual' => $isFollowing && in_array($id, $theyFollowMe, true),
            ];
        }

        return $items;
    }

    /** 昵称只留首字 + `*`（docs/05 第 4.4 节）。 */
    private function maskNickname(string $nickname): string
    {
        if ($nickname === '') {
            return '';
        }

        return mb_substr($nickname, 0, 1) . '*';
    }

    private function requireUser(int $targetId): User
    {
        $target = $this->userRepository->findById($targetId);
        if (! $target instanceof User || $target->status === User::STATUS_CANCELLED) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        return $target;
    }

    private function assertNotSelf(User $user, int $targetId): void
    {
        if ($user->id === $targetId) {
            throw new ClBusinessException(
                BizCode::STATUS_NOT_ALLOWED,
                '不能对自己做这个操作',
            );
        }
    }

    private function offset(int $page): int
    {
        return max(0, ($page - 1) * self::PAGE_SIZE);
    }
}
