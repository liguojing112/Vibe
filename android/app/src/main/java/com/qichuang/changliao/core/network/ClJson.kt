package com.qichuang.changliao.core.network

import kotlinx.serialization.json.Json

/**
 * 全项目共用的 JSON 配置（NetworkModule 注入的也是这个实例）。
 */
val clJson: Json = Json {
    ignoreUnknownKeys = true
    coerceInputValues = true
    explicitNulls = false
}
