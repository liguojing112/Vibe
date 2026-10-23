package com.qichuang.changliao.feature.moment.components

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
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
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.Moment
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClIconButton
import com.qichuang.changliao.core.designsystem.component.ClPhotoGrid
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 一条朋友圈：作者 → 正文 → 九宫格 → 点赞/评论。
 *
 * 点赞按钮**不做动画**：列表是长列表，每条都挂动画会明显掉帧，
 * 用颜色变化表示状态就够了。
 */
@Composable
fun MomentCard(
    moment: Moment,
    onToggleLike: () -> Unit,
    onComment: () -> Unit,
    onDelete: () -> Unit,
    onPreview: (index: Int) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            ClAvatar(
                url = moment.avatar,
                size = ClTheme.size.avatarS,
                contentDescription = moment.nickname,
            )
            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = ClTheme.spacing.s),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
            ) {
                Text(
                    text = moment.nickname,
                    style = ClTheme.typography.body,
                    color = ClTheme.colors.ink,
                )
                Text(
                    text = TimeFormat.friendly(moment.createdAt / 1000),
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.inkSecondary,
                )
            }
            if (moment.canDelete) {
                ClIconButton(
                    icon = ClIcons.more,
                    contentDescription = "删除动态",
                    onClick = onDelete,
                )
            }
        }

        if (moment.content.isNotBlank()) {
            Text(
                text = moment.content,
                style = ClTheme.typography.body,
                color = ClTheme.colors.ink,
            )
        }

        if (moment.images.isNotEmpty()) {
            ClPhotoGrid(urls = moment.images, onClick = onPreview)
        }

        Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.l)) {
            MomentAction(
                icon = ClIcons.heart,
                text = if (moment.likeCount > 0) moment.likeCount.toString() else "赞",
                tint = if (moment.isLiked) ClTheme.colors.pink else ClTheme.colors.inkSecondary,
                onClick = onToggleLike,
            )
            MomentAction(
                icon = ClIcons.message,
                text = if (moment.commentCount > 0) moment.commentCount.toString() else "评论",
                tint = ClTheme.colors.inkSecondary,
                onClick = onComment,
            )
        }
    }
}

@Composable
private fun MomentAction(
    icon: ImageVector,
    text: String,
    tint: Color,
    onClick: () -> Unit,
) {
    Row(
        modifier = Modifier
            .clip(ClTheme.shapes.circle)
            .clickable(onClick = onClick)
            .padding(horizontal = ClTheme.spacing.s, vertical = ClTheme.spacing.xs),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = tint,
            modifier = Modifier.size(ClTheme.size.iconS),
        )
        Text(text = text, style = ClTheme.typography.tiny, color = tint)
    }
}
