package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.local.CacheKeys
import com.qichuang.changliao.core.data.local.ClDataStore
import com.qichuang.changliao.core.data.local.LocalCache
import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.model.DiscoverPage
import com.qichuang.changliao.core.data.model.DiscoverUser
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.DiscoverApi
import com.qichuang.changliao.core.network.clJson
import com.qichuang.changliao.core.network.safeApiCall
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 发现页数据（docs/05 第 4.3 节、docs/01 P01）。
 *
 * **第一页走 stale-while-revalidate**（docs/10 第 3 节）：先发本地缓存让页面秒开，再静默刷新；
 * 下拉刷新传 `force = true` 跳过 60 秒节流。第二页起是「加载更多」，不读缓存。
 */
@Singleton
class DiscoverRepository @Inject constructor(
    private val discoverApi: DiscoverApi,
    private val localCache: LocalCache,
    private val dataStore: ClDataStore,
) {

    /**
     * 读本地保存的筛选条件（docs/01 P07：筛选存在本地，**回到 P01 生效**）。
     * 按用户分开存；解不出来（结构变了）就当没筛过。
     */
    suspend fun filter(userId: Long): DiscoverFilter {
        val json = dataStore.filtersFlow(userId).first() ?: return DiscoverFilter()

        return runCatching { clJson.decodeFromString(DiscoverFilter.serializer(), json) }
            .getOrDefault(DiscoverFilter())
    }

    fun saveFilter(userId: Long, filter: DiscoverFilter) {
        dataStore.saveFilters(userId, clJson.encodeToString(DiscoverFilter.serializer(), filter))
    }

    fun firstPage(
        tab: String,
        userId: Long,
        filter: DiscoverFilter,
        force: Boolean = false,
    ): Flow<ApiResult<DiscoverPage>> = localCache.staleWhileRevalidate(
        key = CacheKeys.discover(tab, userId, filter.signature),
        serializer = DiscoverPage.serializer(),
        force = force,
    ) {
        fetch(tab, filter, page = 1)
    }

    /** 加载更多（第 2 页起）。 */
    suspend fun more(tab: String, filter: DiscoverFilter, page: Int): ApiResult<DiscoverPage> =
        fetch(tab, filter, page)

    /**
     * 按昵称 / ID 搜人（消息页搜索框用）。服务端只返回异性，和发现页口径一致。
     *
     * 失败时返回空列表而不是错误：搜索是辅助动作，不该把整页顶成错误态。
     */
    suspend fun search(keyword: String): List<DiscoverUser> =
        when (val result = safeApiCall<DiscoverPage> { discoverApi.search(keyword, page = 1) }) {
            is ApiResult.Success -> result.data.list
            is ApiResult.BizError -> emptyList()
            is ApiResult.NetworkError -> emptyList()
        }

    private suspend fun fetch(tab: String, filter: DiscoverFilter, page: Int): ApiResult<DiscoverPage> =
        safeApiCall {
            discoverApi.discover(
                tab = tab,
                page = page,
                city = filter.city.ifBlank { null },
                // 年龄只在**真的缩过范围**时才传：一旦带了年龄，服务端会按生日过滤，
                // 没填生日的用户就被排除了，不该因为「没筛过」而误伤。
                ageMin = filter.ageMin.takeIf { it != DiscoverFilter.DEFAULT_AGE_MIN },
                ageMax = filter.ageMax.takeIf { it != DiscoverFilter.DEFAULT_AGE_MAX },
                purposes = filter.purposes.ifEmpty { null },
                verified = filter.verified.toFlag(),
                complete = filter.complete.toFlag(),
                onlineOnly = filter.onlineOnly.toFlag(),
            )
        }

    /** 开关类参数：false 不传（服务端 `sometimes`），省得白传一堆 0。 */
    private fun Boolean.toFlag(): Int? = if (this) 1 else null
}
