package com.qichuang.changliao.feature.chat

import com.qichuang.changliao.core.data.model.ChatMessage
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * 聊天列表的行拼装（P06）：**间隔超过 5 分钟插一条时间**，第一条前面一定有。
 *
 * 这是纯函数，所以能直接把规则断言死（页面里那套倒序铺列表的写法不好单测）。
 */
class ChatUiStateTest {

    @Test
    fun `第一条消息前面一定有时间分隔`() {
        val rows = buildChatRows(listOf(message(id = 1, at = 1_791_888_877_000)))

        assertEquals(2, rows.size)
        assertTrue(rows[0] is ChatRow.Time)
        assertTrue(rows[1] is ChatRow.Message)
    }

    @Test
    fun `间隔不到 5 分钟不插时间`() {
        val base = 1_791_888_877_000
        val rows = buildChatRows(
            listOf(
                message(id = 1, at = base),
                message(id = 2, at = base + 60_000),
                message(id = 3, at = base + 4 * 60_000),
            ),
        )

        assertEquals(1, rows.count { it is ChatRow.Time })
        assertEquals(4, rows.size)
    }

    @Test
    fun `间隔超过 5 分钟就插一条`() {
        val base = 1_791_888_877_000
        val rows = buildChatRows(
            listOf(
                message(id = 1, at = base),
                message(id = 2, at = base + 6 * 60_000),
            ),
        )

        assertEquals(2, rows.count { it is ChatRow.Time })
        assertEquals(4, rows.size)
    }

    @Test
    fun `界面用的是倒过来的行（最新的在最下面）`() {
        val base = 1_791_888_877_000
        val state = ChatUiState(
            messages = listOf(
                message(id = 1, at = base, content = "第一"),
                message(id = 2, at = base + 10 * 60_000, content = "第二"),
            ),
        )

        // 倒序列表里，数组第一个显示在最下面 —— 所以最新的那条要在最前面
        val firstMessage = state.displayRows.filterIsInstance<ChatRow.Message>().first()
        assertEquals("第二", firstMessage.message.content)
    }

    @Test
    fun `空消息列表没有行，也不该报错`() {
        assertTrue(buildChatRows(emptyList()).isEmpty())
        assertTrue(ChatUiState().displayRows.isEmpty())
    }

    private fun message(id: Long, at: Long, content: String = "内容") = ChatMessage(
        localId = id,
        clientMsgId = "c$id",
        peerId = 102,
        outgoing = true,
        content = content,
        createdAt = at,
    )
}
