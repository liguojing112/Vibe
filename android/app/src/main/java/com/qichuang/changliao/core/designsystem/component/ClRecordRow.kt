package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 流水行（docs/03 第 5.3 节，对应原型 `.record-line`）：收支明细、收益明细用。
 *
 * [positive] = true 时金额用绿色（docs/01 P24：金额 +绿 / -黑）。
 */
@Composable
fun ClRecordRow(
    icon: ImageVector,
    title: String,
    time: String,
    amount: String,
    modifier: Modifier = Modifier,
    positive: Boolean = true,
    showDivider: Boolean = true,
    onClick: (() -> Unit)? = null,
) {
    Column(modifier = modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
                .padding(vertical = ClTheme.spacing.m),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            Box(
                modifier = Modifier
                    .size(RecordIconSize)
                    .clip(ClTheme.shapes.cardSmall)
                    .background(ClTheme.colors.primarySoft),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = ClTheme.colors.incomeText,
                    modifier = Modifier.size(ClTheme.size.iconS),
                )
            }

            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
            ) {
                Text(
                    text = title,
                    style = ClTheme.typography.body,
                    color = ClTheme.colors.ink,
                    maxLines = 1,
                )
                Text(
                    text = time,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                    maxLines = 1,
                )
            }

            Text(
                text = amount,
                style = ClTheme.typography.cardTitle,
                color = if (positive) ClTheme.colors.green else ClTheme.colors.ink,
                maxLines = 1,
            )
        }

        if (showDivider) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(1.dp)
                    .background(ClTheme.colors.line),
            )
        }
    }
}

private val RecordIconSize = 37.dp
