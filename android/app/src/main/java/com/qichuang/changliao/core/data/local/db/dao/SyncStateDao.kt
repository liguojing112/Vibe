package com.qichuang.changliao.core.data.local.db.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity

@Dao
interface SyncStateDao {

    @Query("SELECT lastSyncedMessageId FROM sync_state WHERE conversationId = :conversationId")
    suspend fun lastSyncedMessageId(conversationId: Long): Long?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(state: SyncStateEntity)
}
