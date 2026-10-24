package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

/**
 * 一条消息（docs/05 第 4.5 节）。`/chat/send`、`/chat/messages`、`/chat/sync` 和 WebSocket 的
 * `message.new` 推送**用的是同一套字段**，所以只需要这一个 DTO。
 */
@Serializable
data class MessageDto(
    val id: Long = 0,
    @SerialName("conversation_id") val conversationId: Long = 0,
    @SerialName("sender_id") val senderId: Long = 0,
    @SerialName("receiver_id") val receiverId: Long = 0,
    @SerialName("client_msg_id") val clientMsgId: String = "",
    val type: Int = 1,
    val content: String = "",
    /** 礼物消息的图标（emoji 或图片地址），服务端按礼物名补上。 */
    @SerialName("gift_icon") val giftIcon: String = "",
    @SerialName("price_coins") val priceCoins: Int = 0,
    @SerialName("free_reason") val freeReason: String? = null,
    @SerialName("receiver_points") val receiverPoints: Int = 0,
    @SerialName("sensitive_hit") val sensitiveHit: Int = 0,
    @SerialName("created_at") val createdAt: String? = null,
)

/**
 * 收费信息（docs/05 第 4.5 节 `fee`）。**只对男用户有值，女用户是 null**。
 *
 * `stripText` 是服务端拼好的整句文案，客户端**只显示不算**（铁律 6）。
 */
@Serializable
data class ChatFeeDto(
    @SerialName("next_price") val nextPrice: Int = 0,
    @SerialName("free_reason") val freeReason: String? = null,
    @SerialName("pair_free_left") val pairFreeLeft: Int = 0,
    @SerialName("vip_free_left") val vipFreeLeft: Int = 0,
    val balance: Long = 0,
    @SerialName("strip_text") val stripText: String = "",
)

/** 发消息的返回（docs/05 第 4.5 节）。 */
@Serializable
data class SendMessageDataDto(
    val message: MessageDto = MessageDto(),
    val fee: ChatFeeDto? = null,
    val balance: Long = 0,
)

/** 会话列表里的一项（docs/05 第 4.5 节）。 */
@Serializable
data class ConversationDto(
    val id: Long = 0,
    val peer: ChatPeerDto? = null,
    @SerialName("last_message") val lastMessage: MessageDto? = null,
    @SerialName("last_message_at") val lastMessageAt: String? = null,
    val unread: Int = 0,
)

/** 聊天相关接口里的「对方简要资料」。 */
@Serializable
data class ChatPeerDto(
    val id: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    val age: Int? = null,
    val gender: Int = 0,
    val level: Int = 1,
    val online: Boolean = false,
)

/** 会话列表的一页。 */
@Serializable
data class ConversationPageDto(
    val list: List<ConversationDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    @SerialName("page_size") val pageSize: Int = 20,
    @SerialName("has_more") val hasMore: Boolean = false,
)

/** 历史消息的一页（游标分页：`{list, has_more}`，**倒序**，最新在前）。 */
@Serializable
data class MessagePageDto(
    val list: List<MessageDto> = emptyList(),
    @SerialName("has_more") val hasMore: Boolean = false,
)

/** 未读总览（docs/05 第 4.5 节）。 */
@Serializable
data class UnreadDto(
    val conversation: Int = 0,
    val visitor: Int = 0,
    val notice: Int = 0,
    val total: Int = 0,
)

/** 增量补拉的返回（docs/05 第 4.5 节）。 */
@Serializable
data class SyncDataDto(
    val list: List<MessageDto> = emptyList(),
    @SerialName("max_id") val maxId: Long = 0,
    @SerialName("has_more") val hasMore: Boolean = false,
    val unread: UnreadDto = UnreadDto(),
)

/**
 * `account.banned` 推送的载荷（docs/08 T7.5）。
 *
 * `until` 为 null 表示永久封禁。
 */
@Serializable
data class AccountBannedDto(
    val reason: String = "",
    val until: String? = null,
)

/** 进聊天页的返回（docs/05 第 4.5 节）：会话 id 可能为 null（第一次发消息时才建）+ 收费信息。 */
@Serializable
data class ChatEntryDto(
    @SerialName("conversation_id") val conversationId: Long? = null,
    val peer: ChatPeerDto = ChatPeerDto(),
    val unread: Int = 0,
    val fee: ChatFeeDto? = null,
)

/** 收费说明弹窗的条目（docs/05 第 4.5 节 `/chat/fee-rules`）。 */
@Serializable
data class FeeRulesDto(
    val items: List<String> = emptyList(),
)

/** 发消息的入参。 */
@Serializable
data class SendMessageRequestDto(
    @SerialName("receiver_id") val receiverId: Long,
    val type: Int,
    val content: String,
    @SerialName("client_msg_id") val clientMsgId: String,
)

/** 标记已读的入参。 */
@Serializable
data class ReadRequestDto(
    @SerialName("conversation_id") val conversationId: Long,
)

/** WebSocket 下发的一帧（docs/05 第 5 节）：`{ "event": "...", "data": {...} }`。 */
@Serializable
data class WsFrameDto(
    val event: String = "",
    val data: JsonElement? = null,
)
