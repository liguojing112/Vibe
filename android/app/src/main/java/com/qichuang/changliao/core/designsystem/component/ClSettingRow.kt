package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 设置行的开关（docs/03 第 5.3 节）：用主题色，不用 Material 默认色。
 */
@Composable
fun ClSwitch(
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
) {
    Switch(
        checked = checked,
        onCheckedChange = onCheckedChange,
        enabled = enabled,
        modifier = modifier,
        colors = SwitchDefaults.colors(
            checkedThumbColor = ClTheme.colors.surface,
            checkedTrackColor = ClTheme.colors.primary,
            checkedBorderColor = ClTheme.colors.primary,
            uncheckedThumbColor = ClTheme.colors.surface,
            uncheckedTrackColor = ClTheme.colors.line,
            uncheckedBorderColor = ClTheme.colors.line,
        ),
    )
}

/**
 * 设置行（docs/03 第 5.3 节）：左标签（可带说明），右侧放开关或任意控件。
 */
@Composable
fun ClSettingRow(
    label: String,
    modifier: Modifier = Modifier,
    desc: String? = null,
    trailing: (@Composable () -> Unit)? = null,
    onClick: (() -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .background(ClTheme.colors.surface)
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
        ) {
            Text(
                text = label,
                style = ClTheme.typography.body,
                color = ClTheme.colors.ink,
            )
            if (desc != null) {
                Text(
                    text = desc,
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.muted,
                )
            }
        }

        trailing?.invoke()
    }
}
