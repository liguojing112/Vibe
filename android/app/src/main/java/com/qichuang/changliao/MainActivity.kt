package com.qichuang.changliao

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.navigation.ClNavHost
import com.qichuang.changliao.core.navigation.Routes
import com.qichuang.changliao.core.network.TokenStore
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject

/**
 * 唯一的 Activity（docs/02 第 4.1 节）。
 *
 * 起始页由本地 token 决定：没登录进 P00 登录页，已登录直接进主界面。
 */
@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    @Inject
    lateinit var tokenStore: TokenStore

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            ClTheme {
                val startDestination = remember {
                    if (tokenStore.accessToken() == null) Routes.LOGIN else Routes.MAIN
                }
                ClNavHost(
                    startDestination = startDestination,
                    modifier = Modifier.fillMaxSize(),
                )
            }
        }
    }
}
