<?php

declare(strict_types=1);

namespace App\Service\Cl\Moment;

use App\Exception\ClBusinessException;
use App\Model\Cl\Moment;
use App\Model\Cl\MomentComment;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\MomentCommentRepository;
use App\Repository\Cl\MomentLikeRepository;
use App\Repository\Cl\MomentRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Chat\SensitiveWordService;
use App\Service\Cl\Notice\NoticeService;
use Hyperf\DbConnection\Db;

/**
 * 朋友圈（动态）：浏览、发布、点赞、评论。
 *
 * 三条规则：
 * 1. **所有登录用户都能看**任意正常用户的朋友圈（不需要互关），但双方任一方向拉黑就互相看不到，
 *    拉黑时返回空列表而不是报错 —— 别让对方从"报错"里推断出自己被拉黑了；
 * 2. 点赞/评论在**事务里**同时改行为表和冗余计数，计数不会和真实行数漂移；
 * 3. 自己赞自己、自己评自己不推送互动通知。
 */
final class MomentService
{
    /** 每页条数（docs/05 第 1 节分页约定）。 */
    public const PAGE_SIZE = 10;

    /** 个人主页的「朋友圈」区块展示几条。 */
    public const PREVIEW_SIZE = 3;

    public function __construct(
        private readonly MomentRepository $momentRepository,
        private readonly MomentLikeRepository $likeRepository,
        private readonly MomentCommentRepository $commentRepository,
        private readonly UserRepository $userRepository,
        private readonly BlockRepository $blockRepository,
        private readonly NoticeService $noticeService,
        private readonly SensitiveWordService $sensitiveWordService,
    ) {}

    /**
     * 某个用户的动态列表。目标用户不存在/已封禁 → 抛错；双方拉黑 → 空列表。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function userMoments(User $viewer, int $userId, int $page): array
    {
        $owner = $this->requireUser($userId);
        if ($this->blockedEitherWay($viewer->id, $owner->id)) {
            return ['list' => [], 'total' => 0, 'page' => $page];
        }

        $offset = max(0, ($page - 1) * self::PAGE_SIZE);
        $rows = $this->momentRepository->pageByUser($owner->id, $offset, self::PAGE_SIZE);
        $total = $this->momentRepository->countByUser($owner->id);

        return [
            'list' => $this->present($rows, $viewer),
            'total' => $total,
            'page' => $page,
        ];
    }

    /**
     * 我自己的动态（含别人看不到的？不含 —— 被后台隐藏的自己也不用看了，保持一致）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function mine(User $viewer, int $page): array
    {
        return $this->userMoments($viewer, $viewer->id, $page);
    }

    /**
     * 发布一条动态。
     *
     * @param array<int,string> $images
     * @return array<string,mixed>
     */
    public function publish(User $viewer, string $content, array $images): array
    {
        $content = trim($content);
        if ($content === '' && $images === []) {
            throw new ClBusinessException(BizCode::MOMENT_FORBIDDEN, '说点什么或配张图吧');
        }
        if ($this->sensitiveWordService->hit($content)) {
            throw new ClBusinessException(BizCode::INVALID_MESSAGE_CONTENT);
        }

        $moment = $this->momentRepository->createMoment($viewer->id, $content, array_values($images));

        return $this->item($moment, $viewer, false);
    }

    /** 删除自己的动态（软删，点赞/评论留着，后台可恢复）。 */
    public function remove(User $viewer, int $id): void
    {
        $moment = $this->requireMoment($id);
        if ($moment->user_id !== $viewer->id) {
            throw new ClBusinessException(BizCode::MOMENT_FORBIDDEN);
        }

        $this->momentRepository->softDelete($moment->id);
    }

    /**
     * 点赞（幂等：已经赞过不会重复计数，也不会重复通知）。
     *
     * @return array{liked: bool, like_count: int}
     */
    public function like(User $viewer, int $id): array
    {
        $moment = $this->requireViewable($viewer, $id);

        [$count, $created] = Db::transaction(function () use ($viewer, $moment): array {
            $created = $this->likeRepository->addIfNew($moment->id, $viewer->id);
            $count = $created
                ? $this->momentRepository->incrementLike($moment->id)
                : (int) $moment->like_count;

            return [$count, $created];
        });

        if ($created && $moment->user_id !== $viewer->id) {
            $author = $this->userRepository->findById($moment->user_id);
            if ($author instanceof User) {
                $this->noticeService->momentLiked($author, $viewer, $moment->id);
            }
        }

        return ['liked' => true, 'like_count' => $count];
    }

    /**
     * 取消点赞（没赞过也不会把计数减成负数）。
     *
     * @return array{liked: bool, like_count: int}
     */
    public function unlike(User $viewer, int $id): array
    {
        $moment = $this->requireViewable($viewer, $id);

        $count = Db::transaction(function () use ($viewer, $moment): int {
            $removed = $this->likeRepository->remove($moment->id, $viewer->id);

            return $removed
                ? $this->momentRepository->decrementLike($moment->id)
                : (int) $moment->like_count;
        });

        return ['liked' => false, 'like_count' => $count];
    }

