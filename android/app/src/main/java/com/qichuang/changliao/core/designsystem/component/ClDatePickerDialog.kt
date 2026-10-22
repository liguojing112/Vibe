package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.layout.padding
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDefaults
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Text
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 日期选择弹窗（docs/03 第 5.5 节 `ClSelectField` 的「生日」用它弹出来）。
 *
 * 时间轴是 **UTC 零点毫秒**（Material3 `DatePicker` 的口径），转换用
 * `core/common/TimeFormat.utcMillisToDate()` / `dateToUtcMillis()`，页面别自己算。
 *
 * @param yearRange 可选年份范围。生日这种要卡「必须满 18 岁」的场景传进来，
 *                  直接把不该选的年份排除掉，比让用户选完再报错友好。
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClDatePickerDialog(
    title: String,
    onDismiss: () -> Unit,
    onConfirm: (utcMillis: Long) -> Unit,
    modifier: Modifier = Modifier,
    initialUtcMillis: Long? = null,
    yearRange: IntRange? = null,
) {
    val state = rememberDatePickerState(
        initialSelectedDateMillis = initialUtcMillis,
        yearRange = yearRange ?: DEFAULT_YEAR_RANGE,
    )

    DatePickerDialog(
        onDismissRequest = onDismiss,
        modifier = modifier,
        // 按钮用设计系统的胶囊按钮（原来两个纯文字按钮客户觉得太简陋）：
        // 取消 = 灰底柔和按钮，确定 = 黄底主按钮，和其它页面的按钮风格一致。
        confirmButton = {
            ClPrimaryButton(
                text = "确定",
                fillMaxWidth = false,
                modifier = Modifier.padding(end = ClTheme.spacing.s),
                onClick = { state.selectedDateMillis?.let { onConfirm(it) } },
            )
        },
        dismissButton = {
            ClSoftButton(
                text = "取消",
                kind = ClSoftButtonKind.Gray,
                onClick = onDismiss,
            )
        },
        colors = DatePickerDefaults.colors(
            containerColor = ClTheme.colors.surface,
            titleContentColor = ClTheme.colors.ink,
        ),
    ) {
        DatePicker(
            state = state,
            title = {
                Text(
                    text = title,
                    style = ClTheme.typography.cardTitle,
                    color = ClTheme.colors.ink,
                    modifier = Modifier.padding(
                        start = ClTheme.spacing.xxl,
                        top = ClTheme.spacing.l,
                    ),
                )
            },
            showModeToggle = false,
        )
    }
}

private val DEFAULT_YEAR_RANGE = 1900..2100
