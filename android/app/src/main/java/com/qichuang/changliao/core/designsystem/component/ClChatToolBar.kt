package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 工具栏里的一项（例如「语音通话」「送礼物」）。 */
data class ClChatToolItem(
    val key: String,
    val label: String,
    val icon: ImageVector,
)

/**
 * 聊天工具栏（docs/03 第 5.4 节，设计图 06）：等宽奶黄色按钮，图标 + 文字。
 *
 * 开关关闭时不要传语音/视频这两项（`feature.call_enabled`，docs/01 第 4 节）。
 */
@Composable
fun ClChatToolBar(
    items: List<ClChatToolItem>,
    onSelect: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .background(ClTheme.colors.surface)
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.s),
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        items.forEach { item ->
            ClChatToolButton(
                item = item,
                modifier = Modifier.weight(1f),
                onClick = { onSelect(item.key) },
            )
        }
    }
}

@Composable
private fun RowScope.ClChatToolButton(
    item: ClChatToolItem,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Row(
        modifier = modifier
            .height(ClTheme.size.buttonHeight)
            .clip(ClTheme.shapes.cardSmall)
            .background(ClTheme.colors.bgCream)
            .clickable(onClick = onClick),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center,
    ) {
        Icon(
            imageVector = item.icon,
            contentDescription = null,
            tint = ClTheme.colors.ink,
            modifier = Modifier.size(ClTheme.size.iconS),
        )
        Text(
            text = item.label,
            style = ClTheme.typography.body,
            color = ClTheme.colors.ink,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(start = ClTheme.spacing.xs),
        )
    }
}
