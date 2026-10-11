package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 出错可重试（docs/03 第 5.6 节）：页面四态之一。
 * 网络错误统一显示「网络不太好，请稍后重试」（docs/03 第 6 节第 5 条）。
 */
@Composable
fun ClErrorRetry(
    message: String,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier = modifier.fillMaxSize(),
        contentAlignment = Alignment.Center,
    ) {
        Column(
            modifier = Modifier.padding(ClTheme.spacing.xxxl),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            Text(
                text = message,
                style = ClTheme.typography.body,
                color = ClTheme.colors.inkSecondary,
                textAlign = TextAlign.Center,
            )
            ClOutlineSmallButton(text = "重新加载", onClick = onRetry)
        }
    }
}
