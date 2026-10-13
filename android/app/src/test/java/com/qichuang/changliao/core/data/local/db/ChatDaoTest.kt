package com.qichuang.changliao.core.data.local.db

import androidx.room.Room
import androidx.test.core.app.ApplicationProvider
import com.qichuang.changliao.core.data.local.db.entity.ConversationEntity
import com.qichuang.changliao.core.data.local.db.entity.MessageEntity
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity
import com.qichuang.changliao.core.data.model.MessageStatus
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * 本地库的 DAO（T4.5 / docs/10 第 1.2 节）。
 *
 * 这一层以前是空的（Q0-21）：写错了 SQL 只有真机上才看得出来、或者被 Room 在编译期挡住一部分。
 * 现在用 Robolectric 在 JVM 上跑**真 Room 库**，把去重、排序、未读、游标这些行为逐个锁住。
 *
 * 用内存库（`inMemoryDatabaseBuilder`）：不落盘、每个用例都是干净的。
 */
@RunWith(RobolectricTestRunner::class)
class ChatDaoTest {

    private lateinit var db: ClDatabase

    private val me = 101L
    private val peer = 102L

    @Before
    fun setUp() {
        db = Room.inMemoryDatabaseBuilder(
            ApplicationProvider.getApplicationContext(),
            ClDatabase::class.java,
        ).allowMainThreadQueries().build()
    }

    @After
    fun tearDown() {
        db.close()
    }

    // ---------- message ----------

    @Test
    fun `clientMsgId 唯一——同一条消息写两次只有一行`() = runTest {
        val first = message(clientMsgId = "c1")
        assertTrue(db.messageDao().insert(first) > 0)

        // 乐观 UI 写过的本地记录，服务端返回同一条（同一个 client_msg_id）时会再插一次
        val again = db.messageDao().insert(message(clientMsgId = "c1"))
        assertEquals(-1L, again)
        assertEquals(1, db.messageDao().countByPeer(me, peer))
    }

    @Test
    fun `按时间升序取某个联系人的消息`() = runTest {
        db.messageDao().insert(message(clientMsgId = "c2", createdAt = 200, content = "第二"))
        db.messageDao().insert(message(clientMsgId = "c1", createdAt = 100, content = "第一"))
        db.messageDao().insert(message(clientMsgId = "c3", createdAt = 300, content = "第三", peerId = 999))

        val rows = db.messageDao().byPeer(me, peer).first()

        assertEquals(listOf("第一", "第二"), rows.map { it.content })
    }

    @Test
    fun `服务端确认后就地替换，本地 id 不变`() = runTest {
        val localId = db.messageDao().insert(
            message(clientMsgId = "c1", createdAt = 100, status = MessageStatus.SENDING.code),
        )

        db.messageDao().markSent(
            localId = localId,
            serverId = 54,
            conversationId = 32,
            content = "在吗",
            status = MessageStatus.SENT.code,
            priceCoins = 5,
            createdAt = 1791888877000,
            updatedAt = 1791888880000,
        )

        val row = db.messageDao().byLocalId(localId)!!
        assertEquals(1, db.messageDao().countByPeer(me, peer))
        assertEquals(1L, row.localId)
        assertEquals(54L, row.serverId)
        assertEquals(32L, row.conversationId)
        assertEquals(MessageStatus.SENT.code, row.status)
        assertEquals(5, row.priceCoins)
        assertEquals(1791888877000, row.createdAt)
    }

    @Test
    fun `只有超过时限的发送中消息会被改判失败`() = runTest {
        val now = 1_000_000L
        db.messageDao().insert(
            message(clientMsgId = "old", createdAt = now - 70_000, status = MessageStatus.SENDING.code),
        )
        db.messageDao().insert(
            message(clientMsgId = "fresh", createdAt = now - 10_000, status = MessageStatus.SENDING.code),
        )
        db.messageDao().insert(
            message(clientMsgId = "sent", createdAt = now - 70_000, status = MessageStatus.SENT.code),
        )

        val changed = db.messageDao().markStale(
            from = MessageStatus.SENDING.code,
            to = MessageStatus.FAILED.code,
            before = now - 60_000,
            now = now,
        )

        assertEquals(1, changed)
        val byId = db.messageDao().byPeer(me, peer).first().associateBy { it.clientMsgId }
        assertEquals(MessageStatus.FAILED.code, byId.getValue("old").status)
        assertEquals(MessageStatus.SENDING.code, byId.getValue("fresh").status)
        assertEquals(MessageStatus.SENT.code, byId.getValue("sent").status)
    }

    @Test
    fun `上拉游标取本地最老一条有服务端 id 的消息`() = runTest {
        // 还在发送中（没有 serverId）的不该被当成游标
        db.messageDao().insert(message(clientMsgId = "c0", createdAt = 50, serverId = null))
        db.messageDao().insert(message(clientMsgId = "c1", createdAt = 100, serverId = 40))
        db.messageDao().insert(message(clientMsgId = "c2", createdAt = 200, serverId = 41))

        assertEquals(40L, db.messageDao().oldestServerId(me, peer))
        assertNull(db.messageDao().oldestServerId(me, 999))
    }

