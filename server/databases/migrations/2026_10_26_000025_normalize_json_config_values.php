<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 修复历史上被双重编码的 JSON 配置值。
 *
 * 旧版 `AdminConfigService::save()` 把校验后的 JSON 字符串又交给
 * `AppConfigService::set()` 编码了一次，数据库里会变成
 * `"[20,100,200,500]"`（字符串里再套一层数组）。
 *
 * 这会让后台提现档位/充值档位显示成 JSON 文本框，App 也可能读到字符串。
 * 这里把所有 `value_type = json` 的值逐层解到真正的数组再存回去；
 * 不是双重编码的数据原样不动。
 */
return new class extends Migration {
    public function up(): void
    {
        $rows = Db::table('cl_config')->where('value_type', 'json')->get(['id', 'value']);

        foreach ($rows as $row) {
            $raw = (string) ($row->value ?? '');
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $changed = false;
            while (is_string($decoded)) {
                $again = json_decode($decoded, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    break;
                }
                $decoded = $again;
                $changed = true;
            }

            if (! $changed || ! is_array($decoded)) {
                continue;
            }

            Db::table('cl_config')->where('id', (int) $row->id)->update([
                'value' => (string) json_encode($decoded, JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** 无法区分「原本就是数组」和「被修过」，不做回滚。 */
    public function down(): void {}
};
