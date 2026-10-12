package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 用户卡片上的一个小标签。 */
data class ClPersonTag(
    val text: String,
    val kind: ClTagKind = ClTagKind.Green,
)

/** [ClPersonCard] 要展示的人（docs/01 P01）。 */
data class ClPersonCardData(
    val name: String,
    val avatarUrl: String? = null,
    val online: Boolean = false,
    val vip: Boolean = false,
    val tags: List<ClPersonTag> = emptyList(),
    val meta: String = "",
    val intro: String? = null,
)

/**
 * 用户卡片（docs/03 第 5.4 节，设计图 01）：
 * 头像 + 昵称 + 标签 + 「年龄 · 城市 · 职业」+ 自我介绍 + 操作按钮。
 *
 * 箭头进个人详情；[actions] 由页面给（男用户三颗按钮，女用户只有「私信」）。
 */
@Composable
fun ClPersonCard(
    user: ClPersonCardData,
    modifier: Modifier = Modifier,
    showDivider: Boolean = true,
    onClick: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
) {
    Box(modifier = modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
                .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.l),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                ClAvatar(
                    url = user.avatarUrl,
                    size = ClTheme.size.avatarM,
                    online = user.online,
                    vip = user.vip,
                    contentDescription = user.name,
                )
                Column(
                    modifier = Modifier
                        .weight(1f)
                        .padding(start = ClTheme.spacing.m),
                    verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                    ) {
                        Text(
                            text = user.name,
                            style = ClTheme.typography.cardTitle,
                            color = ClTheme.colors.ink,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                        user.tags.forEach { ClTag(text = it.text, kind = it.kind) }
                    }
                    if (user.meta.isNotEmpty()) {
                        Text(
                            text = user.meta,
                            style = ClTheme.typography.bodySmall,
                            color = ClTheme.colors.inkSecondary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
                if (onClick != null) {
                    Icon(
                        imageVector = ClIcons.chevron,
                        contentDescription = null,
                        tint = ClTheme.colors.muted,
                        modifier = Modifier.size(ClTheme.size.iconM),
                    )
                }
            }

            if (user.intro != null) {
                Text(
                    text = user.intro,
                    style = ClTheme.typography.body,
                    color = ClTheme.colors.inkSecondary,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }

            Row(
                horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                verticalAlignment = Alignment.CenterVertically,
                content = actions,
            )
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

/** 列表项之间的分割线（设计图里从左侧内容起点开始，不顶到屏幕边）。 */
@Composable
fun ClCardDivider(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(1.dp)
            .background(ClTheme.colors.line),
    )
}
