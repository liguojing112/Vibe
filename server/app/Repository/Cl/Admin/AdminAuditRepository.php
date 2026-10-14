<?php

declare(strict_types=1);

namespace App\Repository\Cl\Admin;

use App\Model\Cl\ProfileAudit;
use App\Model\Cl\RealName;
use Hyperf\Database\Model\Builder;

/**
 * 后台审核中心的查询（docs/07 第 3.4、3.5 节）。
 *
 * 资料审核读 `cl_profile_audit`、实名审核读 `cl_real_name`，两条都要带上用户是谁，
 * 所以都 join `cl_user` 拿昵称头像（不是钱包表，不违反 `check-money.sh` 那条规矩）。
 */
final class AdminAuditRepository
{
    public function __construct(
        private readonly ProfileAudit $auditModel,
        private readonly RealName $realNameModel,
    ) {}

    /**
     * 资料审核列表。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function profilePage(int $status, int $page, int $pageSize): array
    {
        $total = $this->profileQuery($status)->count();
        $rows = $this->profileQuery($status)
            ->orderByDesc('cl_profile_audit.id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        return ['list' => $this->toRows($rows), 'total' => $total];
    }

    public function findProfileAudit(int $id): ?ProfileAudit
    {
        /** @var null|ProfileAudit $row */
        $row = $this->auditModel->newQuery()->whereKey($id)->first();

        return $row;
    }

    /** 记下这条资料审核的结果。 */
    public function markProfileAudited(int $id, int $status, ?string $reason, int $auditorId): void
    {
        $this->auditModel->newQuery()->whereKey($id)->update([
            'status' => $status,
            'reject_reason' => $reason,
            'auditor_id' => $auditorId,
            'audited_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 实名审核列表。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function realNamePage(int $status, int $page, int $pageSize): array
    {
        $total = $this->realNameQuery($status)->count();
        $rows = $this->realNameQuery($status)
            ->orderByDesc('cl_real_name.id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        return ['list' => $this->toRows($rows), 'total' => $total];
    }

    public function findRealName(int $id): ?RealName
    {
        /** @var null|RealName $row */
        $row = $this->realNameModel->newQuery()->whereKey($id)->first();

        return $row;
    }

    /**
     * 一批身份证 hash 各自被多少条实名记录用过（**只给后台提示，不拦截**，docs/07 第 3.5 节）。
     *
     * 一次查完，别在列表里按行去数（那样一页 20 行就是 20 次查询）。
     *
     * @param array<int,string> $hashes
     * @return array<string,int> hash => 条数
     */
    public function hashCounts(array $hashes): array
    {
        $hashes = array_values(array_unique(array_filter($hashes)));
        if ($hashes === []) {
            return [];
        }

        $map = [];
        $rows = $this->realNameModel->newQuery()
            ->whereIn('id_card_hash', $hashes)
            ->selectRaw('id_card_hash, COUNT(*) as total')
            ->groupBy('id_card_hash')
            ->get();

        foreach ($rows as $row) {
            $map[(string) $row->id_card_hash] = (int) $row->total;
        }

        return $map;
    }

    private function profileQuery(int $status): Builder
    {
        // `@var`：phpstan 认为 join 之后是 Query\Builder，但运行期一直是模型的 Builder
        /** @var Builder $query */
        $query = $this->auditModel->newQuery()
            ->join('cl_user', 'cl_user.id', '=', 'cl_profile_audit.user_id')
            ->select([
                'cl_profile_audit.id', 'cl_profile_audit.user_id', 'cl_profile_audit.field',
                'cl_profile_audit.old_value', 'cl_profile_audit.new_value', 'cl_profile_audit.status',
                'cl_profile_audit.reject_reason', 'cl_profile_audit.audited_at', 'cl_profile_audit.created_at',
                'cl_user.nickname as user_nickname', 'cl_user.avatar as user_avatar', 'cl_user.gender as user_gender',
            ])
            ->where('cl_profile_audit.status', $status);

        return $query;
    }

    private function realNameQuery(int $status): Builder
    {
        /** @var Builder $query */
        $query = $this->realNameModel->newQuery()
            ->join('cl_user', 'cl_user.id', '=', 'cl_real_name.user_id')
            ->select([
                'cl_real_name.id', 'cl_real_name.user_id', 'cl_real_name.real_name',
                'cl_real_name.id_card_mask', 'cl_real_name.id_card_hash', 'cl_real_name.status',
                'cl_real_name.reject_reason', 'cl_real_name.audited_at', 'cl_real_name.created_at',
                'cl_user.nickname as user_nickname', 'cl_user.avatar as user_avatar', 'cl_user.gender as user_gender',
            ])
            ->where('cl_real_name.status', $status);

        return $query;
    }

    /**
     * 统一成数组（模型或 stdClass 都吃）。
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
