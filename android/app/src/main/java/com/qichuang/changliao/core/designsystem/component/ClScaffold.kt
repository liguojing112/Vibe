package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.statusBars
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 页面骨架（docs/03 第 5.1 节）：状态栏颜色 + 顶栏 + 内容 + 可选固定底部按钮区 + 可选底栏 + Toast 宿主。
 *
 * 页面里一律用它开头，不要自己拼 Column。
 *
 * `imePadding()` 是**键盘弹出时把整棵 Column 顶高**（配合 manifest 的 `adjustResize`）。
 * 没有键盘时它是 0，对没有输入框的页面没有任何影响 —— 放在骨架里统一做，
 * 省得每个有输入框的页面各自配一份（P06 聊天页就是这么踩过的：键盘一弹顶栏被平移出屏，
 * 「小满」和收费条整个消失，T9.6）。
 */
@Composable
fun ClScaffold(
    modifier: Modifier = Modifier,
    statusBarColor: Color = ClTheme.colors.bgCream,
    containerColor: Color = ClTheme.colors.surface,
    topBar: (@Composable () -> Unit)? = null,
    bottomBar: (@Composable () -> Unit)? = null,
    fixedBottom: (@Composable () -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    val toastState = remember { ClToastState() }

    CompositionLocalProvider(LocalClToastState provides toastState) {
        Box(
            modifier = modifier
                .fillMaxSize()
                .background(containerColor),
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .imePadding(),
            ) {
                ClStatusBarSpacer(color = statusBarColor)
                topBar?.invoke()
                Column(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth(),
                    content = content,
                )
                fixedBottom?.invoke()
                bottomBar?.invoke()
            }

            ClToastHost(state = toastState)
        }
    }
}

/** 状态栏区域用指定颜色填满（docs/03 第 6 节第 9 条）。 */
@Composable
private fun ClStatusBarSpacer(color: Color) {
    val top = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(top)
            .background(color),
    )
}
