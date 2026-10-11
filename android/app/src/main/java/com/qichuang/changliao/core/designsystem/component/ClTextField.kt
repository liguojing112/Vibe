package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.TextStyle
import androidx.compose.foundation.text.KeyboardOptions
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 表单输入（docs/03 第 5.5 节）：标签在上、`input` 圆角、`line` 描边、错误时红字提示。
 *
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
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
    ) {
        Text(
            text = label,
            style = ClTheme.typography.caption,
            color = ClTheme.colors.inkSecondary,
        )

        OutlinedTextField(
            value = value,
            onValueChange = { input ->
                onValueChange(if (maxLength != null) input.take(maxLength) else input)
            },
            enabled = enabled,
            singleLine = singleLine,
            isError = error != null,
            placeholder = placeholder?.let {
                {
                    Text(
                        text = it,
                        style = ClTheme.typography.body,
                        color = ClTheme.colors.muted,
                    )
                }
            },
            textStyle = TextStyle(
                fontSize = ClTheme.typography.body.fontSize,
                color = ClTheme.colors.ink,
            ),
            trailingIcon = trailingIcon,
            visualTransformation = visualTransformation,
            keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
            shape = ClTheme.shapes.input,
            colors = OutlinedTextFieldDefaults.colors(
                focusedContainerColor = ClTheme.colors.bgInput,
                unfocusedContainerColor = ClTheme.colors.bgInput,
                disabledContainerColor = ClTheme.colors.bgInput,
                focusedBorderColor = ClTheme.colors.primaryChipBorder,
                unfocusedBorderColor = ClTheme.colors.line,
                disabledBorderColor = ClTheme.colors.line,
                errorBorderColor = ClTheme.colors.danger,
                cursorColor = ClTheme.colors.primaryText,
            ),
            modifier = Modifier
                .fillMaxWidth()
                .height(ClTheme.size.inputHeight),
        )

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
