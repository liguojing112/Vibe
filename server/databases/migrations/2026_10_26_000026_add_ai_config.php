<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * AI 代聊的自定义模型配置（后台「AI 笔友 → AI 模型」）。
 *
 * 以前 base_url / model / api_key 写死在 `.env`；现在客户可以在后台填任意 OpenAI 兼容服务。
 * 后台留空时 `DeepSeekClient` 仍回退 `.env`，不影响现有部署。
 */
return new class extends Migration {
    private const ROWS = [
        ['key' => 'ai.base_url', 'value' => 'https://api.deepseek.com', 'value_type' => 'string', 'group' => 'ai', 'label' => 'AI 服务地址', 'remark' => 'OpenAI 兼容接口的 Base URL，例如 https://api.deepseek.com'],
        ['key' => 'ai.model', 'value' => 'deepseek-chat', 'value_type' => 'string', 'group' => 'ai', 'label' => '模型名', 'remark' => '例如 deepseek-chat、gpt-4o-mini'],
        ['key' => 'ai.api_key', 'value' => '', 'value_type' => 'string', 'group' => 'ai', 'label' => 'API Key', 'remark' => '只存服务端；后台留空表示不修改'],
    ];

    public function up(): void
    {
        foreach (self::ROWS as $row) {
            Db::table('cl_config')->updateOrInsert(
                ['key' => $row['key']],
                $row + ['updated_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')],
            );
        }
    }

    public function down(): void
    {
        Db::table('cl_config')->whereIn('key', array_column(self::ROWS, 'key'))->delete();
    }
};
