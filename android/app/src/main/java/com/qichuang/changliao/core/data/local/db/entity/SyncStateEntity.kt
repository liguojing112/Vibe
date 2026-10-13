package com.qichuang.changliao.core.data.local.db.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * 同步游标（docs/10 第 1.2 节）。
 *
 * 服务端的 `/chat/sync?after_id=` 是**全局**的（「我收到的、id 大于它的消息」，docs/05 第 4.5 节），
 * 所以这里用 [GLOBAL_CURSOR] 这一行存全局游标；留 [conversationId] 这一维是给以后
 * 按会话单独记游标留的位置（本地清理、个别会话重拉时要用）。
 *
 * [ownerId] 是必须的：同一台设备换账号登录时，两个账号的游标不能互相顶掉
 * （否则 B 一登录就从 A 的进度开始，中间的消息永远补不回来）。
 */
@Entity(tableName = "sync_state", primaryKeys = ["ownerId", "conversationId"])
data class SyncStateEntity(
    val ownerId: Long = 0,
    val conversationId: Long = GLOBAL_CURSOR,
    val lastSyncedMessageId: Long = 0,
) {
    companion object {
        /** 全局游标用的伪会话 id。 */
        const val GLOBAL_CURSOR = 0L
    }
}
