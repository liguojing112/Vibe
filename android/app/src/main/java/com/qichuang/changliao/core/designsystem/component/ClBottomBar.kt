package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.layout.windowInsetsPadding
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 底部导航的一项。
 */
data class ClBottomBarItem(
    val key: String,
    val label: String,
    val icon: ImageVector,
)

/**
 * 底部导航（docs/03 第 5.1 节，对应原型 `.bottom`）：选中项图标有 primary 圆角底。
 *
 * 未读数用 [badges] 传（key = item.key）。
 */
@Composable
fun ClBottomBar(
    items: List<ClBottomBarItem>,
    selected: String,
    modifier: Modifier = Modifier,
    badges: Map<String, Int> = emptyMap(),
    onSelect: (String) -> Unit,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(ClTheme.colors.surface)
            // 背景铺到系统导航栏下面，内容避开它（targetSdk 35+ 强制边到边）
            .windowInsetsPadding(WindowInsets.navigationBars),
    ) {
        // 顶部分割线
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(1.dp)
                .background(ClTheme.colors.line),
        )

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(ClTheme.size.bottomBarHeight),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            items.forEach { item ->
                val isSelected = item.key == selected
                Column(
                    modifier = Modifier
                        .weight(1f)
                        .clickable { onSelect(item.key) }
                        .padding(vertical = ClTheme.spacing.xs),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Box(
                            modifier = Modifier
                                .size(ClTheme.size.iconM + ClTheme.spacing.s)
                                .clip(ClTheme.shapes.pill)
                                .background(
                                    if (isSelected) ClTheme.colors.primary else androidx.compose.ui.graphics.Color.Transparent
                                ),
                        )
                        Icon(
                            imageVector = item.icon,
                            contentDescription = item.label,
                            tint = if (isSelected) ClTheme.colors.onPrimary else ClTheme.colors.inkSecondary,
                            modifier = Modifier.size(ClTheme.size.iconM),
                        )
                        val count = badges[item.key] ?: 0
                        if (count > 0) {
                            ClBadgeDot(
                                count = count,
                                modifier = Modifier.align(Alignment.TopEnd),
                            )
                        }
                    }

                    Text(
                        text = item.label,
                        style = ClTheme.typography.tiny,
                        color = if (isSelected) ClTheme.colors.primaryText else ClTheme.colors.inkSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}
