<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 访客（docs/04 第 2 节）。同一人同一天只留一条，重复访问只更新 `last_visit_at`。
 */
class Visit extends MineModel
{
    protected ?string $table = 'cl_visit';

    protected array $fillable = [
        'id', 'visitor_id', 'target_id', 'visit_date', 'last_visit_at', 'read_at',
        'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'visitor_id' => 'integer',
        'target_id' => 'integer',
        'visit_date' => 'date',
        'last_visit_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
