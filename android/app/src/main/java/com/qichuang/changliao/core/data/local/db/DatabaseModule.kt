package com.qichuang.changliao.core.data.local.db

import android.content.Context
import androidx.room.Room
import com.qichuang.changliao.core.data.local.db.dao.SyncStateDao
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

/**
 * 本地库装配（docs/10 第 1 节）。
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
}
