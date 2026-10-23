package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.Moment
import com.qichuang.changliao.core.data.model.MomentComment
import com.qichuang.changliao.core.data.model.MomentCommentPage
import com.qichuang.changliao.core.data.model.MomentLikeResult
import com.qichuang.changliao.core.data.model.MomentPage
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.MomentApi
import com.qichuang.changliao.core.network.dto.CreateMomentCommentRequestDto
import com.qichuang.changliao.core.network.dto.MomentCommentDto
import com.qichuang.changliao.core.network.dto.MomentCommentPageDto
import com.qichuang.changliao.core.network.dto.MomentDto
import com.qichuang.changliao.core.network.dto.MomentLikeResultDto
import com.qichuang.changliao.core.network.dto.MomentPageDto
import com.qichuang.changliao.core.network.dto.PublishMomentRequestDto
import com.qichuang.changliao.core.network.safeApiCall
import com.qichuang.changliao.core.network.safeApiCallUnit
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 朋友圈（动态）。不缓存：动态是有时效的内容，进页面直接拉最新的，
 * 和系统消息那种"拉一次就该消失"的场景不同，缓存反而容易看到过期的点赞数。
 */
@Singleton
class MomentRepository @Inject constructor(
    private val momentApi: MomentApi,
) {

    suspend fun userMoments(userId: Long, page: Int): ApiResult<MomentPage> =
        mapPage(safeApiCall<MomentPageDto> { momentApi.userMoments(userId, page) })

    suspend fun mine(page: Int): ApiResult<MomentPage> =
        mapPage(safeApiCall<MomentPageDto> { momentApi.mine(page) })

    suspend fun publish(content: String, images: List<String>): ApiResult<Moment> =
        when (val result = safeApiCall<MomentDto> {
            momentApi.publish(PublishMomentRequestDto(content.trim(), images))
        }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun remove(id: Long): ApiResult<Unit> =
        safeApiCallUnit { momentApi.remove(id) }

    suspend fun like(id: Long): ApiResult<MomentLikeResult> =
        when (val result = safeApiCall<MomentLikeResultDto> { momentApi.like(id) }) {
            is ApiResult.Success -> ApiResult.Success(
                MomentLikeResult(liked = result.data.liked, likeCount = result.data.likeCount),
            )

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun unlike(id: Long): ApiResult<MomentLikeResult> =
        when (val result = safeApiCall<MomentLikeResultDto> { momentApi.unlike(id) }) {
            is ApiResult.Success -> ApiResult.Success(
                MomentLikeResult(liked = result.data.liked, likeCount = result.data.likeCount),
            )

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun comments(id: Long, page: Int): ApiResult<MomentCommentPage> =
        when (val result = safeApiCall<MomentCommentPageDto> { momentApi.comments(id, page) }) {
            is ApiResult.Success -> ApiResult.Success(
                MomentCommentPage(
                    list = result.data.list.map { it.toModel() },
                    total = result.data.total,
                    page = result.data.page,
                    hasMore = result.data.hasMore,
                ),
            )

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun comment(id: Long, content: String): ApiResult<MomentComment> =
        when (val result = safeApiCall<MomentCommentDto> {
            momentApi.comment(id, CreateMomentCommentRequestDto(content.trim()))
        }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    private fun mapPage(result: ApiResult<MomentPageDto>): ApiResult<MomentPage> = when (result) {
        is ApiResult.Success -> ApiResult.Success(
            MomentPage(
                list = result.data.list.map { it.toModel() },
                total = result.data.total,
                page = result.data.page,
                hasMore = result.data.hasMore,
            ),
        )

        is ApiResult.BizError -> result
        is ApiResult.NetworkError -> result
    }
}

private fun MomentDto.toModel() = Moment(
    id = id,
    userId = userId,
    nickname = nickname,
    avatar = avatar,
    content = content,
    images = images,
    likeCount = likeCount,
    commentCount = commentCount,
    isLiked = isLiked,
    canDelete = canDelete,
    createdAt = ChatRepository.serverMillis(createdAt, 0L),
)

private fun MomentCommentDto.toModel() = MomentComment(
    id = id,
    userId = userId,
    nickname = nickname,
    avatar = avatar,
    content = content,
    canDelete = canDelete,
    createdAt = ChatRepository.serverMillis(createdAt, 0L),
)
