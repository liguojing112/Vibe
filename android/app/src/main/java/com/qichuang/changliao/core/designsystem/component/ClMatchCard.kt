package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.style.TextOverflow
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 照片占位色，与头像统一（docs/03 第 6 节第 7 条）。 */
private val MatchPhotoPlaceholder = Color(0xFFE8D8C7)
private const val MATCH_CARD_RATIO = 0.74f

/** [ClMatchCard] 要展示的人（docs/01 P02）。 */
data class ClMatchCardData(
    val name: String,
    val photoUrl: String? = null,
    val meta: String = "",
)

/**
 * 速配两列网格卡片（docs/03 第 5.4 节，设计图 02）：照片铺满整卡，
 * 左上角「可视频」状态胶囊，底部渐隐上盖照片与操作按钮。
 */
@Composable
fun ClMatchCard(
    user: ClMatchCardData,
    actionText: String,
    modifier: Modifier = Modifier,
    available: Boolean = true,
    availableText: String = "可视频",
    actionIcon: ImageVector = ClIcons.video,
    onAction: () -> Unit,
    onClick: () -> Unit,
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .aspectRatio(MATCH_CARD_RATIO)
            .clip(ClTheme.shapes.card)
            .background(MatchPhotoPlaceholder)
            .clickable(onClick = onClick),
    ) {
        AsyncImage(
            model = user.photoUrl,
            contentDescription = user.name,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize(),
        )

        // 底部渐隐，保证白字能看清
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        listOf(Color.Transparent, ClTheme.colors.mask),
                    ),
                ),
        )

        if (available) {
            Row(
                modifier = Modifier
                    .align(Alignment.TopStart)
                    .padding(ClTheme.spacing.s)
                    .clip(ClTheme.shapes.pill)
                    .background(ClTheme.colors.surface)
                    .padding(horizontal = ClTheme.spacing.s, vertical = ClTheme.spacing.xxs),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
            ) {
                Box(
                    modifier = Modifier
                        .size(ClTheme.spacing.s)
                        .clip(ClTheme.shapes.circle)
                        .background(ClTheme.colors.onlineDot),
                )
                Text(
                    text = availableText,
                    style = ClTheme.typography.micro,
                    color = ClTheme.colors.ink,
                )
            }
        }

        Column(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .fillMaxWidth()
                .padding(ClTheme.spacing.s),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs)) {
                if (user.meta.isNotEmpty()) {
                    Text(
                        text = user.meta,
                        style = ClTheme.typography.micro,
                        color = ClTheme.colors.surface,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                Text(
                    text = user.name,
                    style = ClTheme.typography.cardTitle,
                    color = ClTheme.colors.surface,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(ClTheme.size.buttonHeightSmall)
                    .clip(ClTheme.shapes.pill)
                    .background(ClTheme.colors.primary)
                    .clickable(onClick = onAction)
                    .padding(horizontal = ClTheme.spacing.s),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.Center,
            ) {
                Icon(
                    imageVector = actionIcon,
                    contentDescription = null,
                    tint = ClTheme.colors.onPrimary,
                    modifier = Modifier.size(ClTheme.size.iconS),
                )
                Text(
                    text = actionText,
                    style = ClTheme.typography.buttonSmall,
                    color = ClTheme.colors.onPrimary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.padding(start = ClTheme.spacing.xs),
                )
            }
        }
    }
}