    /**
     * 评论列表（老的在上）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function commentPage(User $viewer, int $id, int $page): array
    {
        $moment = $this->requireViewable($viewer, $id);

        $offset = max(0, ($page - 1) * self::PAGE_SIZE);
        $rows = $this->commentRepository->pageByMoment($moment->id, $offset, self::PAGE_SIZE);
        $total = $this->commentRepository->countByMoment($moment->id);

        return [
            'list' => $this->presentComments($rows, $viewer),
            'total' => $total,
            'page' => $page,
        ];
    }

    /**
     * 发一条评论。
     *
     * @return array<string,mixed>
     */
    public function addComment(User $viewer, int $id, string $content): array
    {
        $moment = $this->requireViewable($viewer, $id);
        $content = trim($content);
        if ($content === '') {
            throw new ClBusinessException(BizCode::MOMENT_FORBIDDEN, '评论不能为空');
        }
        if ($this->sensitiveWordService->hit($content)) {
            throw new ClBusinessException(BizCode::INVALID_MESSAGE_CONTENT);
        }

        $comment = Db::transaction(function () use ($viewer, $moment, $content): MomentComment {
            $comment = $this->commentRepository->createComment($moment->id, $viewer->id, $content);
            $this->momentRepository->incrementComment($moment->id);

            return $comment;
        });

        if ($moment->user_id !== $viewer->id) {
            $author = $this->userRepository->findById($moment->user_id);
            if ($author instanceof User) {
                $this->noticeService->momentCommented($author, $viewer, $moment->id, $content);
            }
        }

        return $this->commentItem($comment, $viewer);
    }

    /**
     * 个人主页用：最近几条 + 总数。
     *
     * @return array{count: int, latest: array<int,array<string,mixed>>}
     */
    public function previewFor(int $ownerId, int $viewerId): array
    {
        if ($this->blockedEitherWay($ownerId, $viewerId)) {
            return ['count' => 0, 'latest' => []];
        }

        $viewer = $this->userRepository->findById($viewerId);
        $rows = $this->momentRepository->latestByUser($ownerId, self::PREVIEW_SIZE);

        return [
            'count' => $this->momentRepository->countByUser($ownerId),
            'latest' => $viewer instanceof User ? $this->present($rows, $viewer) : [],
        ];
    }

    // ---------- 内部 ----------

    /**
     * @param array<int,Moment> $rows
     * @return array<int,array<string,mixed>>
     */
    private function present(array $rows, User $viewer): array
    {
        if ($rows === []) {
            return [];
        }

        $liked = $this->likeRepository->likedAmong(
            $viewer->id,
            array_map(static fn (Moment $m): int => $m->id, $rows),
        );

        $authors = $this->userRepository->listByIds(array_values(array_unique(
            array_map(static fn (Moment $m): int => $m->user_id, $rows),
        )))->keyBy('id');

        $list = [];
        foreach ($rows as $moment) {
            $author = $authors->get($moment->user_id);
            $list[] = $this->item(
                $moment,
                $viewer,
                in_array($moment->id, $liked, true),
                $author instanceof User ? $author : null,
            );
        }

        return $list;
    }

    /**
     * @return array<string,mixed>
     */
    private function item(Moment $moment, User $viewer, bool $liked, ?User $author = null): array
    {
        $author ??= $moment->user_id === $viewer->id ? $viewer : $this->userRepository->findById($moment->user_id);

        return [
            'id' => $moment->id,
            'user_id' => $moment->user_id,
            'nickname' => $author instanceof User ? $author->nickname : '',
            'avatar' => $author instanceof User ? (string) $author->avatar : '',
            'content' => $moment->content,
            'images' => array_values($moment->images ?? []),
            'like_count' => $moment->like_count,
            'comment_count' => $moment->comment_count,
            'is_liked' => $liked,
            'can_delete' => $moment->user_id === $viewer->id,
            'created_at' => $moment->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<int,MomentComment> $rows
     * @return array<int,array<string,mixed>>
     */
    private function presentComments(array $rows, User $viewer): array
    {
        if ($rows === []) {
            return [];
        }

        $users = $this->userRepository->listByIds(array_values(array_unique(
            array_map(static fn (MomentComment $c): int => $c->user_id, $rows),
        )))->keyBy('id');

        $list = [];
        foreach ($rows as $comment) {
            $author = $users->get($comment->user_id);
            $list[] = $this->commentItem($comment, $viewer, $author instanceof User ? $author : null);
        }

        return $list;
    }

    /**
     * @return array<string,mixed>
     */
    private function commentItem(MomentComment $comment, User $viewer, ?User $author = null): array
    {
        $author ??= $comment->user_id === $viewer->id ? $viewer : $this->userRepository->findById($comment->user_id);

        return [
            'id' => $comment->id,
            'user_id' => $comment->user_id,
            'nickname' => $author instanceof User ? $author->nickname : '',
            'avatar' => $author instanceof User ? (string) $author->avatar : '',
            'content' => $comment->content,
            'can_delete' => $comment->user_id === $viewer->id,
            'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
        ];
    }

    private function requireUser(int $userId): User
    {
        $user = $this->userRepository->findById($userId);
        if (! $user instanceof User) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        return $user;
    }

    private function requireMoment(int $id): Moment
    {
        $moment = $this->momentRepository->findVisible($id);
        if (! $moment instanceof Moment) {
            throw new ClBusinessException(BizCode::MOMENT_NOT_FOUND);
        }

        return $moment;
    }

    /** 能看才能赞/评：不存在、被隐藏、双方拉黑都当"不存在"。 */
    private function requireViewable(User $viewer, int $id): Moment
    {
        $moment = $this->requireMoment($id);
        if ($this->blockedEitherWay($viewer->id, $moment->user_id)) {
            throw new ClBusinessException(BizCode::MOMENT_NOT_FOUND);
        }

        return $moment;
    }

    private function blockedEitherWay(int $a, int $b): bool
    {
        return $a !== $b && $this->blockRepository->blockedEitherWay($a, $b);
    }
}
