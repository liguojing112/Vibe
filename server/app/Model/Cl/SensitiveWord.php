<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 敏感词库（docs/04 第 3 节）。**命中只标记不拦截**：消息照常送达，
 * 但会在 `cl_message.sensitive_hit` 上打标，进后台待审核（`04` 第 3 节、`01` 第 2 节）。
 */
class SensitiveWord extends MineModel
{
    protected ?string $table = 'cl_sensitive_word';

    /** 表里的默认值也写在模型上（`create()` 后内存对象就带着它们）。 */
    protected array $attributes = [
        'enabled' => 1,
    ];

    protected array $fillable = ['id', 'word', 'enabled', 'creator_id', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'enabled' => 'integer',
        'creator_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
