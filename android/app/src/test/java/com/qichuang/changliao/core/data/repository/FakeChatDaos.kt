package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.local.db.dao.ConversationDao
import com.qichuang.changliao.core.data.local.db.dao.MessageDao
import com.qichuang.changliao.core.data.local.db.dao.SyncStateDao
import com.qichuang.changliao.core.data.local.db.entity.ConversationEntity
import com.qichuang.changliao.core.data.local.db.entity.MessageEntity
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.map

/**
 * 内存版的三个 DAO，给 JVM 单测用。
 *
 * 为什么不用 MockK：仓库层是**读改写**的逻辑（先查会话、再决定加不加未读、再写回），
 * mock 只能把每次调用的返回值写死，这种「状态在调用之间变化」的场景反而更难写、更容易写错。
 * 用内存实现能真的把流程跑一遍。
 *
 * 行为要和 Room 对齐的地方：`insert` 撞唯一索引（`clientMsgId`）时返回 -1，
 * 这正是聊天仓库用来「只给真正新插入的消息加未读」的依据。
 */
class FakeMessageDao : MessageDao {

    private val state = MutableStateFlow<List<MessageEntity>>(emptyList())

    private var nextId = 1L

    /** 便于断言：现在库里有哪些消息。 */
    val rows: List<MessageEntity> get() = state.value

    override fun byPeer(ownerId: Long, peerId: Long): Flow<List<MessageEntity>> =
        state.map { list ->
            list.filter { it.ownerId == ownerId && it.peerId == peerId }
                .sortedWith(compareBy({ it.createdAt }, { it.localId }))
        }

    override suspend fun byClientMsgId(clientMsgId: String): MessageEntity? =
        state.value.firstOrNull { it.clientMsgId == clientMsgId }

    override suspend fun byLocalId(localId: Long): MessageEntity? =
        state.value.firstOrNull { it.localId == localId }

    override suspend fun countByPeer(ownerId: Long, peerId: Long): Int =
        state.value.count { it.ownerId == ownerId && it.peerId == peerId }

    override suspend fun oldestServerId(ownerId: Long, peerId: Long): Long? =
        state.value.filter { it.ownerId == ownerId && it.peerId == peerId && (it.serverId ?: 0L) > 0 }
            .minOfOrNull { it.serverId ?: 0L }

    override suspend fun insert(entity: MessageEntity): Long {
        // Room 的唯一索引（clientMsgId）在这里等价成这一步
        if (state.value.any { it.clientMsgId == entity.clientMsgId }) {
            return -1L
        }
        val id = if (entity.localId > 0) entity.localId else nextId++
        state.value = state.value + entity.copy(localId = id)
        return id
    }

    override suspend fun insertAll(entities: List<MessageEntity>) {
        entities.forEach { insert(it) }
    }

    override suspend fun update(entity: MessageEntity) {
        state.value = state.value.map { if (it.localId == entity.localId) entity else it }
    }

    override suspend fun markSent(
        localId: Long,
        serverId: Long,
        conversationId: Long,
        content: String,
        status: Int,
        priceCoins: Int,
        createdAt: Long,
        updatedAt: Long,
    ) {
        state.value = state.value.map {
            if (it.localId == localId) {
                it.copy(
                    serverId = serverId,
                    conversationId = conversationId,
                    content = content,
                    status = status,
                    priceCoins = priceCoins,
                    createdAt = createdAt,
                    updatedAt = updatedAt,
                )
            } else {
                it
            }
        }
    }

    override suspend fun updateStatus(localId: Long, status: Int, updatedAt: Long) {
        state.value = state.value.map {
            if (it.localId == localId) it.copy(status = status, updatedAt = updatedAt) else it
        }
    }

    override suspend fun delete(localId: Long) {
        state.value = state.value.filterNot { it.localId == localId }
    }

    override suspend fun markStale(from: Int, to: Int, before: Long, now: Long): Int {
        val stale = state.value.filter { it.status == from && it.createdAt < before }
        state.value = state.value.map {
            if (it.status == from && it.createdAt < before) it.copy(status = to, updatedAt = now) else it
        }
        return stale.size
    }
}

class FakeConversationDao : ConversationDao {

    private val state = MutableStateFlow<List<ConversationEntity>>(emptyList())

    val rows: List<ConversationEntity> get() = state.value

    override fun all(ownerId: Long): Flow<List<ConversationEntity>> =
        state.map { list ->
            list.filter { it.ownerId == ownerId && !it.deleted }
                .sortedWith(compareByDescending<ConversationEntity> { it.lastMessageAt }.thenByDescending { it.peerId })
        }

    override suspend fun byPeerId(ownerId: Long, peerId: Long): ConversationEntity? =
        state.value.firstOrNull { it.ownerId == ownerId && it.peerId == peerId }

    override fun unreadTotal(ownerId: Long): Flow<Int> =
        state.map { list -> list.filter { it.ownerId == ownerId && !it.deleted }.sumOf { it.unread } }

    override suspend fun upsert(entity: ConversationEntity) {
        state.value = state.value.filterNot { it.ownerId == entity.ownerId && it.peerId == entity.peerId } + entity
    }

    override suspend fun upsertAll(entities: List<ConversationEntity>) {
        entities.forEach { upsert(it) }
    }

    override suspend fun clearUnread(ownerId: Long, peerId: Long) {
        state.value = state.value.map {
            if (it.ownerId == ownerId && it.peerId == peerId) it.copy(unread = 0) else it
        }
    }

    override suspend fun hide(ownerId: Long, peerId: Long) {
        state.value = state.value.map {
            if (it.ownerId == ownerId && it.peerId == peerId) it.copy(deleted = true, unread = 0) else it
        }
    }

    override suspend fun unhide(ownerId: Long, peerId: Long) {
        state.value = state.value.map {
            if (it.ownerId == ownerId && it.peerId == peerId) it.copy(deleted = false) else it
        }
    }
}

class FakeSyncStateDao : SyncStateDao {

    private val rows = mutableMapOf<Pair<Long, Long>, Long>()

    override suspend fun lastSyncedMessageId(ownerId: Long, conversationId: Long): Long? =
        rows[ownerId to conversationId]

    override suspend fun upsert(state: SyncStateEntity) {
        rows[state.ownerId to state.conversationId] = state.lastSyncedMessageId
    }

    fun globalCursor(ownerId: Long): Long = rows[ownerId to SyncStateEntity.GLOBAL_CURSOR] ?: 0L
}
