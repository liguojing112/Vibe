package com.qichuang.changliao.core.designsystem.component

import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 确认对话框（docs/03 第 5.6 节）：拉黑、注销、退出登录等。
 * [danger] = true 时确定按钮用红色（危险操作）。
 */
@Composable
fun ClConfirmDialog(
    title: String,
    message: String,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    modifier: Modifier = Modifier,
    confirmText: String = "确定",
    cancelText: String = "取消",
    danger: Boolean = false,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        modifier = modifier,
        title = {
            Text(
                text = title,
                style = ClTheme.typography.sectionTitle,
                color = ClTheme.colors.ink,
            )
        },
        text = {
            Text(
                text = message,
                style = ClTheme.typography.body,
                color = ClTheme.colors.inkSecondary,
            )
        },
        confirmButton = {
            TextButton(onClick = onConfirm) {
                Text(
                    text = confirmText,
                    style = ClTheme.typography.button,
                    color = if (danger) ClTheme.colors.danger else ClTheme.colors.primaryText,
                )
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text(
                    text = cancelText,
                    style = ClTheme.typography.button,
                    color = ClTheme.colors.inkSecondary,
                )
            }
        },
        shape = ClTheme.shapes.card,
        containerColor = ClTheme.colors.surface,
        textContentColor = ClTheme.colors.inkSecondary,
        titleContentColor = ClTheme.colors.ink,
        iconContentColor = Color.Unspecified,
    )
}
