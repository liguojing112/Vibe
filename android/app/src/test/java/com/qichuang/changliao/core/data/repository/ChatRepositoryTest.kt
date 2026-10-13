package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.local.UnreadStore
import com.qichuang.changliao.core.data.local.db.entity.ConversationEntity
import com.qichuang.changliao.core.data.local.db.entity.MessageEntity
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity
import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.MessageStatus
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.ChatApi
import com.qichuang.changliao.core.network.FakeTokenStore
import com.qichuang.changliao.core.network.buildTestRetrofit
import com.qichuang.changliao.core.network.ws.ChatSocket
import io.mockk.every
import io.mockk.mockk
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 聊天数据层（T4.5 / docs/10 第 1、2 节）。
 *
 * 两条硬验收在这里断言死：
 * - **重发用的还是同一个 `client_msg_id`**（换了服务端就会重复扣费）；
 * - **乐观 UI**：先写本地 `sending`，成功后就地替换成服务端那条（`localId` 不变，界面不闪）。
 *
 * 本地库用内存假实现（[FakeMessageDao] 等），HTTP 用 MockWebServer —— 这样 DTO 解析也一起测到了。
 */
class ChatRepositoryTest {

    private lateinit var server: MockWebServer
    private lateinit var messageDao: FakeMessageDao
    private lateinit var conversationDao: FakeConversationDao
    private lateinit var syncStateDao: FakeSyncStateDao
    private lateinit var repository: ChatRepository

    private val me = 7L
    private val peer = 2L

    /** 当前登录用户；测试里可以改它模拟「登录晚一点才拿到用户」。 */
    private val currentUserFlow = MutableStateFlow<UserProfile?>(UserProfile(id = 7L, nickname = "我"))

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()

        messageDao = FakeMessageDao()
        conversationDao = FakeConversationDao()
        syncStateDao = FakeSyncStateDao()

        val retrofit = buildTestRetrofit(server.url("/").toString(), FakeTokenStore(access = "token"))
        val userRepository = mockk<UserRepository>()
        every { userRepository.currentUser } returns currentUserFlow

        // 这个测试不碰连接，只测数据层；socket 给个不出声的替身
        val socket = mockk<ChatSocket>(relaxed = true)
        every { socket.frames } returns MutableSharedFlow()
        every { socket.connected } returns MutableStateFlow(false)

