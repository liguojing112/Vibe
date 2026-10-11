package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

private val UnderlineWidth = 28.dp
private val UnderlineHeight = 3.dp
private val UnderlineRadius = 2.dp

/**
 * 一级页面大标题（docs/03 第 5.1 节，对应原型 `.page-title`），带黄色短下划线。
 */
@Composable
fun ClPageHeader(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    trailing: (@Composable () -> Unit)? = null,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(
                start = ClTheme.spacing.l,
                end = ClTheme.spacing.l,
                top = ClTheme.spacing.l,
                bottom = ClTheme.spacing.s,
            ),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                text = title,
                style = ClTheme.typography.pageTitle,
                color = ClTheme.colors.ink,
                modifier = Modifier.weight(1f),
            )
            trailing?.invoke()
        }

        if (subtitle != null) {
            Text(
                text = subtitle,
                style = ClTheme.typography.caption,
                color = ClTheme.colors.muted,
            )
        }

        Box(
            modifier = Modifier
                .width(UnderlineWidth)
                .height(UnderlineHeight)
                .background(ClTheme.colors.accentUnderline, RoundedCornerShape(UnderlineRadius)),
        )
    }
}
