package com.qichuang.changliao.core.data.local.db

import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase

/**
 * v1 → v2：加聊天记录的两张表，并给 `sync_state` 补上 `ownerId`（docs/10 第 1.2 节）。
 *
 * v1 里只有 `sync_state`（T2.6 铺的骨架，**当时没有任何代码写过它**），所以这次迁移：
 * 1. 新建 `message` / `conversation`；
 * 2. `sync_state` 的主键从 `conversationId` 改成 `(ownerId, conversationId)`
 *    —— 同一台设备换账号时，两个账号的同步游标不能互相顶掉。
 *    SQLite 不支持改主键，所以走「建新表 → 拷数据 → 删旧表 → 改名」这套。
 *
 * ⚠️ 建表语句必须和 Room 由实体生成的**完全一致**（列名、类型、NOT NULL、索引名、主键顺序），
 * 否则打开数据库时会报 schema 不匹配。**照 `app/schemas/.../2.json` 抄，别凭记忆写。**
 * 索引名也是 Room 自己起的：单列 `index_<表>_<列>`，多列 `index_<表>_<列1>_<列2>`。
 */
val MIGRATION_1_2: Migration = object : Migration(1, 2) {
    override fun migrate(db: SupportSQLiteDatabase) {
        // ---------- message ----------
        db.execSQL(
            "CREATE TABLE IF NOT EXISTS `message` (" +
                "`localId` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, " +
                "`ownerId` INTEGER NOT NULL, " +
                "`clientMsgId` TEXT NOT NULL, " +
                "`serverId` INTEGER, " +
                "`conversationId` INTEGER, " +
                "`peerId` INTEGER NOT NULL, " +
                "`senderId` INTEGER NOT NULL, " +
                "`outgoing` INTEGER NOT NULL, " +
                "`type` INTEGER NOT NULL, " +
                "`content` TEXT NOT NULL, " +
                "`localMediaPath` TEXT, " +
                "`status` INTEGER NOT NULL, " +
                "`priceCoins` INTEGER NOT NULL, " +
                "`createdAt` INTEGER NOT NULL, " +
                "`updatedAt` INTEGER NOT NULL)",
        )
        db.execSQL("CREATE INDEX IF NOT EXISTS `index_message_ownerId_peerId_createdAt` ON `message` (`ownerId`, `peerId`, `createdAt`)")
        db.execSQL("CREATE UNIQUE INDEX IF NOT EXISTS `index_message_clientMsgId` ON `message` (`clientMsgId`)")
        db.execSQL("CREATE INDEX IF NOT EXISTS `index_message_serverId` ON `message` (`serverId`)")

        // ---------- conversation ----------
        db.execSQL(
            "CREATE TABLE IF NOT EXISTS `conversation` (" +
                "`ownerId` INTEGER NOT NULL, " +
                "`peerId` INTEGER NOT NULL, " +
                "`serverConversationId` INTEGER, " +
                "`peerNickname` TEXT NOT NULL, " +
                "`peerAvatar` TEXT NOT NULL, " +
                "`lastMessagePreview` TEXT NOT NULL, " +
                "`lastMessageAt` INTEGER NOT NULL, " +
                "`unread` INTEGER NOT NULL, " +
                "`deleted` INTEGER NOT NULL, " +
                "PRIMARY KEY(`ownerId`, `peerId`))",
        )

        // ---------- sync_state：换主键 ----------
        db.execSQL(
            "CREATE TABLE IF NOT EXISTS `sync_state_new` (" +
                "`ownerId` INTEGER NOT NULL, " +
                "`conversationId` INTEGER NOT NULL, " +
                "`lastSyncedMessageId` INTEGER NOT NULL, " +
                "PRIMARY KEY(`ownerId`, `conversationId`))",
        )
        // 老行没有 ownerId，统一归到 0 名下（v1 没有任何代码写过这张表，这里只是把姿态做对）
        db.execSQL(
            "INSERT INTO `sync_state_new` (`ownerId`, `conversationId`, `lastSyncedMessageId`) " +
                "SELECT 0, `conversationId`, `lastSyncedMessageId` FROM `sync_state`",
        )
        db.execSQL("DROP TABLE `sync_state`")
        db.execSQL("ALTER TABLE `sync_state_new` RENAME TO `sync_state`")
    }
}
