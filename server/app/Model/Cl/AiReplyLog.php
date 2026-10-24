<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * AI 代聊调用记录（成本留痕）。
 *
 * @property int $id
 * @property int $persona_id
 * @property int $user_id
 * @property int $to_user_id
 * @property null|int $conversation_id
 * @property null|int $message_id
 * @property string $kind
 * @property int $prompt_tokens
 * @property int $completion_tokens
 */
class AiReplyLog extends MineModel
{
    public const KIND_REPLY = 'reply';

    public const KIND_PROACTIVE = 'proactive';

    protected ?string $table = 'cl_ai_reply_log';

    protected array $fillable = [
        'id', 'persona_id', 'user_id', 'to_user_id', 'conversation_id',
        'message_id', 'kind', 'prompt_tokens', 'completion_tokens', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'persona_id' => 'integer',
        'user_id' => 'integer',
        'to_user_id' => 'integer',
        'conversation_id' => 'integer',
        'message_id' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
