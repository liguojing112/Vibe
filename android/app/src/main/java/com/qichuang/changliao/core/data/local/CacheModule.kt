package com.qichuang.changliao.core.data.local

import android.content.Context
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import java.io.File
import javax.inject.Qualifier
import javax.inject.Singleton

/** 列表缓存的根目录（`filesDir/cl_cache`）。 */
@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class CacheDir

/**
 * 缓存目录装配。给 [LocalCache] 注入的是**目录**而不是 `Context`，
 * 这样它就是一个纯 JVM 类，单元测试直接传一个临时目录就能跑。
 */
@Module
@InstallIn(SingletonComponent::class)
object CacheModule {

    @Provides
    @Singleton
    @CacheDir
    fun provideCacheDir(@ApplicationContext context: Context): File =
        File(context.filesDir, "cl_cache").apply { mkdirs() }
}