        repository = ChatRepository(
            chatApi = retrofit.create(ChatApi::class.java),
            messageDao = messageDao,
            conversationDao = conversationDao,
            syncStateDao = syncStateDao,
            socket = socket,
            unreadStore = UnreadStore(),
            userRepository = userRepository,
        )
    }

    @After
    fun tearDown() {
        runCatching { server.shutdown() }
    }

    // ---------- 发送 ----------

    @Test
    fun `发消息先落本地，成功后用同一个 clientMsgId 就地替换`() = runTest {
        server.enqueue(ok(sendBody(serverId = 100, conversationId = 5, content = "你好呀", priceCoins = 0)))

        val result = repository.send(peer, "你好呀")

        assertTrue(result is ApiResult.Success)
        assertEquals(1, messageDao.rows.size)

        val row = messageDao.rows.single()
        assertEquals(MessageStatus.SENT.code, row.status)
        assertEquals(100L, row.serverId)
        assertEquals(5L, row.conversationId)
        assertEquals(me, row.senderId)
        assertTrue(row.outgoing)

        // 请求里带的就是本地那一条的 clientMsgId —— 说明本地先写、网络后发
        val body = server.takeRequest().body.readUtf8()
        assertTrue(body.contains("\"client_msg_id\":\"${row.clientMsgId}\""))
        assertTrue(body.contains("\"receiver_id\":$peer"))
    }

    @Test
    fun `本地生成的 clientMsgId 是 32 位十六进制`() {
        // docs/10 第 8 节第 1 条：本地 id 必须和服务端 id 同格式，否则会被存储层静默挡掉
        val id = ChatRepository.newClientMsgId()

        assertEquals(32, id.length)
        assertTrue(id.all { it in '0'..'9' || it in 'a'..'f' })
    }

    @Test
    fun `网络失败时本地那条改判 failed，且可以重发`() = runTest {
        server.shutdown() // 连不上

        val result = repository.send(peer, "发不出去")

        assertTrue(result is ApiResult.NetworkError)
        assertEquals(MessageStatus.FAILED.code, messageDao.rows.single().status)
        assertTrue(messageDao.rows.single().toModelForTest().canResend)
    }

    /** **验收项**：重发不换 `client_msg_id`（换了服务端会重复扣费，docs/10 第 2.4 节）。 */
    @Test
    fun `重发用的还是同一个 client_msg_id`() = runTest {
        server.enqueue(MockResponse().setResponseCode(500))
        repository.send(peer, "重发我")
        val failed = messageDao.rows.single()
        assertEquals(MessageStatus.FAILED.code, failed.status)

        server.enqueue(ok(sendBody(serverId = 101, conversationId = 5, content = "重发我")))

        val result = repository.resend(failed.localId)

        assertTrue(result is ApiResult.Success)
        // 还是那一条（localId 没变、没有多出一条）
        assertEquals(1, messageDao.rows.size)
        assertEquals(failed.localId, messageDao.rows.single().localId)
        assertEquals(failed.clientMsgId, messageDao.rows.single().clientMsgId)
        assertEquals(MessageStatus.SENT.code, messageDao.rows.single().status)

        val second = server.takeRequest()
        val body = second.body.readUtf8()
        assertTrue(
            "重发的 client_msg_id 必须和第一次一样，实际：$body",
            body.contains("\"client_msg_id\":\"${failed.clientMsgId}\""),
        )
    }

    @Test
    fun `余额不足时把本地那条撤掉，交给页面把文字放回输入框`() = runTest {
        server.enqueue(
            MockResponse().setResponseCode(200)
                .setBody("""{"code":20001,"message":"金币余额不足","data":{"balance":4}}"""),
        )

        val result = repository.send(peer, "钱不够")

        assertTrue(result is ApiResult.BizError)
        assertEquals(ChatRepository.CODE_COIN_NOT_ENOUGH, (result as ApiResult.BizError).code)
        assertTrue("本地不该留下一条会重复发送的气泡", messageDao.rows.isEmpty())
    }

    // ---------- 增量补拉 ----------

    @Test
    fun `sync 把收到的消息写进本地，按对方归类并记未读`() = runTest {
        server.enqueue(
            ok(
                """
                {"code":200,"data":{"list":[${messageJson(id = 9, sender = peer, receiver = me, clientMsgId = "a1", content = "在吗")}],
                "max_id":9,"has_more":false,"unread":{"conversation":1,"visitor":0,"notice":0,"total":1}}}
                """,
            ),
        )

        val result = repository.sync()

        assertTrue(result is ApiResult.Success)
        val row = messageDao.rows.single()
        assertEquals(peer, row.peerId)
        assertFalse(row.outgoing)
        assertEquals(MessageStatus.SENT.code, row.status)

        val conversation = conversationDao.byPeerId(me, peer)
        assertNotNull(conversation)
        assertEquals(1, conversation!!.unread)
        assertEquals("在吗", conversation.lastMessagePreview)
        assertEquals(9L, syncStateDao.globalCursor(me))
    }

    @Test
    fun `重复拉到同一条消息不会把未读越加越高`() = runTest {
        val body = """
            {"code":200,"data":{"list":[${messageJson(id = 9, sender = peer, receiver = me, clientMsgId = "a1", content = "在吗")}],
            "max_id":9,"has_more":false,"unread":{"conversation":1,"visitor":0,"notice":0,"total":1}}}
        """
        server.enqueue(ok(body))
        repository.sync()
        server.enqueue(ok(body))
        repository.sync()

        // 推送 + 补拉可能给同一条，去重靠 clientMsgId 的唯一索引
        assertEquals(1, messageDao.rows.size)
        assertEquals(1, conversationDao.byPeerId(me, peer)!!.unread)
    }

    @Test
    fun `sync 推进游标，下一次只拉更新的`() = runTest {
        server.enqueue(ok("""{"code":200,"data":{"list":[],"max_id":42,"has_more":false,"unread":{}}}"""))
        repository.sync()

        server.enqueue(ok("""{"code":200,"data":{"list":[],"max_id":42,"has_more":false,"unread":{}}}"""))
        repository.sync()

        server.takeRequest()
        val second = server.takeRequest()
        assertTrue("第二次要带上第一次的游标，实际：${second.path}", second.path!!.contains("after_id=42"))
    }

    @Test
    fun `自己发出去的消息不计未读`() = runTest {
        server.enqueue(
            ok(
                """
                {"code":200,"data":{"list":[${messageJson(id = 9, sender = me, receiver = peer, clientMsgId = "a1", content = "我说的")}],
                "max_id":9,"has_more":false,"unread":{"conversation":0,"visitor":0,"notice":0,"total":0}}}
                """,
            ),
        )

        repository.sync()

        assertEquals(0, conversationDao.byPeerId(me, peer)!!.unread)
    }

    /**
     * 这条是**在模拟器上真跑出来的 bug**：主界面一出现就 `start()` 了，而 `/user/me` 还没回来，
     * 于是未读的总数流绑到了 ownerId = 0 上 —— 推送明明写进了库（ownerId=101），底部角标却一直是 0。
     * 所以未读流必须跟着 currentUser 走，而不是在 start() 那一刻取一次。
     */
    @Test
    fun `未读角标跟着当前用户走，登录晚一点也算得对`() = runTest {
        currentUserFlow.value = null
        conversationDao.upsert(
            ConversationEntity(ownerId = me, peerId = peer, lastMessageAt = 1, unread = 2),
        )

        val values = mutableListOf<Int>()
        val job = launch { repository.unreadStream().collect { values += it } }
        runCurrent()
        assertEquals(0, values.last())

        currentUserFlow.value = UserProfile(id = me)
        runCurrent()

        assertEquals("用户加载好之后要立刻算出正确的未读", 2, values.last())
        job.cancel()
    }

    @Test
    fun `换账号时未读不会串到别人头上`() = runTest {
        conversationDao.upsert(ConversationEntity(ownerId = me, peerId = peer, lastMessageAt = 1, unread = 2))
        conversationDao.upsert(ConversationEntity(ownerId = 99, peerId = peer, lastMessageAt = 1, unread = 5))

        val values = mutableListOf<Int>()
        val job = launch { repository.unreadStream().collect { values += it } }
        runCurrent()
        assertEquals(2, values.last())

        currentUserFlow.value = UserProfile(id = 99)
        runCurrent()

        assertEquals(5, values.last())
        job.cancel()
    }

    // ---------- 卡在 sending 的消息 ----------

    @Test
    fun `启动时把超过 60 秒还在发送中的消息改判失败`() = runTest {
        val now = System.currentTimeMillis()
        messageDao.insert(sendingRow(clientMsgId = "old", createdAt = now - 70_000))
        messageDao.insert(sendingRow(clientMsgId = "fresh", createdAt = now - 10_000))

        repository.markStaleSending()

        val byId = messageDao.rows.associateBy { it.clientMsgId }
        assertEquals(MessageStatus.FAILED.code, byId.getValue("old").status)
        assertEquals(MessageStatus.SENDING.code, byId.getValue("fresh").status)
    }

    // ---------- 会话列表与删除 ----------

    @Test
    fun `刷新会话列表写进本地库，删除后本地不再显示`() = runTest {
        server.enqueue(ok(conversationsBody()))
        repository.refreshConversations()

        assertEquals(1, conversationDao.rows.size)
        assertEquals("你好", conversationDao.rows.single().lastMessagePreview)
        assertEquals(1, conversationDao.all(me).first().size)

        server.enqueue(ok("""{"code":200,"message":"成功","data":[]}"""))
        repository.deleteConversation(peer)

        assertTrue(conversationDao.all(me).first().isEmpty())
    }

    @Test
    fun `对方来新消息后删掉的会话重新出现`() = runTest {
        server.enqueue(ok(conversationsBody()))
        repository.refreshConversations()
        server.enqueue(ok("""{"code":200,"message":"成功","data":[]}"""))
        repository.deleteConversation(peer)
        assertTrue(conversationDao.all(me).first().isEmpty())

        server.enqueue(
            ok(
                """
                {"code":200,"data":{"list":[${messageJson(id = 11, sender = peer, receiver = me, clientMsgId = "b2", content = "在吗")}],
                "max_id":11,"has_more":false,"unread":{"conversation":1,"visitor":0,"notice":0,"total":1}}}
                """,
            ),
        )
        repository.sync()

        val visible = conversationDao.all(me).first()
        assertEquals(1, visible.size)
        assertEquals("在吗", visible.single().lastMessagePreview)
    }

    // ---------- 进聊天页 / 已读 ----------

    @Test
    fun `进聊天页拿到收费信息，女用户是 null`() = runTest {
        server.enqueue(
            ok(
                """
                {"code":200,"data":{"conversation_id":5,"peer":{"id":$peer,"nickname":"小满"},
                "unread":2,"fee":{"next_price":5,"free_reason":null,"pair_free_left":0,"vip_free_left":0,
                "balance":100,"strip_text":"私信 5 币/条 · 余额 100 币"}}}
                """,
            ),
        )

        val result = repository.enterChat(peer)

        assertTrue(result is ApiResult.Success)
        val entry = (result as ApiResult.Success).data
        assertEquals(5L, entry.conversationId)
        assertEquals("小满", entry.peer.nickname)
        assertEquals("私信 5 币/条 · 余额 100 币", entry.fee?.stripText)

        server.enqueue(
            ok("""{"code":200,"data":{"conversation_id":5,"peer":{"id":$peer},"unread":0,"fee":null}}"""),
        )
        val female = repository.enterChat(peer)
        assertNull((female as ApiResult.Success).data.fee)
    }

    @Test
    fun `标记已读先清本地未读再告诉服务端`() = runTest {
        server.enqueue(ok(conversationsBody()))
        repository.refreshConversations()
        server.takeRequest()

        server.enqueue(ok("""{"code":200,"message":"成功","data":{"conversation":0,"visitor":0,"notice":0,"total":0}}"""))
        repository.markRead(peer)

        assertEquals(0, conversationDao.byPeerId(me, peer)!!.unread)
        val request = server.takeRequest()
        assertEquals("/api/v1/chat/read", request.path)
        assertTrue(request.body.readUtf8().contains("\"conversation_id\":5"))
    }

    // ---------- 工具 ----------

    private fun ok(body: String) = MockResponse().setResponseCode(200).setBody(body)

    /**
     * 发消息的返回。响应里的 `client_msg_id` 用不着和请求一致 ——
     * 仓库层认的是**本地那条**的 id（服务端只是把它原样回显）。
     */
    private fun sendBody(serverId: Long, conversationId: Long, content: String, priceCoins: Int = 0) = """
        {"code":200,"message":"成功","data":{
          "message":${messageJson(serverId, me, peer, "echoed", content, priceCoins)},
          "fee":null,"balance":100}}
    """

    private fun conversationsBody() = """
        {"code":200,"message":"成功","data":{"list":[
          {"id":5,"peer":{"id":$peer,"nickname":"小满","avatar":"a.png"},
           "last_message":${messageJson(id = 8, sender = peer, receiver = me, clientMsgId = "c1", content = "你好")},
           "last_message_at":"2026-10-13 10:00:00","unread":2}
        ],"total":1,"page":1,"page_size":20,"has_more":false}}
    """

    private fun messageJson(
        id: Long,
        sender: Long,
        receiver: Long,
        clientMsgId: String,
        content: String,
        priceCoins: Int = 0,
    ) = """
        {"id":$id,"conversation_id":5,"sender_id":$sender,"receiver_id":$receiver,
         "client_msg_id":"$clientMsgId","type":1,"content":"$content","price_coins":$priceCoins,
         "free_reason":null,"receiver_points":0,"sensitive_hit":0,"created_at":"2026-10-13 10:00:00"}
    """

    private fun sendingRow(clientMsgId: String, createdAt: Long) = MessageEntity(
        ownerId = me,
        clientMsgId = clientMsgId,
        peerId = peer,
        senderId = me,
        outgoing = true,
        content = "发送中",
        status = MessageStatus.SENDING.code,
        createdAt = createdAt,
        updatedAt = createdAt,
    )

    private fun MessageEntity.toModelForTest() = ChatMessage(
        localId = localId,
        clientMsgId = clientMsgId,
        peerId = peerId,
        outgoing = outgoing,
        status = MessageStatus.fromCode(status),
    )
}
