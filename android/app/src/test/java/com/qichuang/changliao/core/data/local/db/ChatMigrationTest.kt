package com.qichuang.changliao.core.data.local.db

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import androidx.room.Room
import androidx.sqlite.db.SupportSQLiteDatabase
import androidx.sqlite.db.SupportSQLiteOpenHelper
import androidx.sqlite.db.framework.FrameworkSQLiteOpenHelperFactory
import androidx.test.core.app.ApplicationProvider
import com.qichuang.changliao.core.data.local.db.entity.ConversationEntity
import com.qichuang.changliao.core.data.local.db.entity.MessageEntity
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity
import com.qichuang.changliao.core.data.model.MessageStatus
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * v1 → v2 迁移（T4.5 / docs/10 第 8 节第 3 条）。**这是 `MIGRATION_1_2` 的回归测试。**
 *
 * 做法是把真事重演一遍：先手工建一个 **v1 的库文件**（只有 `sync_state`，而且**没有** `room_master_table`
 * —— 当年那份骨架就是这样，从来没有代码写过它），再用 Room 打开它。Room 会：
 * 1. 看到 `user_version = 1` → 跑 `MIGRATION_1_2`；
 * 2. 跑完**拿实体生成的结构去校验**（对不上直接抛 `IllegalStateException: Migration didn't properly handle`）；
 * 3. 通过才写 `room_master_table` 的 identity hash。
 *
 * 所以这个用例一旦过，就说明迁移语句和实体是严格一致的 —— 这正是手写迁移最容易错的地方。
 */
@RunWith(RobolectricTestRunner::class)
class ChatMigrationTest {

    private val context: Context get() = ApplicationProvider.getApplicationContext()

    private val dbName = "migration-test.db"

    @Test
    fun `v1 升到 v2 —— 建出新表、老游标数据保留、schema 校验通过`() = runTest {
        createVersion1Database()

        val db = Room.databaseBuilder(context, ClDatabase::class.java, dbName)
            .addMigrations(*ClDatabase.MIGRATIONS)
            .allowMainThreadQueries()
            .build()

        // 打开就会触发迁移 + schema 校验；对不上这里会直接抛异常
        assertEquals(
            "v1 里的游标要原样留在 ownerId=0 名下",
            5L,
            db.syncStateDao().lastSyncedMessageId(0L, 1L),
        )

        // 新表真能用
        db.messageDao().insert(
            MessageEntity(
                ownerId = 101,
                clientMsgId = "after-migration",
                peerId = 102,
                senderId = 102,
                content = "迁移之后写的",
                status = MessageStatus.SENT.code,
                createdAt = 1,
                updatedAt = 1,
            ),
        )
        db.conversationDao().upsert(
            ConversationEntity(ownerId = 101, peerId = 102, lastMessageAt = 1, unread = 1),
        )
        assertEquals(1, db.messageDao().countByPeer(101, 102))
        assertEquals(1, db.conversationDao().byPeerId(101, 102)!!.unread)

        // sync_state 的主键换成了 (ownerId, conversationId)
        db.syncStateDao().upsert(
            SyncStateEntity(
                ownerId = 101,
                conversationId = SyncStateEntity.GLOBAL_CURSOR,
                lastSyncedMessageId = 54,
            ),
        )
        db.close()

        assertDatabaseVersion(2)
        assertTableExists("message")
        assertTableExists("conversation")
        assertTrue("Room 校验通过后才会写 identity hash", tableExists("room_master_table"))
    }

    // ---------- 工具 ----------

    /** 建一个和当年那份骨架一样的 v1 库：只有 `sync_state`，主键是 `conversationId`。 */
    private fun createVersion1Database() {
        val configuration = SupportSQLiteOpenHelper.Configuration.builder(context)
            .name(dbName)
            .callback(object : SupportSQLiteOpenHelper.Callback(1) {
                override fun onCreate(db: SupportSQLiteDatabase) {
                    db.execSQL(
                        "CREATE TABLE IF NOT EXISTS `sync_state` (" +
                            "`conversationId` INTEGER NOT NULL, " +
                            "`lastSyncedMessageId` INTEGER NOT NULL, " +
                            "PRIMARY KEY(`conversationId`))",
                    )
                    db.execSQL("INSERT INTO `sync_state` (`conversationId`, `lastSyncedMessageId`) VALUES (1, 5)")
                }

                override fun onUpgrade(db: SupportSQLiteDatabase, oldVersion: Int, newVersion: Int) = Unit
            })
            .build()

        FrameworkSQLiteOpenHelperFactory().create(configuration).writableDatabase.close()
        assertEquals(1, rawDatabaseVersion())
    }

    private fun openRaw(): SQLiteDatabase =
        SQLiteDatabase.openDatabase(context.getDatabasePath(dbName).path, null, SQLiteDatabase.OPEN_READONLY)

    /** 直接读 `PRAGMA user_version`：迁移没发生的话这里还是 1。 */
    private fun rawDatabaseVersion(): Int = openRaw().use { db ->
        db.rawQuery("PRAGMA user_version", null).use { cursor ->
            cursor.moveToFirst()
            cursor.getInt(0)
        }
    }

    private fun assertDatabaseVersion(expected: Int) = assertEquals(expected, rawDatabaseVersion())

    private fun tableExists(name: String): Boolean = openRaw().use { db ->
        db.rawQuery("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", arrayOf(name)).use {
            it.count > 0
        }
    }

    private fun assertTableExists(name: String) = assertTrue("表 $name 应该存在", tableExists(name))
}
