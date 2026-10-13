package com.qichuang.changliao.feature.chat

import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.ChatFee
import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.ChatPeer
import kotlinx.serialization.Serializable

/**
 * 聊天页（P06）状态。
 *
 * 消息来自**本地库**（`ChatRepository.messages(peerId)`），所以自己发出去的乐观气泡、
 * 对方推过来的新消息、补拉回来的历史，全都走同一条路进界面。
 *
 * 价格相关的东西**只有男用户有**（[fee] 为 null 时女用户视角，页面不显示任何价格）。
 */
data class ChatUiState(
    val peerId: Long = 0,
    val peer: ChatPeer = ChatPeer(),
    val messages: List<ChatMessage> = emptyList(),
    val fee: ChatFee? = null,
    val input: String = "",
    val maxLength: Int = DEFAULT_MAX_LENGTH,
    val isMale: Boolean = false,
    /** 我自己的头像（自己的气泡右侧要显示，设计图 06）。 */
    val myAvatar: String = "",
    val callEnabled: Boolean = false,
    val loading: Boolean = true,
    val error: String? = null,
    val sending: Boolean = false,
    val loadingMore: Boolean = false,
    val hasMore: Boolean = true,
    /** 余额不足（20001）时弹 P30；不为空就是弹窗开着。 */
    val coinNotEnough: CoinNotEnough? = null,
    /** 收费说明弹窗的条目；不为空就是弹窗开着。 */
    val feeRules: List<String>? = null,
    val toast: String? = null,
) {

    val isEmpty: Boolean = !loading && error == null && messages.isEmpty()

    /**
     * 输入框的提示语（docs/01 P06）：男用户显示当前单价，女用户只说「说点什么…」。
     *
     * 价格文案走 [MoneyFormat]，页面里不准手拼「币/条」（`check-ui.sh` 会拦）。
     */
    val placeholder: String
        get() = when {
            !isMale -> "说点什么…"
            (fee?.nextPrice ?: 0) > 0 -> "发一句问候 · " +
                MoneyFormat.coinPrice((fee?.nextPrice ?: 0).toLong(), MoneyFormat.UNIT_PER_MESSAGE)

            else -> "发一句问候"
        }

    /** 列表要铺的行：消息 + 时间分隔（间隔超过 5 分钟插一条，docs/01 P06）。 */
    val rows: List<ChatRow> = buildChatRows(messages)

    /** 聊天页是倒着铺的（最新的在最下面），所以行也要倒过来给。 */
    val displayRows: List<ChatRow> = rows.asReversed()

    companion object {
        const val DEFAULT_MAX_LENGTH = 500
    }
}

/**
 * 余额不足弹窗（P30）要的数据。
 *
 * `@Serializable` 是因为它直接对应 20001 的 `data`：`{"balance": 4, "needed": 5}`（服务端抛的）。
 */
@Serializable
data class CoinNotEnough(
    val balance: Long = 0,
    val needed: Long = 0,
) {
    /** 还差多少（服务端给的是「需要多少」和「现在有多少」）。 */
    val short: Long = (needed - balance).coerceAtLeast(0)
}

/** 聊天列表里的一行。 */
sealed interface ChatRow {

    /** 时间分隔条。 */
    data class Time(val label: String) : ChatRow

    data class Message(val message: ChatMessage) : ChatRow
}

/**
 * 消息列表 + 时间分隔（纯函数，好单测）。
 *
 * 规则（docs/01 P06）：**相邻两条间隔超过 [GAP_MILLIS] 就在中间插一条时间**，
 * 第一条前面也一定有（不然用户不知道这段对话是什么时候的）。
 */
internal fun buildChatRows(
    messages: List<ChatMessage>,
    gapMillis: Long = GAP_MILLIS,
): List<ChatRow> {
    val rows = mutableListOf<ChatRow>()
    var previousAt = 0L

    messages.forEach { message ->
        if (rows.isEmpty() || message.createdAt - previousAt > gapMillis) {
            rows += ChatRow.Time(chatTimeLabel(message.createdAt))
        }
        rows += ChatRow.Message(message)
        previousAt = message.createdAt
    }

    return rows
}

/** 「今天 09:41」这种；跨天的交给 [TimeFormat.friendly]（它自己会给「昨天 HH:mm」「MM-dd HH:mm」）。 */
private fun chatTimeLabel(millis: Long): String {
    val seconds = millis / 1000
    val day = TimeFormat.dayLabel(seconds)

    return if (day == TODAY || day == YESTERDAY) {
        "$day ${TimeFormat.friendly(seconds)}"
    } else {
        TimeFormat.friendly(seconds)
    }
}

/** 时间分隔的间隔阈值（docs/01 P06：超过 5 分钟显示一次时间）。 */
internal const val GAP_MILLIS = 5 * 60 * 1000L

private const val TODAY = "今天"

private const val YESTERDAY = "昨天"
