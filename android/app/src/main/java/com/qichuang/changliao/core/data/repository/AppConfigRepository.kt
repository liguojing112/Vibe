package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.local.ClDataStore
import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.AppApi
import com.qichuang.changliao.core.network.clJson
import com.qichuang.changliao.core.network.safeApiCall
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 启动配置（docs/01 第 4 节）：App 启动拉一次 `/app/config` 并落本地，之后界面读 [config]。
 *
 * 拿不到（断网 / 首次安装还没缓存）时 [config] 为 null，调用方按「功能关闭」处理 ——
 * 与服务端默认值一致（`feature.call_enabled` 默认 0）。
 */
@Singleton
class AppConfigRepository @Inject constructor(
    private val appApi: AppApi,
    private val dataStore: ClDataStore,
) {

    private val _config = MutableStateFlow(readCached())

    val config: StateFlow<AppConfig?> = _config.asStateFlow()

    /** 拉取最新配置并缓存；失败时保留原有值。 */
    suspend fun refresh(): ApiResult<AppConfig> {
        val result = safeApiCall<AppConfig> { appApi.config() }
        if (result is ApiResult.Success) {
            _config.value = result.data
            dataStore.saveAppConfig(clJson.encodeToString(AppConfig.serializer(), result.data))
        }
        return result
    }

    private fun readCached(): AppConfig? {
        val json = dataStore.appConfigJson() ?: return null
        return runCatching { clJson.decodeFromString(AppConfig.serializer(), json) }.getOrNull()
    }
}
