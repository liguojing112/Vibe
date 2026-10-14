package com.qichuang.changliao.core.network.ws

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * 服务端推下来的一帧怎么解析（T4.5 / docs/05 第 5 节）。
 *
 * 这里最要紧的一条是**认不出来就丢**：服务端以后加新事件，现在这版客户端不能因此崩掉或者断开。
 */
class ChatFrameTest {

    @Test
    fun `message_new 解析出完整消息`() {
        val frame = ChatFrame.parse(
            """
            {"event":"message.new","data":{"id":9,"conversation_id":5,"sender_id":2,"receiver_id":7,
             "client_msg_id":"a1","type":1,"content":"你好呀","price_coins":5,"free_reason":null,
             "receiver_points":15,"sensitive_hit":0,"created_at":"2026-10-13 10:00:00"}}
            """,
        )

        assertTrue(frame is ChatFrame.MessageNew)
        val message = (frame as ChatFrame.MessageNew).message
        assertEquals(9L, message.id)
        assertEquals(5L, message.conversationId)
        assertEquals(2L, message.senderId)
        assertEquals("a1", message.clientMsgId)
        assertEquals("你好呀", message.content)
        assertEquals(5, message.priceCoins)
        assertEquals("2026-10-13 10:00:00", message.createdAt)
    }

    @Test
    fun `unread_update 解析出三项未读`() {
        val frame = ChatFrame.parse(
            """{"event":"unread.update","data":{"conversation":3,"visitor":1,"notice":0,"total":4}}""",
        )

        assertTrue(frame is ChatFrame.UnreadUpdate)
        val unread = (frame as ChatFrame.UnreadUpdate).unread
        assertEquals(3, unread.conversation)
        assertEquals(1, unread.visitor)
        assertEquals(4, unread.total)
    }

    @Test
    fun `pong 的 data 是空对象也能认`() {
        assertEquals(ChatFrame.Pong, ChatFrame.parse("""{"event":"pong","data":{}}"""))
    }

    @Test
    fun `notice_new 只认事件本身，不解载荷`() {
        assertEquals(
            ChatFrame.NoticeNew,
            ChatFrame.parse("""{"event":"notice.new","data":{"id":1,"category":2,"title":"收到礼物"}}"""),
        )
    }

    /** 封号：原因和到期时间要带过来（docs/08 T7.5）；永久封禁时 `until` 是 null。 */
    @Test
    fun `account_banned 带上原因和解封时间`() {
        val frame = ChatFrame.parse("""{"event":"account.banned","data":{"reason":"发布违规内容","until":"2026-11-01 00:00:00"}}""")

        assertEquals(ChatFrame.AccountBanned("发布违规内容", "2026-11-01 00:00:00"), frame)

        val forever = ChatFrame.parse("""{"event":"account.banned","data":{"reason":"屡教不改","until":null}}""")
        assertEquals(ChatFrame.AccountBanned("屡教不改", null), forever)
    }

    @Test
    fun `认不出来的事件丢掉，不抛错`() {
        assertNull(ChatFrame.parse("""{"event":"friend.request","data":{"id":1}}"""))
        assertNull(ChatFrame.parse("""{"event":"","data":{}}"""))
    }

    @Test
    fun `坏数据丢掉，不抛错`() {
        assertNull(ChatFrame.parse(""))
        assertNull(ChatFrame.parse("not json at all"))
        assertNull(ChatFrame.parse("""{"event":"message.new"}"""))
        assertNull(ChatFrame.parse("""{"event":"message.new","data":"不是对象"}"""))
    }

    @Test
    fun `消息里没见过的字段不影响解析`() {
        // 服务端加字段时旧客户端要照常认下来
        val frame = ChatFrame.parse(
            """{"event":"message.new","data":{"id":1,"client_msg_id":"x","content":"hi","brand_new_field":123}}""",
        )

        assertTrue(frame is ChatFrame.MessageNew)
        assertEquals("hi", (frame as ChatFrame.MessageNew).message.content)
    }
}
