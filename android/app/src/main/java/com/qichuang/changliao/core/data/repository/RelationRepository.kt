package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.local.CacheKeys
import com.qichuang.changliao.core.data.local.LocalCache
import com.qichuang.changliao.core.data.model.RelationKind
import com.qichuang.changliao.core.data.model.RelationPage
import com.qichuang.changliao.core.data.model.RelationState
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.RelationApi
import com.qichuang.changliao.core.network.dto.RelationStateDto
import com.qichuang.changliao.core.network.dto.RelationTargetDto
import com.qichuang.changliao.core.network.safeApiCall
import com.qichuang.changliao.core.network.safeApiCallUnit
import kotlinx.coroutines.flow.Flow
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 关系（docs/05 第 4.4 节）：关注、取关、拉黑、解除拉黑。
 *
 * 「拉黑后互相不可见」这类规则都在服务端，客户端只管调接口、把结果告诉页面。
 * 关注/取关会把**关注后的关系**带回来，页面据此把按钮从「关注」换成「已关注 / 互相关注」。
 */
@Singleton
class RelationRepository @Inject constructor(
    private val relationApi: RelationApi,
    private val localCache: LocalCache,
) {

    suspend fun follow(targetId: Long): ApiResult<RelationState> =
        safeApiCall<RelationStateDto> { relationApi.follow(RelationTargetDto(targetId)) }
            .mapToState()

    suspend fun unfollow(targetId: Long): ApiResult<RelationState> =
        safeApiCall<RelationStateDto> { relationApi.unfollow(RelationTargetDto(targetId)) }
            .mapToState()

    suspend fun block(targetId: Long): ApiResult<Unit> =
        safeApiCallUnit { relationApi.block(RelationTargetDto(targetId)) }

    suspend fun unblock(targetId: Long): ApiResult<Unit> =
        safeApiCallUnit { relationApi.unblock(RelationTargetDto(targetId)) }

    // ---------- 列表（P08 最近访客 / P09 喜欢我的人） ----------

    /**
     * 第一页：先发本地缓存让页面秒开，再静默刷新（docs/10 第 3 节）；
     * 下拉刷新传 `force = true`。注意缓存键按用户分。
     */
    fun firstPage(kind: RelationKind, userId: Long, force: Boolean = false): Flow<ApiResult<RelationPage>> =
        localCache.staleWhileRevalidate(
            key = cacheKeyOf(kind, userId),
            serializer = RelationPage.serializer(),
            force = force,
        ) {
            fetch(kind, page = 1)
        }

    /** 加载更多（第 2 页起，不读缓存）。 */
    suspend fun more(kind: RelationKind, page: Int): ApiResult<RelationPage> = fetch(kind, page)

    /**
     * 黑名单（P15 设置页）。**不缓存** —— 这是设置页里的一个列表，
     * 打开频率低，而且「移除了谁」要立刻反映出来（缓存住反而会看到刚移除的人还在）。
     */
    suspend fun blocks(page: Int): ApiResult<RelationPage> = safeApiCall { relationApi.blocks(page) }

    private suspend fun fetch(kind: RelationKind, page: Int): ApiResult<RelationPage> = safeApiCall {
        when (kind) {
            RelationKind.VISITORS -> relationApi.visitors(page)
            RelationKind.FANS -> relationApi.fans(page)
        }
    }

    private fun cacheKeyOf(kind: RelationKind, userId: Long): String = when (kind) {
        RelationKind.VISITORS -> CacheKeys.visitors(userId)
        RelationKind.FANS -> CacheKeys.fans(userId)
    }

    private fun ApiResult<RelationStateDto>.mapToState(): ApiResult<RelationState> = when (this) {
        is ApiResult.Success -> ApiResult.Success(
            RelationState(isFollowing = data.isFollowing, isMutual = data.isMutual),
        )

        is ApiResult.BizError -> this
        is ApiResult.NetworkError -> this
    }
}
