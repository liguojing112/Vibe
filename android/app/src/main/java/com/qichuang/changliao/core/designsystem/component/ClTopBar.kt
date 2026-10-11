package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 图标按钮的可点区域下限（docs/03 第 6 节第 8 条：不小于 48dp）。 */
private val MinTouchTarget = 48.dp

/**
 * 内页顶栏（docs/03 第 5.1 节，对应原型 `.inner-head`）：
 * 返回箭头 + 标题 + 右侧文字按钮，背景 bgCream。
 */
@Composable
fun ClTopBar(
    title: String,
    modifier: Modifier = Modifier,
    onBack: (() -> Unit)? = null,
    action: (@Composable () -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .height(ClTheme.size.topBarHeight)
            .background(ClTheme.colors.bgCream)
            .padding(horizontal = ClTheme.spacing.l),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (onBack != null) {
            Box(
                modifier = Modifier
                    .size(MinTouchTarget)
                    .clickable(
                        interactionSource = remember { MutableInteractionSource() },
                        indication = null,
                        onClick = onBack,
                    ),
                contentAlignment = Alignment.CenterStart,
            ) {
                Icon(
                    imageVector = ClIcons.back,
                    contentDescription = "返回",
                    tint = ClTheme.colors.ink,
                    modifier = Modifier.size(ClTheme.size.iconM),
                )
            }
        }

        Text(
            text = title,
            style = ClTheme.typography.headerTitle,
            color = ClTheme.colors.ink,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier
                .weight(1f)
                .padding(start = if (onBack == null) ClTheme.spacing.xs else ClTheme.spacing.s),
        )

        action?.invoke()
    }
}
