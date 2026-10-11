package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.RangeSlider
import androidx.compose.material3.SliderDefaults
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 年龄范围双滑块（docs/03 第 5.5 节）。默认 18~60。
 */
@Composable
fun ClRangeSlider(
    value: ClosedFloatingPointRange<Float>,
    onValueChange: (ClosedFloatingPointRange<Float>) -> Unit,
    modifier: Modifier = Modifier,
    valueRange: ClosedFloatingPointRange<Float> = 18f..60f,
) {
    RangeSlider(
        value = value,
        onValueChange = onValueChange,
        valueRange = valueRange,
        modifier = modifier.fillMaxWidth(),
        colors = SliderDefaults.colors(
            thumbColor = ClTheme.colors.primary,
            activeTrackColor = ClTheme.colors.primary,
            inactiveTrackColor = ClTheme.colors.line,
        ),
    )
}
