package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import kotlinx.coroutines.delay
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** Toast 显示时长（docs/03 第 5.6 节：2.2 秒消失）。 */
private const val ToastDurationMillis = 2200L

/**
 * 深色胶囊 Toast 的状态（docs/03 第 5.6 节）。
 * 宿主由 [ClScaffold] 提供；页面里用 `rememberClToast()` 拿到它。
 */
@Stable
class ClToastState {
    internal var message by mutableStateOf<String?>(null)
    internal var version by mutableIntStateOf(0)

    fun show(text: String) {
        message = text
        version++
    }
}

internal val LocalClToastState = staticCompositionLocalOf<ClToastState?> { null }

/**
 * 取当前页面（[ClScaffold]）的 Toast 状态；没有 Scaffold 时退化为一个独立实例。
 */
@Composable
fun rememberClToast(): ClToastState {
    val fallback = remember { ClToastState() }
    return LocalClToastState.current ?: fallback
}

/**
 * Toast 宿主。只由 [ClScaffold] 使用。
 */
@Composable
internal fun ClToastHost(state: ClToastState, modifier: Modifier = Modifier) {
    val text = state.message
    val version = state.version
    var visible by remember { mutableStateOf(false) }

    LaunchedEffect(version) {
        if (text.isNullOrBlank()) {
            return@LaunchedEffect
        }
        visible = true
        delay(ToastDurationMillis)
        visible = false
    }

    if (!visible || text.isNullOrBlank()) {
        return
    }

    Box(modifier = modifier.fillMaxSize(), contentAlignment = Alignment.BottomCenter) {
        Box(
            modifier = Modifier
                .padding(bottom = ClTheme.spacing.xxxl)
                .clip(ClTheme.shapes.pill)
                .background(ClTheme.colors.ink)
                .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
        ) {
            Text(
                text = text,
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.surface,
            )
        }
    }
}
