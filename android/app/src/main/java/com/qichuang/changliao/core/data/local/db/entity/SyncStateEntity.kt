package com.qichuang.changliao.core.data.local.db.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * 同步游标（docs/10 第 1.2 节）：每个会话最后同步到的消息 id。
 */
@Entity(tableName = "sync_state")
data class SyncStateEntity(
    @PrimaryKey
    val conversationId: Long,
    val lastSyncedMessageId: Long = 0,
)
