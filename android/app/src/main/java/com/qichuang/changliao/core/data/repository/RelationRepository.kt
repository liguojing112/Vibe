package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.RelationState
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.RelationApi
import com.qichuang.changliao.core.network.dto.RelationStateDto
import com.qichuang.changliao.core.network.dto.RelationTargetDto
import com.qichuang.changliao.core.network.safeApiCall
import com.qichuang.changliao.core.network.safeApiCallUnit
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

    private fun ApiResult<RelationStateDto>.mapToState(): ApiResult<RelationState> = when (this) {
        is ApiResult.Success -> ApiResult.Success(
            RelationState(isFollowing = data.isFollowing, isMutual = data.isMutual),
        )

        is ApiResult.BizError -> this
        is ApiResult.NetworkError -> this
    }
}
