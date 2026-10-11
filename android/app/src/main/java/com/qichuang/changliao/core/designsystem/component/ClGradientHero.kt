package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import com.qichuang.changliao.core.designsystem.theme.ClLinearGradient
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.designsystem.theme.clGradientBackground

/**
 * 渐变横幅（docs/03 第 5.3 节）：任务页、语音大厅、余额卡复用。
 * [gradient] 取 [com.qichuang.changliao.core.designsystem.theme.ClGradients] 里的值。
 */
@Composable
fun ClGradientHero(
    gradient: ClLinearGradient,
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    trailing: (@Composable () -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .clip(ClTheme.shapes.card)
            .clGradientBackground(gradient)
            .padding(ClTheme.spacing.xl),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
        ) {
            Text(
                text = title,
                style = ClTheme.typography.sectionTitle,
                color = ClTheme.colors.onPrimary,
            )
            if (subtitle != null) {
                Text(
                    text = subtitle,
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.onPrimary,
                )
            }
        }
        trailing?.invoke()
    }
}
