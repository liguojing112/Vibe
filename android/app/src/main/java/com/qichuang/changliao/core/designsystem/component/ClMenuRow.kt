package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 菜单分组（docs/03 第 5.3 节）：「我的」页菜单等。
 */
@Composable
fun ClMenuGroup(
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(ClTheme.colors.surface),
        content = content,
    )
}

/**
 * 菜单行（docs/03 第 5.3 节，对应原型 `.menu-row`）：高度 `menuRowHeight`(55dp)。
 */
@Composable
fun ClMenuRow(
    icon: ImageVector,
    label: String,
    modifier: Modifier = Modifier,
    iconTint: Color? = null,
    extra: String? = null,
    showChevron: Boolean = true,
    showDivider: Boolean = true,
    onClick: (() -> Unit)? = null,
) {
    Column(modifier = modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(ClTheme.size.menuRowHeight)
                .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
                .padding(horizontal = ClTheme.spacing.l),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = iconTint ?: ClTheme.colors.inkSecondary,
                modifier = Modifier.size(ClTheme.size.iconM),
            )

            Text(
                text = label,
                style = ClTheme.typography.body,
                color = ClTheme.colors.ink,
                maxLines = 1,
                modifier = Modifier.weight(1f),
            )

            if (extra != null) {
                Text(
                    text = extra,
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.muted,
                    maxLines = 1,
                )
            }

            if (showChevron) {
                Icon(
                    imageVector = ClIcons.chevron,
                    contentDescription = null,
                    tint = ClTheme.colors.muted,
                    modifier = Modifier.size(ClTheme.size.iconS),
                )
            }
        }

        if (showDivider) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(1.dp)
                    .background(ClTheme.colors.line),
            )
        }
    }
}
