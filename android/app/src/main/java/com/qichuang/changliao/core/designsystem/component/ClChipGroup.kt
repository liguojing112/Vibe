package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * Chip 单选 / 多选（docs/03 第 5.5 节）：筛选条件、兴趣标签用。
 *
 * [multi] = false 时点谁选谁；= true 时可多选。
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun ClChipGroup(
    options: List<String>,
    selected: Set<String>,
    onSelectionChange: (Set<String>) -> Unit,
    modifier: Modifier = Modifier,
    multi: Boolean = false,
) {
    FlowRow(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        options.forEach { option ->
            val isSelected = option in selected
            Text(
                text = option,
                style = ClTheme.typography.caption,
                color = if (isSelected) ClTheme.colors.primaryText else ClTheme.colors.inkSecondary,
                maxLines = 1,
                modifier = Modifier
                    .clip(ClTheme.shapes.pill)
                    .background(
                        if (isSelected) ClTheme.colors.primaryChip else ClTheme.colors.surface
                    )
                    .border(
                        width = 1.dp,
                        color = if (isSelected) ClTheme.colors.primaryChipBorder else ClTheme.colors.line,
                        shape = ClTheme.shapes.pill,
                    )
                    .clickable {
                        val next = when {
                            multi && isSelected -> selected - option
                            multi -> selected + option
                            else -> setOf(option)
                        }
                        onSelectionChange(next)
                    }
                    .padding(horizontal = ClTheme.spacing.m, vertical = ClTheme.spacing.s),
            )
        }
    }
}
