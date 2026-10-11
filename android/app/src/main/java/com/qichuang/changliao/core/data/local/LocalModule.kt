package com.qichuang.changliao.core.data.local

import com.qichuang.changliao.core.network.TokenStore
import dagger.Binds
import dagger.Module
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

/**
 * 本地存储装配（docs/02 第 4.3 节）。
 */
@Module
@InstallIn(SingletonComponent::class)
abstract class LocalModule {

    @Binds
    @Singleton
    abstract fun bindTokenStore(impl: ClDataStore): TokenStore
}
