<?php

declare(strict_types=1);

/**
 * 畅撩交友自己的配置（不是 `cl_config` 表里的可调项）。
 *
 * 只放**不该进数据库**的东西：密钥这类只能走 `.env`（docs/04 第 1 节、铁律「可调项从配置读」）。
 */
return [
    // 身份证号 AES 加密密钥。`.env` 里必须配（`CL_ID_CARD_KEY`），示例值见 `.env.example`
    'id_card_key' => env('CL_ID_CARD_KEY', ''),
];
