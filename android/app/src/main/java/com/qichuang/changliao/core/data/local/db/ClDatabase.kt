package com.qichuang.changliao.core.data.local.db

import androidx.room.Database
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import com.qichuang.changliao.core.data.local.db.dao.ConversationDao
import com.qichuang.changliao.core.data.local.db.dao.MessageDao
import com.qichuang.changliao.core.data.local.db.dao.SyncStateDao
import com.qichuang.changliao.core.data.local.db.entity.ConversationEntity
import com.qichuang.changliao.core.data.local.db.entity.MessageEntity
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity

/**
 * 聊天记录本地库（docs/10 第 1 节）。**本地库是聊天记录的主数据源**，服务端只做转发与监管。
 *
 * - v1：只有 `sync_state`（T2.6 铺的骨架）
 * - v2（T4.5）：加 `message` / `conversation`
 *
 * **从第 1 天就带迁移**（docs/10 第 8 节第 3 条）：新增表/字段时往 [MIGRATIONS] 里加一条、version +1，
 * 不要等到上线后才补。迁移 SQL 必须和 Room 由实体生成的建表语句**逐字一致**，
 * 对不上时 Room 会在打开数据库时报 schema 不匹配。
 * （`app/schemas/` 下是 Room 导出的权威结构，写迁移照着它抄，不要凭记忆写。）
 */
@Database(
    entities = [
        SyncStateEntity::class,
        MessageEntity::class,
        ConversationEntity::class,
    ],
    version = 2,
    exportSchema = true,
)
abstract class ClDatabase : RoomDatabase() {

    abstract fun syncStateDao(): SyncStateDao

    abstract fun messageDao(): MessageDao

    abstract fun conversationDao(): ConversationDao

    companion object {
        const val NAME = "cl.db"

        val MIGRATIONS: Array<Migration> = arrayOf(MIGRATION_1_2)
    }
}
