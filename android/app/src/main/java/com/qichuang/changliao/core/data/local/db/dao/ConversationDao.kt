package com.qichuang.changliao.core.data.local.db.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.qichuang.changliao.core.data.local.db.entity.ConversationEntity
import kotlinx.coroutines.flow.Flow

/**
 * 本地会话表（docs/10 第 1.2 节）：P03 的列表和底部未读角标都从这里读。
 */
@Dao
interface ConversationDao {

    @Query(
        "SELECT * FROM conversation WHERE ownerId = :ownerId AND deleted = 0 " +
            "ORDER BY lastMessageAt DESC, peerId DESC",
    )
    fun all(ownerId: Long): Flow<List<ConversationEntity>>

    @Query("SELECT * FROM conversation WHERE ownerId = :ownerId AND peerId = :peerId LIMIT 1")
    suspend fun byPeerId(ownerId: Long, peerId: Long): ConversationEntity?

    /** 底部导航的角标（只算没被我删掉的会话）。 */
    @Query("SELECT COALESCE(SUM(unread), 0) FROM conversation WHERE ownerId = :ownerId AND deleted = 0")
    fun unreadTotal(ownerId: Long): Flow<Int>

    /**
     * 写入/覆盖一条会话。
     *
     * 用 REPLACE 是故意的：**未读数和最后一条消息以服务端为准**，刷新时整体覆盖。
     */
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(entity: ConversationEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(entities: List<ConversationEntity>)

    /** 清掉这个会话的未读（进聊天页时调用）。 */
    @Query("UPDATE conversation SET unread = 0 WHERE ownerId = :ownerId AND peerId = :peerId")
    suspend fun clearUnread(ownerId: Long, peerId: Long)

    /** 删除会话（只删本地这一侧，服务端那边另外调接口）。 */
    @Query("UPDATE conversation SET deleted = 1, unread = 0 WHERE ownerId = :ownerId AND peerId = :peerId")
    suspend fun hide(ownerId: Long, peerId: Long)

    /** 对方来新消息时把删除标记清掉 —— 会话要重新出现（docs/01 P06）。 */
    @Query("UPDATE conversation SET deleted = 0 WHERE ownerId = :ownerId AND peerId = :peerId")
    suspend fun unhide(ownerId: Long, peerId: Long)
}
