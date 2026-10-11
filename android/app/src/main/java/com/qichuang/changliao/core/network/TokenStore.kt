package com.qichuang.changliao.core.network

import kotlinx.coroutines.flow.Flow

/**
 * 登录态存取（docs/02 第 4.3 节）。
 *
 * 实现见 `core/data/local/ClDataStore.kt`（DataStore 持久化 + 内存缓存）。
 * 抽成接口是为了让 [AuthInterceptor] 在单元测试里能换成内存实现。
 */
interface TokenStore {
    /** 同步读 access token（拦截器在 OkHttp 线程里用，所以不走 Flow）。 */
    fun accessToken(): String?

    /** 同步读 refresh token。 */
    fun refreshToken(): String?

    /** 响应式读 access token（页面根据它决定去登录页还是主页）。 */
    fun accessTokenFlow(): Flow<String?>

    fun saveTokens(accessToken: String, refreshToken: String)

    /** 清登录态（401 且刷新失败时调用）。 */
    fun clear()
}
