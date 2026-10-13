package com.qichuang.changliao.core.data.local.db.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import com.qichuang.changliao.core.data.local.db.entity.MessageEntity
import kotlinx.coroutines.flow.Flow

/**
 * 本地消息表（docs/10 第 1.2 节）。
 *
 * 所有写入都用 `INSERT OR IGNORE`（唯一索引在 clientMsgId 上）：同一条消息可能从
 * WebSocket 推送、`/chat/sync` 补拉、`/chat/messages` 首次拉取三条路各来一次，去重就靠它。
 */
@Dao
interface MessageDao {

    /** 某个联系人的全部消息（升序，界面直接按这个顺序铺）。 */
    @Query("SELECT * FROM message WHERE ownerId = :ownerId AND peerId = :peerId ORDER BY createdAt ASC, localId ASC")
    fun byPeer(ownerId: Long, peerId: Long): Flow<List<MessageEntity>>

    @Query("SELECT * FROM message WHERE clientMsgId = :clientMsgId LIMIT 1")
    suspend fun byClientMsgId(clientMsgId: String): MessageEntity?

    /** 把「还没发出去的那条」找回来（重发用：换的是状态，不是 id）。 */
    @Query("SELECT * FROM message WHERE localId = :localId LIMIT 1")
    suspend fun byLocalId(localId: Long): MessageEntity?

    @Query("SELECT COUNT(*) FROM message WHERE ownerId = :ownerId AND peerId = :peerId")
    suspend fun countByPeer(ownerId: Long, peerId: Long): Int

    /** 本地最老一条有服务端 id 的消息，用来做 `before_id` 上拉游标。 */
    @Query(
        "SELECT MIN(serverId) FROM message WHERE ownerId = :ownerId AND peerId = :peerId " +
            "AND serverId IS NOT NULL AND serverId > 0",
    )
    suspend fun oldestServerId(ownerId: Long, peerId: Long): Long?

    @Insert(onConflict = OnConflictStrategy.IGNORE)
    suspend fun insert(entity: MessageEntity): Long

    @Insert(onConflict = OnConflictStrategy.IGNORE)
    suspend fun insertAll(entities: List<MessageEntity>)

    @Update
    suspend fun update(entity: MessageEntity)

    /**
     * 服务端确认后**就地替换那条本地记录**（保住 localId，界面不会闪一下）。
     */
    @Query(
        """
        UPDATE message SET serverId = :serverId, conversationId = :conversationId, content = :content,
            status = :status, priceCoins = :priceCoins, createdAt = :createdAt, updatedAt = :updatedAt
        WHERE localId = :localId
        """,
    )
    suspend fun markSent(
        localId: Long,
        serverId: Long,
        conversationId: Long,
        content: String,
        status: Int,
        priceCoins: Int,
        createdAt: Long,
        updatedAt: Long,
    )

    @Query("UPDATE message SET status = :status, updatedAt = :updatedAt WHERE localId = :localId")
    suspend fun updateStatus(localId: Long, status: Int, updatedAt: Long)

    /**
     * 删掉一条本地消息。
     *
     * 只在一个地方用：**余额不足（20001）时把刚写进去的乐观气泡撤掉** ——
     * 聊天页会按 P06 的要求把文字放回输入框，气泡留着的话用户重发一次就重复发了。
     */
    @Query("DELETE FROM message WHERE localId = :localId")
    suspend fun delete(localId: Long)

    /**
     * App 被杀时卡在 `sending` 的消息，下次启动超过 60 秒的改判失败（docs/10 第 1.3 节）。
     *
     * @return 改了几条
     */
    @Query("UPDATE message SET status = :to, updatedAt = :now WHERE status = :from AND createdAt < :before")
    suspend fun markStale(from: Int, to: Int, before: Long, now: Long): Int
}
