package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 区块标题（docs/03 第 5.1 节，对应原型 `.section-head`）。
 */
@Composable
fun ClSectionHeader(
    title: String,
    modifier: Modifier = Modifier,
    actionText: String? = null,
    onAction: (() -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(
                start = ClTheme.spacing.l,
                end = ClTheme.spacing.l,
                top = ClTheme.spacing.l,
                bottom = ClTheme.spacing.s,
            ),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(
            text = title,
            style = ClTheme.typography.sectionTitle,
            color = ClTheme.colors.ink,
            modifier = Modifier.weight(1f),
        )

        if (actionText != null) {
            ClTextButton(text = actionText, onClick = onAction)
        }
    }
}
