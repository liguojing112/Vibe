package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 点击弹出选择的表单字段（docs/03 第 5.5 节）：城市、学历、生日等。
 *
 * 只负责展示与触发点击，弹什么由调用方决定。
 */
@Composable
fun ClSelectField(
    label: String,
    value: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String = "请选择",
    error: String? = null,
    enabled: Boolean = true,
) {
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
    ) {
        Text(
            text = label,
            style = ClTheme.typography.caption,
            color = ClTheme.colors.inkSecondary,
        )

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(ClTheme.size.inputHeight)
                .background(ClTheme.colors.bgInput, ClTheme.shapes.input)
                .border(
                    width = 1.dp,
                    color = if (error != null) ClTheme.colors.danger else ClTheme.colors.line,
                    shape = ClTheme.shapes.input,
                )
                .clickable(enabled = enabled, onClick = onClick)
                .padding(horizontal = ClTheme.spacing.m),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
        ) {
            Text(
                text = value.ifBlank { placeholder },
                style = ClTheme.typography.body,
                color = if (value.isBlank()) ClTheme.colors.muted else ClTheme.colors.ink,
                maxLines = 1,
                modifier = Modifier.weight(1f),
            )
            Icon(
                imageVector = ClIcons.chevron,
                contentDescription = null,
                tint = ClTheme.colors.muted,
                modifier = Modifier.size(ClTheme.size.iconS),
            )
        }

        if (error != null) {
            Text(
                text = error,
                style = ClTheme.typography.caption,
                color = ClTheme.colors.danger,
                modifier = Modifier.padding(horizontal = ClTheme.spacing.xs),
            )
        }
    }
}

/** 选择弹窗里常用的一行选项。 */
@Composable
fun ClSelectOption(
    text: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(ClTheme.size.menuRowHeight)
            .clickable(onClick = onClick)
            .padding(horizontal = ClTheme.spacing.l),
        contentAlignment = Alignment.CenterStart,
    ) {
        Text(
            text = text,
            style = ClTheme.typography.body,
            color = if (selected) ClTheme.colors.primaryText else ClTheme.colors.ink,
        )
    }
}
