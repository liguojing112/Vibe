package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.local.UnreadStore
import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.ChatApi
import com.qichuang.changliao.core.network.FakeTokenStore
import com.qichuang.changliao.core.network.GiftApi
import com.qichuang.changliao.core.network.buildTestRetrofit
import com.qichuang.changliao.core.network.ws.ChatSocket
import io.mockk.every
import io.mockk.mockk
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.runTest
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 礼物数据层（T5.4 / docs/05 第 4.6 节）。
 *
 * 这里断言死的是**在模拟器上真跑出来的那个 bug**：送完礼物回到聊天页看不到礼物气泡。
 * 原因是服务端的推送只发给收礼那一边（`PushService::messageReceived`），送礼这一端
 * 本地库里没有这条 —— 所以 `/gift/send` 成功之后必须自己补写一条进本地库。
 */
class GiftRepositoryTest {

    private lateinit var server: MockWebServer
    private lateinit var messageDao: FakeMessageDao
    private lateinit var conversationDao: FakeConversationDao
    private lateinit var repository: GiftRepository

    private val me = 7L
    private val peer = 113L

    private val currentUserFlow = MutableStateFlow<UserProfile?>(UserProfile(id = 7L, nickname = "我"))

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()

        messageDao = FakeMessageDao()
        conversationDao = FakeConversationDao()

        val retrofit = buildTestRetrofit(server.url("/").toString(), FakeTokenStore(access = "token"))
        val userRepository = mockk<UserRepository>()
        every { userRepository.currentUser } returns currentUserFlow

        val socket = mockk<ChatSocket>(relaxed = true)
        every { socket.frames } returns MutableSharedFlow()
        every { socket.connected } returns MutableStateFlow(false)

        val chatRepository = ChatRepository(
            chatApi = retrofit.create(ChatApi::class.java),
            messageDao = messageDao,
            conversationDao = conversationDao,
            syncStateDao = FakeSyncStateDao(),
            socket = socket,
            imageUploader = mockk<ImageUploader>(relaxed = true),
            unreadStore = UnreadStore(),
            userRepository = userRepository,
        )
        repository = GiftRepository(
            giftApi = retrofit.create(GiftApi::class.java),
            chatRepository = chatRepository,
        )
    }

    @After
    fun tearDown() {
        runCatching { server.shutdown() }
    }

    @Test
    fun `送礼成功后礼物消息要落本地库，否则回聊天页看不到气泡`() = runTest {
        server.enqueue(ok(sendBody(serverId = 900, balance = 180)))

        val result = repository.send(peer, giftId = 4, clientOrderId = "order-1")

        assertTrue(result is ApiResult.Success)
        assertEquals(180L, (result as ApiResult.Success).data.balance)

        val row = messageDao.rows.single()
        assertEquals(ChatMessage.TYPE_GIFT, row.type)
        assertTrue("送礼的是我，气泡要在右边", row.outgoing)
        assertEquals(peer, row.peerId)
        assertEquals(me, row.senderId)
        assertEquals(900L, row.serverId)

        // 会话摘要也要跟着变成 [礼物]（会话列表里显示的就是它）
        val conversation = conversationDao.byPeerId(me, peer)
        assertNotNull(conversation)
        assertEquals("[礼物]", conversation!!.lastMessagePreview)
        assertEquals("自己送的礼物不该给自己加未读", 0, conversation.unread)
    }

    @Test
    fun `同一个订单号重发不会被重复算成两条消息`() = runTest {
        val body = sendBody(serverId = 900, balance = 180)
        server.enqueue(ok(body))
        repository.send(peer, giftId = 4, clientOrderId = "order-1")
        server.enqueue(ok(body))
        repository.send(peer, giftId = 4, clientOrderId = "order-1")

        // 服务端靠订单号幂等，本地靠 clientMsgId 的唯一索引去重，两边都不会多出一条
        assertEquals(1, messageDao.rows.size)
    }

    @Test
    fun `送礼请求带上订单号，失败时什么都不写本地库`() = runTest {
        server.enqueue(
            MockResponse().setResponseCode(200)
                .setBody("""{"code":20001,"message":"金币余额不足","data":{"balance":30,"needed":120}}"""),
        )

        val result = repository.send(peer, giftId = 4, clientOrderId = "order-2")

        assertTrue(result is ApiResult.BizError)
        assertEquals(20001, (result as ApiResult.BizError).code)
        assertTrue("没送出去就不该留下气泡", messageDao.rows.isEmpty())

        val body = server.takeRequest().body.readUtf8()
        assertTrue(body.contains("\"client_order_id\":\"order-2\""))
        assertTrue(body.contains("\"receiver_id\":$peer"))
        assertTrue(body.contains("\"gift_id\":4"))
    }

    @Test
    fun `网络失败原样返回，本地不留垃圾`() = runTest {
        server.shutdown()

        val result = repository.send(peer, giftId = 4, clientOrderId = "order-3")

        assertTrue(result is ApiResult.NetworkError)
        assertTrue(messageDao.rows.isEmpty())
        assertTrue(conversationDao.rows.isEmpty())
    }

    @Test
    fun `礼物列表原样解析`() = runTest {
        server.enqueue(
            ok(
                """
                {"code":200,"message":"成功","data":{"list":[
                  {"id":1,"name":"一朵玫瑰","icon":"🌹","price_coins":10},
                  {"id":6,"name":"梦幻城堡","icon":"🏰","price_coins":680}
                ]}}
                """,
            ),
        )

        val result = repository.list()

        assertTrue(result is ApiResult.Success)
        val gifts = (result as ApiResult.Success).data
        assertEquals(2, gifts.size)
        assertEquals("一朵玫瑰", gifts[0].name)
        assertEquals(680, gifts[1].priceCoins)
    }

    @Test
    fun `本地生成的订单号是 32 位十六进制，和服务端 id 同形状`() {
        val id = GiftRepository.newOrderId()

        assertEquals(32, id.length)
        assertTrue(id.all { it in '0'..'9' || it in 'a'..'f' })
    }

    private fun ok(body: String) = MockResponse().setResponseCode(200).setBody(body)

    /** `/gift/send` 的返回：会话里那条礼物消息 + 最新余额。 */
    private fun sendBody(serverId: Long, balance: Long) = """
        {"code":200,"message":"成功","data":{
          "message":${messageJson(serverId)},
          "balance":$balance}}
    """

    private fun messageJson(id: Long) = """
        {"id":$id,"conversation_id":5,"sender_id":$me,"receiver_id":$peer,
         "client_msg_id":"order-1","type":${ChatMessage.TYPE_GIFT},"content":"浪漫花束",
         "price_coins":0,"free_reason":null,"receiver_points":408,"sensitive_hit":0,
         "created_at":"2026-10-13 10:00:00"}
    """
}
