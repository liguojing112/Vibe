<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Report;
use App\Repository\IRepository;
use Hyperf\Database\Model\Builder;

/**
 * @extends IRepository<Report>
 */
final class ReportRepository extends IRepository
{
    public function __construct(
        protected readonly Report $model
    ) {}

    /**
     * 按状态查举报（docs/07 第 3.9 节），带上举报人和被举报人是谁。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function pageByStatus(int $status, int $page, int $pageSize): array
    {
        $total = $this->queryByStatus($status)->count();
        $rows = $this->queryByStatus($status)
            ->orderByDesc('cl_report.id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'reporter_id' => (int) $row->reporter_id,
                'target_id' => (int) $row->target_id,
                'reason' => (string) $row->reason,
                'content' => (string) $row->content,
                'images' => $row->images === null ? [] : (array) $row->images,
                'message_id' => $row->message_id === null ? null : (int) $row->message_id,
                'status' => (int) $row->status,
                'handle_result' => (string) ($row->handle_result ?? ''),
                'handled_at' => $row->handled_at?->toDateTimeString(),
                'created_at' => $row->created_at?->toDateTimeString(),
                'reporter_nickname' => (string) $row->reporter_nickname,
                'reporter_avatar' => (string) ($row->reporter_avatar ?? ''),
                'reporter_gender' => $row->reporter_gender === null ? null : (int) $row->reporter_gender,
                'target_nickname' => (string) $row->target_nickname,
                'target_avatar' => (string) ($row->target_avatar ?? ''),
                'target_gender' => $row->target_gender === null ? null : (int) $row->target_gender,
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    private function queryByStatus(int $status): Builder
    {
        // `@var`：phpstan 认为 join 之后是 Query\Builder，运行期一直是模型的 Builder
        /** @var Builder $query */
        $query = $this->model->newQuery()
            ->join('cl_user as re', 're.id', '=', 'cl_report.reporter_id')
            ->join('cl_user as ta', 'ta.id', '=', 'cl_report.target_id')
            ->select([
                'cl_report.id', 'cl_report.reporter_id', 'cl_report.target_id', 'cl_report.reason',
                'cl_report.content', 'cl_report.images', 'cl_report.message_id', 'cl_report.status',
                'cl_report.handle_result', 'cl_report.handled_at', 'cl_report.created_at',
                're.nickname as reporter_nickname', 're.avatar as reporter_avatar', 're.gender as reporter_gender',
                'ta.nickname as target_nickname', 'ta.avatar as target_avatar', 'ta.gender as target_gender',
            ])
            ->where('cl_report.status', $status);

        return $query;
    }
}
