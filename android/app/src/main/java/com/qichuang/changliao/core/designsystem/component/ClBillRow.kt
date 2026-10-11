package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 账单分组（docs/03 第 5.3 节，对应原型 `.bill-grid`）：拨打确认、通话账单、提现信息用。
 */
@Composable
fun ClBillGroup(
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(ClTheme.shapes.card)
            .background(ClTheme.colors.bgCream)
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.xs),
        content = content,
    )
}

/**
 * 账单行（docs/03 第 5.3 节，对应原型 `.bill-row`）：左标签、右值。
 */
@Composable
fun ClBillRow(
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    valueColor: Color? = null,
    showDivider: Boolean = true,
) {
    Column(modifier = modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = ClTheme.spacing.m),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = label,
                style = ClTheme.typography.caption,
                color = ClTheme.colors.inkSecondary,
            )
            Text(
                text = value,
                style = ClTheme.typography.caption,
                color = valueColor ?: ClTheme.colors.ink,
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
