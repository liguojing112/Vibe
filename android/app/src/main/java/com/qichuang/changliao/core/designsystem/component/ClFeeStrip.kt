package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 聊天收费条（docs/03 第 5.3 节，对应原型 `.fee-strip`）：文案由服务端拼好（docs/05 第 4.5 节）。
 */
@Composable
fun ClFeeStrip(
    text: String,
    modifier: Modifier = Modifier,
    actionText: String? = null,
    onAction: (() -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .background(ClTheme.colors.feeStripBg)
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.s),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            text = text,
            style = ClTheme.typography.caption,
            color = ClTheme.colors.feeStripText,
            maxLines = 1,
            modifier = Modifier.weight(1f),
        )
        if (actionText != null) {
            ClTextButton(
                text = actionText,
                color = ClTheme.colors.feeStripText,
                onClick = onAction,
            )
        }
    }
}
