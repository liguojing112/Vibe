package com.qichuang.changliao.core.data.local.db

import androidx.room.Database
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import com.qichuang.changliao.core.data.local.db.dao.SyncStateDao
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity

/**
 * 聊天记录本地库（docs/10 第 1 节）。**本地库是聊天记录的主数据源**，服务端只做转发与监管。
 *
 * 现在只有 `sync_state`（同步游标）；T4.5 会加 `message`/`conversation` 并把 version 升到 2。
 * **从第 1 天就带迁移**（docs/10 第 8 节第 3 条）：新增表/字段时往 [MIGRATIONS] 里加一条、version +1，
 * 不要等到上线后才补。
 */
@Database(
    entities = [SyncStateEntity::class],
    version = 1,
    exportSchema = false,
)
abstract class ClDatabase : RoomDatabase() {

    abstract fun syncStateDao(): SyncStateDao

    companion object {
        const val NAME = "cl.db"

        val MIGRATIONS: Array<Migration> = emptyArray()
    }
}
