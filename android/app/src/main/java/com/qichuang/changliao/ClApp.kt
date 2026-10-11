package com.qichuang.changliao

import android.app.Application
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import dagger.hilt.android.HiltAndroidApp
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltAndroidApp
class ClApp : Application() {

    @Inject
    lateinit var appConfigRepository: AppConfigRepository

    override fun onCreate() {
        super.onCreate()
        // 启动就拉一次启动配置（docs/01 第 4 节）：界面等不到它也不会卡住，
        // 只会先按本地缓存的配置渲染，拿到新的再切。
        CoroutineScope(SupervisorJob() + Dispatchers.IO).launch {
            appConfigRepository.refresh()
        }
    }
}
