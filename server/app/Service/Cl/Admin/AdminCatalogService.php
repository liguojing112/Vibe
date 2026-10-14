<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\Task;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\CityRepository;
use App\Repository\Cl\GiftRepository;
use App\Repository\IRepository;
use App\Repository\Cl\TagRepository;
use App\Repository\Cl\TaskRepository;
use App\Repository\Cl\VipPlanRepository;

/**
 * 后台「运营配置」里的几张目录表（docs/07 第 3.17、3.22 节）：
 * 礼物、会员套餐、每日任务、兴趣标签、城市。
 *
 * 这五张表是**同一个形状**（名字 + 几个业务字段 + 排序 + 启停），所以增删改走同一条路，
 * 只有字段白名单和校验各不相同 —— 各写一份「校验 + 落库」的话，加一个字段要改五处。
 *
 * 删是**真删**（这几张表没有被外键引用：历史单据自己存了名字），
 * 但**停用比删除安全**：停用只是不让新用户选，历史记录不受影响。
 */
final class AdminCatalogService
{
    /** 任务的类型只能是 `06` 第 7 节列的这四种。 */
    private const TASK_TYPES = [
        Task::TYPE_SIGN_IN,
        Task::TYPE_PROFILE_PHOTO,
        Task::TYPE_PROFILE_BIO,
        Task::TYPE_SEND_MESSAGE,
    ];

    public function __construct(
        private readonly GiftRepository $giftRepository,
        private readonly VipPlanRepository $vipPlanRepository,
        private readonly TaskRepository $taskRepository,
        private readonly TagRepository $tagRepository,
        private readonly CityRepository $cityRepository,
    ) {}

    // ---------- 礼物（P3.17）----------

    public function gifts(): array
    {
        return $this->giftRepository->adminList();
    }

    public function saveGift(AdminUser $operator, ?int $id, array $data): void
    {
        $this->saveRow($this->giftRepository, $id, [
            'name' => $this->requireText($data, 'name', '礼物名称'),
            // 图标可以是 emoji，也可以是上传后的图片地址
            'icon' => $this->requireText($data, 'icon', '礼物图标'),
            'price_coins' => $this->intField($data, 'price_coins', '价格', 0),
            'sort' => $this->intField($data, 'sort', '排序', 0),
            'enabled' => $this->boolField($data, 'enabled'),
        ]);
    }

    public function removeGifts(array $ids): int
    {
        return $this->giftRepository->removeByIds($ids);
    }

    // ---------- 会员套餐（P3.17）----------

    public function vipPlans(): array
    {
        return $this->vipPlanRepository->adminList();
    }

    public function saveVipPlan(AdminUser $operator, ?int $id, array $data): void
    {
        $days = $this->intField($data, 'days', '天数', 1);
        if ($days <= 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '会员天数必须大于 0');
        }

        $this->saveRow($this->vipPlanRepository, $id, [
            'name' => $this->requireText($data, 'name', '套餐名称'),
            'days' => $days,
            'price_coins' => $this->intField($data, 'price_coins', '价格', 0),
            'sort' => $this->intField($data, 'sort', '排序', 0),
            'enabled' => $this->boolField($data, 'enabled'),
        ]);
    }

    public function removeVipPlans(array $ids): int
    {
        return $this->vipPlanRepository->removeByIds($ids);
    }

    // ---------- 每日任务（P3.17）----------

    public function tasks(): array
    {
        return $this->taskRepository->adminList();
    }

    public function saveTask(AdminUser $operator, ?int $id, array $data): void
    {
        $type = (string) ($data['type'] ?? '');
        if (! in_array($type, self::TASK_TYPES, true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '任务类型不在支持范围内');
        }

        $gender = (int) ($data['gender'] ?? 0);
        if (! in_array($gender, [1, 2], true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '任务只能是男号或女号');
        }

        $this->saveRow($this->taskRepository, $id, [
            'name' => $this->requireText($data, 'name', '任务名称'),
            'description' => (string) ($data['description'] ?? ''),
            'icon' => (string) ($data['icon'] ?? ''),
            'type' => $type,
            // 目标次数 / 奖励：女号发积分、男号发金币（docs/06 第 7 节）
            'target' => $this->intField($data, 'target', '目标次数', 1),
            'reward' => $this->intField($data, 'reward', '奖励', 0),
            'gender' => $gender,
            'period' => (int) ($data['period'] ?? 1) === 2 ? 2 : 1,
            'sort' => $this->intField($data, 'sort', '排序', 0),
            'enabled' => $this->boolField($data, 'enabled'),
        ]);
    }

    public function removeTasks(array $ids): int
    {
        return $this->taskRepository->removeByIds($ids);
    }

    // ---------- 兴趣标签 / 城市（P3.22）----------

    public function tags(): array
    {
        return $this->tagRepository->adminList();
    }

    public function saveTag(AdminUser $operator, ?int $id, array $data): void
    {
        $this->saveRow($this->tagRepository, $id, [
            'name' => $this->requireText($data, 'name', '标签名'),
            'sort' => $this->intField($data, 'sort', '排序', 0),
            'enabled' => $this->boolField($data, 'enabled'),
        ]);
    }

    public function removeTags(array $ids): int
    {
        return $this->tagRepository->removeByIds($ids);
    }

    public function cities(): array
    {
        return $this->cityRepository->adminList();
    }

    public function saveCity(AdminUser $operator, ?int $id, array $data): void
    {
        $this->saveRow($this->cityRepository, $id, [
            'name' => $this->requireText($data, 'name', '城市名'),
            'sort' => $this->intField($data, 'sort', '排序', 0),
            'enabled' => $this->boolField($data, 'enabled'),
        ]);
    }

    public function removeCities(array $ids): int
    {
        return $this->cityRepository->removeByIds($ids);
    }

    // ---------- 内部 ----------

    /** 传了 id 就是改、没传就是新增（五张表共用这一条路）。 */
    private function saveRow(IRepository $repository, ?int $id, array $data): void
    {
        if ($id === null) {
            $repository->create($data);

            return;
        }

        $repository->saveById($id, $data);
    }

    private function requireText(array $data, string $field, string $label): string
    {
        $value = trim((string) ($data[$field] ?? ''));
        if ($value === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $label . '不能为空');
        }
        if (mb_strlen($value) > 64) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $label . '最长 64 个字');
        }

        return $value;
    }

    private function intField(array $data, string $field, string $label, int $min): int
    {
        $value = (int) ($data[$field] ?? 0);
        if ($value < $min) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $label . '不能小于 ' . $min);
        }

        return $value;
    }

    private function boolField(array $data, string $field): int
    {
        $value = $data[$field] ?? 1;

        return $value === false || $value === 0 || $value === '0' ? 0 : 1;
    }
}
