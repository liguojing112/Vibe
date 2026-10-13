package com.qichuang.changliao.core.data.model

/**
 * 消息的本地状态机（docs/10 第 1.3 节）：
 *
 * ```
 * SENDING → SENT（服务端确认）
 *         → FAILED（点气泡重发，client_msg_id 不变）
 * ```
 *
 * [code] 是要写进本地库的值，**改它等于改数据库**，不要动。
 */
enum class MessageStatus(val code: Int) {
    SENDING(0),
    SENT(1),
    FAILED(2),
    ;

    companion object {
        fun fromCode(code: Int): MessageStatus = entries.firstOrNull { it.code == code } ?: SENT
    }
}

/**
 * 一条消息（界面用的模型，由本地库映射而来）。
 *
 * 和 `docs/10` 第 1.2 节的表一致：**[serverId] 可能为空**（还没发出去的消息没有服务端 id），
 * 所以本地主键是 [localId]；[clientMsgId] 是自己生成的，重发时**必须保持不变**
 * —— 服务端靠它做幂等，换了就会重复扣费。
 *
 * 价格只做显示（铁律 6）：[priceCoins] 是服务端扣掉的数，客户端不算钱。
 */
data class ChatMessage(
    val localId: Long = 0,
    val clientMsgId: String,
    val serverId: Long? = null,
    val conversationId: Long? = null,
    val peerId: Long,
    val senderId: Long = 0,
    val outgoing: Boolean = false,
    val type: Int = TYPE_TEXT,
    val content: String = "",
    /** 图片消息的本地文件路径：发出去之前先存本地，重发时直接用（docs/10 第 2.5 节）。 */
    val localMediaPath: String? = null,
    val status: MessageStatus = MessageStatus.SENT,
    val priceCoins: Int = 0,
    /** 服务端时间，毫秒 */
    val createdAt: Long = 0,
) {
    /** 能不能重发（只有自己发的、失败了的才给重发入口）。 */
    val canResend: Boolean get() = outgoing && status == MessageStatus.FAILED

    companion object {
        /** 消息类型，和服务端 `cl_message.type` 对齐（docs/04 第 3 节）。 */
        const val TYPE_TEXT = 1

        const val TYPE_IMAGE = 2

        const val TYPE_GIFT = 3

        const val TYPE_SYSTEM = 4
    }
}

/**
 * 收费信息（docs/05 第 4.5 节）。**只对男用户有值，女用户是 null**。
 *
 * [stripText] 是服务端拼好的整句文案，聊天页顶部的收费条直接显示它，客户端不拼文案（铁律 6）。
 */
data class ChatFee(
    val nextPrice: Int = 0,
    val freeReason: String? = null,
    val pairFreeLeft: Int = 0,
    val vipFreeLeft: Int = 0,
    val balance: Long = 0,
    val stripText: String = "",
)

/** 发消息的结果：正式消息 + 发送后的最新收费信息 + 余额。 */
data class ChatSendResult(
    val message: ChatMessage,
    val fee: ChatFee? = null,
    val balance: Long = 0,
)

/**
 * 会话（P03 的列表项）。
 *
 * [serverConversationId] 可能为空：**第一次发消息之前服务端还没有会话**，
 * 所以本地的会话是以 [peerId] 为主键的，服务端 id 只是拿来调接口用。
 */
data class ChatConversation(
    val peerId: Long,
    val serverConversationId: Long? = null,
    val peerNickname: String = "",
    val peerAvatar: String = "",
    val lastMessagePreview: String = "",
    /** 毫秒 */
    val lastMessageAt: Long = 0,
    val unread: Int = 0,
) {
    val hasUnread: Boolean get() = unread > 0
}

/** 聊天里的「对方简要资料」（进聊天页 / 会话列表都用它）。 */
data class ChatPeer(
    val id: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    val age: Int? = null,
    val gender: Int = 0,
    val level: Int = 1,
    val online: Boolean = false,
)

/** 进聊天页的结果（docs/05 第 4.5 节 `/chat/conversations/with/{userId}`）。 */
data class ChatEntry(
    val conversationId: Long? = null,
    val peer: ChatPeer = ChatPeer(),
    val unread: Int = 0,
    val fee: ChatFee? = null,
)

/**
 * 未读总览（docs/05 第 4.5 节）：会话 / 访客 / 系统通知。
 *
 * **会话未读以本地库为准**（docs/10 第 1 节），这里保留服务端的数用于校准。
 */
data class ChatUnread(
    val conversation: Int = 0,
    val visitor: Int = 0,
    val notice: Int = 0,
    val total: Int = 0,
)
