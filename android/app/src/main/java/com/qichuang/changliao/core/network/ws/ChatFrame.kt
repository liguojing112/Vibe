package com.qichuang.changliao.core.network.ws

import com.qichuang.changliao.core.network.clJson
import com.qichuang.changliao.core.network.dto.MessageDto
import com.qichuang.changliao.core.network.dto.UnreadDto
import com.qichuang.changliao.core.network.dto.WsFrameDto
import kotlinx.serialization.json.decodeFromJsonElement

/**
 * 服务端推下来的一帧（docs/05 第 5 节）。
 *
 * 只认下面四种：**认不出来的一律返回 null 丢掉**，不抛异常 ——
 * 服务端以后加了新事件，旧客户端不能因此断开或崩掉。
 */
sealed interface ChatFrame {

    /** 有人给我发消息 / 礼物。 */
    data class MessageNew(val message: MessageDto) : ChatFrame

    /** 未读数变了（结构和 `/chat/unread` 一样）。 */
    data class UnreadUpdate(val unread: UnreadDto) : ChatFrame

    /**
     * 收到一条系统/互动通知（P10 的红点）。
     *
     * 载荷是自包含的（标题、正文、分类），但**这里不需要解它**：客户端只借这个事件
     * 把红点 +1，正文等打开 P10 时再拉。解一半反而多一处要跟着服务端改的地方。
     */
    data object NoticeNew : ChatFrame

    /** 心跳应答。 */
    data object Pong : ChatFrame

    companion object {
        const val EVENT_MESSAGE_NEW = "message.new"

        const val EVENT_UNREAD_UPDATE = "unread.update"

        const val EVENT_NOTICE_NEW = "notice.new"

        const val EVENT_PONG = "pong"

        fun parse(text: String): ChatFrame? {
            val frame = runCatching { clJson.decodeFromString(WsFrameDto.serializer(), text) }.getOrNull()
                ?: return null
            val data = frame.data ?: return null

            return runCatching {
                when (frame.event) {
                    EVENT_MESSAGE_NEW -> MessageNew(clJson.decodeFromJsonElement<MessageDto>(data))
                    EVENT_UNREAD_UPDATE -> UnreadUpdate(clJson.decodeFromJsonElement<UnreadDto>(data))
                    EVENT_NOTICE_NEW -> NoticeNew
                    EVENT_PONG -> Pong
                    else -> null
                }
            }.getOrNull()
        }
    }
}
