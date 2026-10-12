package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 表单输入（docs/03 第 5.5 节）：标签在上、`input` 圆角、`line` 描边、错误时红字提示。
 *
 * 外壳自己拼（`BasicTextField`），不用 Material3 的 `OutlinedTextField` ——
 * 后者自带 56dp 的最小高度，压到设计要的 44dp（[ClTheme.size.inputHeight]）会把文字裁掉。
 * 超长会被截断（[maxLength]），错误时边框变红、下面显示 [error]。
 */
@Composable
fun ClTextField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String? = null,
    error: String? = null,
    enabled: Boolean = true,
    singleLine: Boolean = true,
    keyboardType: KeyboardType = KeyboardType.Text,
    maxLength: Int? = null,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    trailingIcon: (@Composable () -> Unit)? = null,
) {
    var focused by remember { mutableStateOf(false) }

    val borderColor = when {
        error != null -> ClTheme.colors.danger
        focused -> ClTheme.colors.primaryChipBorder
        else -> ClTheme.colors.line
    }

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
                .heightIn(min = ClTheme.size.inputHeight)
                .background(ClTheme.colors.bgInput, ClTheme.shapes.input)
                .border(1.dp, borderColor, ClTheme.shapes.input)
                .padding(horizontal = ClTheme.spacing.m, vertical = ClTheme.spacing.s),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(modifier = Modifier.weight(1f)) {
                if (value.isEmpty() && placeholder != null) {
                    Text(
                        text = placeholder,
                        style = ClTheme.typography.body,
                        color = ClTheme.colors.muted,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }

                BasicTextField(
                    value = value,
                    onValueChange = { input ->
                        onValueChange(if (maxLength != null) input.take(maxLength) else input)
                    },
                    enabled = enabled,
                    singleLine = singleLine,
                    textStyle = ClTheme.typography.body.merge(color = ClTheme.colors.ink),
                    cursorBrush = SolidColor(ClTheme.colors.primaryText),
                    visualTransformation = visualTransformation,
                    keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
                    modifier = Modifier
                        .fillMaxWidth()
                        .onFocusChanged { focused = it.isFocused },
                )
            }

            trailingIcon?.invoke()
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
