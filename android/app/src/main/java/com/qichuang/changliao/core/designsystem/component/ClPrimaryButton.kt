package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 主按钮（docs/03 第 5.2 节）：黄底、`buttonHeight`、胶囊形。
 * 请求中显示 loading 并禁用（docs/03 第 6 节第 4 条）。
 */
@Composable
fun ClPrimaryButton(
    text: String,
    modifier: Modifier = Modifier,
    subText: String? = null,
    enabled: Boolean = true,
    loading: Boolean = false,
    fillMaxWidth: Boolean = true,
    onClick: () -> Unit,
) {
    val clickable = enabled && !loading
    Row(
        modifier = modifier
            .then(if (fillMaxWidth) Modifier.fillMaxWidth() else Modifier)
            .height(ClTheme.size.buttonHeight)
            .clip(ClTheme.shapes.pill)
            .background(if (clickable) ClTheme.colors.primary else ClTheme.colors.line)
            .clickable(enabled = clickable, onClick = onClick)
            .padding(horizontal = ClTheme.spacing.l),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (loading) {
            CircularProgressIndicator(
                modifier = Modifier.size(ClTheme.size.iconS),
                strokeWidth = 2.dp,
                color = ClTheme.colors.onPrimary,
            )
            return@Row
        }

        Text(
            text = text,
            style = ClTheme.typography.button,
            color = if (clickable) ClTheme.colors.onPrimary else ClTheme.colors.muted,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )

        if (subText != null) {
            Spacer(modifier = Modifier.width(ClTheme.spacing.xs))
            Text(
                text = subText,
                style = ClTheme.typography.buttonSmall,
                color = if (clickable) ClTheme.colors.onPrimary else ClTheme.colors.muted,
                maxLines = 1,
            )
        }
    }
}
