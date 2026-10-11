package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.Dp
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 头像占位色（docs/03 第 6 节第 7 条：统一占位色 `#E8D8C7`，不在颜色 token 表里）。 */
private val AvatarPlaceholder = Color(0xFFE8D8C7)

/**
 * 圆形头像（docs/03 第 5.3 节）：可选在线绿点、VIP 角标，加载失败/没图显示默认头像。
 *
 * 在线点放右下角、VIP 角标放右上角（避免两者重叠）。
 */
@Composable
fun ClAvatar(
    url: String?,
    modifier: Modifier = Modifier,
    size: Dp = ClTheme.size.avatarS,
    online: Boolean = false,
    vip: Boolean = false,
    contentDescription: String? = null,
) {
    Box(modifier = modifier.size(size)) {
        AsyncImage(
            model = url,
            contentDescription = contentDescription,
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .fillMaxSize()
                .clip(ClTheme.shapes.circle)
                .background(AvatarPlaceholder),
        )

        if (online) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .size(ClTheme.size.onlineDot)
                    .clip(ClTheme.shapes.circle)
                    .background(ClTheme.colors.surface),
                contentAlignment = Alignment.Center,
            ) {
                Box(
                    modifier = Modifier
                        .size(ClTheme.size.onlineDot - ClTheme.spacing.xxs)
                        .clip(ClTheme.shapes.circle)
                        .background(ClTheme.colors.onlineDot),
                )
            }
        }

        if (vip) {
            ClVipBadge(active = true, modifier = Modifier.align(Alignment.TopEnd))
        }
    }
}
