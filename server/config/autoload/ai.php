<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    // OpenAI 兼容协议：换成别家模型（比如以后接的模型）只改这三项就行
    'base_url' => env('AI_BASE_URL', 'https://api.deepseek.com'),
    'api_key' => env('AI_API_KEY', ''),
    'model' => env('AI_MODEL', 'deepseek-chat'),
    'timeout' => (int) env('AI_TIMEOUT', 30),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 120),
    'temperature' => (float) env('AI_TEMPERATURE', 1.1),
    // 单次带多少条历史消息进上下文（太多既慢又贵）
    'context_messages' => (int) env('AI_CONTEXT_MESSAGES', 12),
];
