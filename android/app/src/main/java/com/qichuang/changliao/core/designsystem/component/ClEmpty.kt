package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 空状态（docs/03 第 5.6 节，对应原型 `.empty`）：页面四态之一。
 */
@Composable
fun ClEmpty(
    title: String,
    modifier: Modifier = Modifier,
    desc: String? = null,
    icon: ImageVector = ClIcons.spark,
    actionText: String? = null,
    onAction: (() -> Unit)? = null,
) {
    Box(
        modifier = modifier.fillMaxSize(),
        contentAlignment = Alignment.Center,
    ) {
        Column(
            modifier = Modifier.padding(ClTheme.spacing.xxxl),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = ClTheme.colors.muted,
                modifier = Modifier.size(ClTheme.size.iconM + ClTheme.spacing.l),
            )

            Text(
                text = title,
                style = ClTheme.typography.body,
                color = ClTheme.colors.inkSecondary,
                textAlign = TextAlign.Center,
            )

            if (desc != null) {
                Text(
                    text = desc,
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.muted,
                    textAlign = TextAlign.Center,
                )
            }

            if (actionText != null) {
                ClOutlineSmallButton(text = actionText, onClick = { onAction?.invoke() })
            }
        }
    }
}
