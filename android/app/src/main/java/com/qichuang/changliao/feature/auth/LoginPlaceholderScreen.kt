package com.qichuang.changliao.feature.auth

import androidx.compose.runtime.Composable
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClScaffold

/**
 * 登录页占位：T3.1 会换成真页面（P00 登录/注册）。
 *
 * 在它完成之前，先给一个「直接进主界面」的入口，方便调后面的页面。
 */
@Composable
fun LoginPlaceholderScreen(onEnter: () -> Unit) {
    ClScaffold {
        ClEmpty(
            title = "登录页开发中",
            desc = "P00 登录 / 注册 · 任务 T3.1",
            actionText = "先进入主界面",
            onAction = onEnter,
        )
    }
}
