package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 4 格统计行（docs/03 第 5.3 节，「我的」页）：我关注的 / 好友 / 关注我的 / 访客。
 */
@Composable
fun ClStatRow(
    modifier: Modifier = Modifier,
    content: @Composable RowScope.() -> Unit,
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        content = content,
    )
}

/**
 * 统计格（docs/03 第 5.3 节）：上面数字、下面标签。
 */
@Composable
fun ClStatCell(
    number: String,
    label: String,
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
) {
    Column(
        modifier = modifier
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
    ) {
        Text(
            text = number,
            style = ClTheme.typography.cardTitle,
            color = ClTheme.colors.ink,
            textAlign = TextAlign.Center,
            maxLines = 1,
        )
        Text(
            text = label,
            style = ClTheme.typography.tiny,
            color = ClTheme.colors.muted,
            textAlign = TextAlign.Center,
            maxLines = 1,
        )
    }
}
