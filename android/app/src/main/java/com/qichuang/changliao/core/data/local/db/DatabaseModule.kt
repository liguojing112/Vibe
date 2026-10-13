package com.qichuang.changliao.core.data.local.db

import android.content.Context
import androidx.room.Room
import com.qichuang.changliao.core.data.local.db.dao.ConversationDao
import com.qichuang.changliao.core.data.local.db.dao.MessageDao
import com.qichuang.changliao.core.data.local.db.dao.SyncStateDao
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

/**
 * 本地库装配（docs/10 第 1 节）。
 *
 * **不挂 `fallbackToDestructiveMigration`**：宁可升级时报错，也不能悄悄把用户的聊天记录清掉
 * （docs/10 第 8 节第 3 条要求从第一天就做迁移，正是为了这个）。
 */
@Module
@InstallIn(SingletonComponent::class)
object DatabaseModule {

    @Provides
    @Singleton
    fun provideClDatabase(@ApplicationContext context: Context): ClDatabase =
        Room.databaseBuilder(context, ClDatabase::class.java, ClDatabase.NAME)
            .addMigrations(*ClDatabase.MIGRATIONS)
            .build()

    @Provides
    fun provideSyncStateDao(database: ClDatabase): SyncStateDao = database.syncStateDao()

    @Provides
    fun provideMessageDao(database: ClDatabase): MessageDao = database.messageDao()

    @Provides
    fun provideConversationDao(database: ClDatabase): ConversationDao = database.conversationDao()
}
