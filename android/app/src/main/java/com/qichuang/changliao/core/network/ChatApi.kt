package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.ReadRequestDto
import com.qichuang.changliao.core.network.dto.SendMessageRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/**
 * 私信接口（docs/05 第 4.5 节）。
 *
 * 注意：**安卓端以本地库（Room）为聊天记录的主数据源**（docs/10 第 1 节），
 * 这几个接口只服务于「首次拉取 + 静默刷新 + 增量补拉」，界面不从它们直接渲染。
 */
interface ChatApi {

    /** 发消息。价格、余额、收费文案全由服务端算好返回（铁律 6）。 */
    @POST("api/v1/chat/send")
    suspend fun send(@Body body: SendMessageRequestDto): ApiResponse<JsonElement?>

    /** 会话列表（分页）。App 端另有本地库，这里用于首次拉取 + 静默刷新。 */
    @GET("api/v1/chat/conversations")
    suspend fun conversations(@Query("page") page: Int): ApiResponse<JsonElement?>

    /** 删除会话（只对自己隐藏）。 */
    @DELETE("api/v1/chat/conversations/{id}")
    suspend fun deleteConversation(@Path("id") conversationId: Long): ApiResponse<JsonElement?>

    /** 进聊天页：会话 id（没有则 null）+ 收费信息 `fee`。 */
    @GET("api/v1/chat/conversations/with/{userId}")
    suspend fun with(@Path("userId") peerId: Long): ApiResponse<JsonElement?>

    /** 历史消息（倒序，上拉传 `before_id`）。 */
    @GET("api/v1/chat/messages")
    suspend fun messages(
        @Query("conversation_id") conversationId: Long,
        @Query("before_id") beforeId: Long? = null,
        @Query("limit") limit: Int = 30,
    ): ApiResponse<JsonElement?>

    /** 标记已读，返回最新未读总览。 */
    @POST("api/v1/chat/read")
    suspend fun read(@Body body: ReadRequestDto): ApiResponse<JsonElement?>

    /** 未读总览：会话 / 访客 / 系统通知。 */
    @GET("api/v1/chat/unread")
    suspend fun unread(): ApiResponse<JsonElement?>

    /** 增量补拉：我收到的、id 大于 `after_id` 的消息（重连 / 回前台时用）。 */
    @GET("api/v1/chat/sync")
    suspend fun sync(
        @Query("after_id") afterId: Long,
        @Query("limit") limit: Int = 100,
    ): ApiResponse<JsonElement?>

    /** 收费说明弹窗的条目（按当前配置生成）。 */
    @GET("api/v1/chat/fee-rules")
    suspend fun feeRules(): ApiResponse<JsonElement?>
}
