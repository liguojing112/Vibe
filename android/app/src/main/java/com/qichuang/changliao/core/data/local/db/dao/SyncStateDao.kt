package com.qichuang.changliao.core.data.local.db.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity

/**
 * 同步游标（docs/10 第 1.2 节）。读游标、写游标的逻辑（含全局游标这一行的拼装）放在仓库层，
 * 这里只留最朴素的读写。
 */
@Dao
interface SyncStateDao {

    @Query("SELECT lastSyncedMessageId FROM sync_state WHERE ownerId = :ownerId AND conversationId = :conversationId")
    suspend fun lastSyncedMessageId(ownerId: Long, conversationId: Long): Long?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(state: SyncStateEntity)
}
