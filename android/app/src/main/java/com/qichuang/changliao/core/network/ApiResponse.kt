package com.qichuang.changliao.core.network

import kotlinx.serialization.Serializable

/**
 * 服务端统一响应外壳（docs/05 第 1 节）：`{ "code": 200, "message": "成功", "data": {...} }`。
 *
 * - 成功 code = 200
 * - 业务错误 HTTP 也是 200，code 是业务码（20001 等），message 可直接给用户看
 * - 未登录 / token 失效是 HTTP 401
 */
@Serializable
data class ApiResponse<T>(
    val code: Int = 0,
    val message: String? = null,
    val data: T? = null,
)

/** 服务端约定：成功码。 */
const val API_CODE_SUCCESS = 200

/** 服务端约定：未登录 / token 失效（HTTP 401）。 */
const val API_CODE_UNAUTHORIZED = 401

/** 服务端约定：账号被封禁（HTTP 200 + 业务码 10004）。 */
const val API_CODE_ACCOUNT_BANNED = 10004