    @Test
    fun `删掉一条本地消息（余额不足时撤掉乐观气泡）`() = runTest {
        val localId = db.messageDao().insert(message(clientMsgId = "c1"))

        db.messageDao().delete(localId)

        assertEquals(0, db.messageDao().countByPeer(me, peer))
    }

    // ---------- conversation ----------

    @Test
    fun `会话按最后消息时间倒序，删掉的不出现`() = runTest {
        db.conversationDao().upsert(conversation(peerId = 1, lastMessageAt = 100))
        db.conversationDao().upsert(conversation(peerId = 2, lastMessageAt = 300))
        db.conversationDao().upsert(conversation(peerId = 3, lastMessageAt = 200))

        assertEquals(listOf(2L, 3L, 1L), db.conversationDao().all(me).first().map { it.peerId })

        db.conversationDao().hide(me, 2)
        assertEquals(listOf(3L, 1L), db.conversationDao().all(me).first().map { it.peerId })
    }

    @Test
    fun `同一个人再写一次会话是覆盖不是新增`() = runTest {
        db.conversationDao().upsert(conversation(peerId = peer, unread = 1))
        db.conversationDao().upsert(conversation(peerId = peer, unread = 5, preview = "新消息"))

        val rows = db.conversationDao().all(me).first()
        assertEquals(1, rows.size)
        assertEquals(5, rows.single().unread)
        assertEquals("新消息", rows.single().lastMessagePreview)
    }

    @Test
    fun `未读总数只算没被删掉的会话，且按登录用户分开`() = runTest {
        db.conversationDao().upsert(conversation(peerId = 1, unread = 2))
        db.conversationDao().upsert(conversation(peerId = 2, unread = 3))
        db.conversationDao().upsert(conversation(peerId = 3, unread = 7, ownerId = 999))

        assertEquals(5, db.conversationDao().unreadTotal(me).first())
        assertEquals(7, db.conversationDao().unreadTotal(999).first())

        db.conversationDao().hide(me, 1)
        assertEquals("删掉的会话不该还挂在角标上", 3, db.conversationDao().unreadTotal(me).first())
    }

    @Test
    fun `已读清零，对方来新消息后重新出现`() = runTest {
        db.conversationDao().upsert(conversation(peerId = peer, unread = 3))
        db.conversationDao().clearUnread(me, peer)
        assertEquals(0, db.conversationDao().byPeerId(me, peer)!!.unread)

        db.conversationDao().hide(me, peer)
        assertTrue(db.conversationDao().byPeerId(me, peer)!!.deleted)

        db.conversationDao().unhide(me, peer)
        assertFalse(db.conversationDao().byPeerId(me, peer)!!.deleted)
    }

    // ---------- sync_state ----------

    @Test
    fun `同步游标按登录用户分开，换号不会互相顶掉`() = runTest {
        db.syncStateDao().upsert(
            SyncStateEntity(ownerId = me, conversationId = SyncStateEntity.GLOBAL_CURSOR, lastSyncedMessageId = 54),
        )

        assertEquals(54L, db.syncStateDao().lastSyncedMessageId(me, SyncStateEntity.GLOBAL_CURSOR))
        assertNull(
            "另一个账号必须是干净的起点，否则中间的消息永远补不回来",
            db.syncStateDao().lastSyncedMessageId(999, SyncStateEntity.GLOBAL_CURSOR),
        )

        db.syncStateDao().upsert(
            SyncStateEntity(ownerId = me, conversationId = SyncStateEntity.GLOBAL_CURSOR, lastSyncedMessageId = 60),
        )
        assertEquals(60L, db.syncStateDao().lastSyncedMessageId(me, SyncStateEntity.GLOBAL_CURSOR))
    }

    // ---------- 工具 ----------

    private fun message(
        clientMsgId: String,
        createdAt: Long = 100,
        status: Int = MessageStatus.SENT.code,
        serverId: Long? = null,
        peerId: Long = this.peer,
        content: String = "内容",
    ) = MessageEntity(
        ownerId = me,
        clientMsgId = clientMsgId,
        serverId = serverId,
        peerId = peerId,
        senderId = peerId,
        outgoing = false,
        content = content,
        status = status,
        createdAt = createdAt,
        updatedAt = createdAt,
    )

    private fun conversation(
        peerId: Long,
        unread: Int = 0,
        lastMessageAt: Long = 100,
        preview: String = "你好",
        ownerId: Long = me,
    ) = ConversationEntity(
        ownerId = ownerId,
        peerId = peerId,
        lastMessagePreview = preview,
        lastMessageAt = lastMessageAt,
        unread = unread,
    )
}
