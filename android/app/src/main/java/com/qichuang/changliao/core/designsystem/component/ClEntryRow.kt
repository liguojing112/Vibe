package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.designsystem.theme.ClTheme

private const val ENTRY_ICON_ALPHA = 0.18f

/**
 * 消息页的功能入口行（docs/03 第 5.4 节，设计图 03）：
 * 圆形彩底图标 + 标题 + 说明 +（未读角标 或 右侧时间）。
 *
 * [tint] 同时决定图标颜色和圆形底色（底色是它的 18% 透明度），所以页面只传一个颜色 token。
 */
@Composable
fun ClEntryRow(
    icon: ImageVector,
    tint: Color,
    title: String,
    modifier: Modifier = Modifier,
    desc: String? = null,
    badge: Int = 0,
    trailingText: String? = null,
    showDivider: Boolean = true,
    onClick: (() -> Unit)? = null,
) {
    Box(modifier = modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
                .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier
                    .size(ClTheme.size.avatarXS + ClTheme.spacing.m)
                    .clip(ClTheme.shapes.circle)
                    .background(tint.copy(alpha = ENTRY_ICON_ALPHA)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = tint,
                    modifier = Modifier.size(ClTheme.size.iconM),
                )
            }

            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = ClTheme.spacing.m),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
            ) {
                Text(
                    text = title,
                    style = ClTheme.typography.cardTitle,
                    color = ClTheme.colors.ink,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                if (desc != null) {
                    Text(
                        text = desc,
                        style = ClTheme.typography.bodySmall,
                        color = ClTheme.colors.inkSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }

            if (badge > 0) {
                ClBadgeDot(count = badge)
            } else if (trailingText != null) {
                Text(
                    text = trailingText,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                    maxLines = 1,
                )
            }
        }

        if (showDivider) {
            ClCardDivider(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(start = ClTheme.spacing.l),
            )
        }
    }
}
