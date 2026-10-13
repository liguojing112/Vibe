package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.Notice
import com.qichuang.changliao.core.data.model.NoticeCategory
import com.qichuang.changliao.core.data.model.NoticePage
import com.qichuang.changliao.core.data.model.ReportResult
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NoticeApi
import com.qichuang.changliao.core.network.dto.NoticeDto
import com.qichuang.changliao.core.network.dto.NoticePageDto
import com.qichuang.changliao.core.network.dto.ReportDataDto
import com.qichuang.changliao.core.network.dto.ReportRequestDto
import com.qichuang.changliao.core.network.safeApiCall
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 系统消息与举报（docs/05 第 4.9 节，P10 / P05）。
 *
 * **通知不缓存**：列表一被打开服务端就把它标成已读了（已读状态在服务端），
 * 缓存住会让「红点已清」和「列表还是旧的」对不上。
 */
@Singleton
class NoticeRepository @Inject constructor(
    private val noticeApi: NoticeApi,
) {

    /** 拉某个分类的通知。**拉完服务端就把这一分类标已读了**。 */
    suspend fun list(category: NoticeCategory, page: Int): ApiResult<NoticePage> =
        when (val result = safeApiCall<NoticePageDto> { noticeApi.list(category.apiValue, page) }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /**
     * 举报（P05 的「…」菜单）。
     *
     * 原因、对象、说明都由页面传上来；**能不能举报**（不能举报自己、对象要存在、
     * 带上来的 message_id 得是自己的）全在服务端判。
     */
    suspend fun report(
        targetId: Long,
        reason: String,
        content: String = "",
        images: List<String> = emptyList(),
    ): ApiResult<ReportResult> =
        when (
            val result = safeApiCall<ReportDataDto> {
                noticeApi.report(ReportRequestDto(targetId, reason, content.trim(), images))
            }
        ) {
            is ApiResult.Success -> ApiResult.Success(
                ReportResult(id = result.data.id, statusText = result.data.statusText),
            )

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
}

private fun NoticePageDto.toModel() = NoticePage(
    list = list.map { it.toModel() },
    total = total,
    page = page,
    unread = unread,
)

private fun NoticeDto.toModel() = Notice(
    id = id,
    category = category,
    title = title,
    content = content,
    bizType = bizType,
    bizId = bizId,
    read = read,
    createdAt = ChatRepository.serverMillis(createdAt, 0L),
)
