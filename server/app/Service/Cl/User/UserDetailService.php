<?php

declare(strict_types=1);

namespace App\Service\Cl\User;

use App\Exception\ClBusinessException;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Repository\Cl\DiscoverRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Repository\Cl\UserPhotoRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Moment\MomentService;
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\Relation\RelationService;
use App\Model\Enums\Cl\BizCode;
use Hyperf\Carbon\Carbon;

/**
 * 他人主页（docs/05 第 4.2 节 `/user/{id}`）。
 *
 * 和 `/user/me` 的区别：**别人看到的永远是审核通过的内容**（待审的新头像/昵称只有本人看得到），
 * 相册也只给已通过的；另外会写一条访客记录（P08）。
 */
final class UserDetailService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPhotoRepository $photoRepository,
        private readonly RelationService $relationService,
        private readonly MessagePricingService $pricingService,
        private readonly LevelConfigRepository $levelConfigRepository,
        private readonly AppConfigService $configService,
        private readonly MomentService $momentService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function detail(User $viewer, int $targetId): array
    {
        $target = $this->requireVisibleUser($targetId);
        $isSelf = $target->id === $viewer->id;
        $isBlocked = ! $isSelf && $this->relationService->blockedEitherWay($viewer->id, $target->id);

        // 看别人的主页算一次访客；自己看自己不算，被拉黑的关系也不算（不然等于确认对方来过）
        if (! $isSelf && ! $isBlocked) {
            $this->relationService->recordVisit($viewer->id, $target->id);
        }

        $data = [
            'id' => $target->id,
            'nickname' => $target->nickname,
            'avatar' => $target->avatar,
            'age' => $target->age(),
            'city' => $target->city,
            'height' => $target->height,
            'job' => $target->job,
            'education' => $target->education,
            'dating_purpose' => $target->dating_purpose,
            'bio' => $target->bio,
            'tags' => $target->tags ?? [],
            'level' => $target->level,
            'online' => $this->isOnline($target),
            'is_new' => $this->isNew($target),
            'verified' => $target->real_name_status === 2,
            // 别人只看得到已通过的相册
            'photos' => $this->approvedPhotos($target->id),
            'is_following' => ! $isSelf && $this->relationService->isFollowing($viewer->id, $target->id),
            'is_mutual' => ! $isSelf && $this->relationService->isMutual($viewer->id, $target->id),
            'is_blocked' => $isBlocked,
        ];

        // 朋友圈：只在没被拉黑时给（拉黑等于互相不可见），预览 3 条
        if (! $isBlocked) {
            $moments = $this->momentService->previewFor($target->id, $viewer->id);
            $data['moment_count'] = $moments['count'];
            $data['latest_moments'] = $moments['latest'];
        }

        // 只有「男看女」才有价格（docs/05 第 4.2 节），被拉黑了也不显示
        if (! $isBlocked && $viewer->isMale() && $target->isFemale()) {
            $data['price'] = $this->priceFor($viewer, $target);
        }

        return $data;
    }

    /**
     * @return array<string,int|string|null>
     */
    private function priceFor(User $viewer, User $target): array
    {
        $level = $this->levelConfigRepository->findByLevel($target->level);
        // 还没聊过（会话表是 P4 的活），所以已发条数按 0 算 —— 页面上的免费提示因此是「还剩 N 条」
        $quote = $this->pricingService->quote($viewer, $target);

        $price = [
            'message_price' => $level instanceof LevelConfig ? (int) $level->message_price : 0,
            'free_hint' => $this->pricingService->freeHint($quote),
        ];

        if ((bool) $this->configService->get('feature.call_enabled', false)) {
            // 开关关着的时候不返回这两项（docs/01 第 4 节）
            $price['voice_price'] = $level instanceof LevelConfig ? (int) $level->voice_price : 0;
            $price['video_price'] = $level instanceof LevelConfig ? (int) $level->video_price : 0;
        }

        return $price;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function approvedPhotos(int $userId): array
    {
        $photos = [];
        foreach ($this->photoRepository->listByUser($userId) as $photo) {
            if ($photo->audit_status !== UserPhoto::AUDIT_APPROVED) {
                continue;
            }
            $photos[] = [
                'id' => (int) $photo->id,
                'url' => (string) $photo->url,
                'sort' => (int) $photo->sort,
            ];
        }

        return $photos;
    }

    private function isOnline(User $user): bool
    {
        return $user->last_active_at !== null
            && $user->last_active_at->greaterThanOrEqualTo(
                Carbon::now()->subMinutes(DiscoverRepository::ONLINE_MINUTES),
            );
    }

    private function isNew(User $user): bool
    {
        return $user->created_at !== null
            && $user->created_at->greaterThanOrEqualTo(
                Carbon::now()->subDays(DiscoverRepository::NEW_USER_DAYS),
            );
    }

    /**
     * 封号 / 已注销 / 不存在都当「对方不存在」，不暴露封禁状态。
     */
    private function requireVisibleUser(int $targetId): User
    {
        $target = $this->userRepository->findById($targetId);
        if (! $target instanceof User || $target->status !== User::STATUS_NORMAL) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        return $target;
    }
}
