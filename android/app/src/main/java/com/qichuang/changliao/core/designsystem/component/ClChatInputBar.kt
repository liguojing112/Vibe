package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 聊天输入区（docs/03 第 5.4 节，设计图 06）：左边输入框（可选表情/图片按钮）+ 右边黄色「发送」。
 *
 * 输入框文案由调用方给（男用户是服务端返回的单价或免费提示，女用户是「说点什么…」）。
 * [sending] = true 时按钮转圈并禁用，避免重复发送。
 */
@Composable
fun ClChatInputBar(
    value: String,
    onValueChange: (String) -> Unit,
    onSend: () -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String = "",
    maxLength: Int = Int.MAX_VALUE,
    sending: Boolean = false,
    onPickImage: (() -> Unit)? = null,
    onEmoji: (() -> Unit)? = null,
) {
    val canSend = value.isNotBlank() && !sending

    Row(
        modifier = modifier
            .fillMaxWidth()
            .background(ClTheme.colors.surface)
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.s),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
    ) {
        Row(
            modifier = Modifier
                .weight(1f)
                .height(ClTheme.size.inputHeight)
                .clip(ClTheme.shapes.pill)
                .background(ClTheme.colors.bgInput)
                .padding(horizontal = ClTheme.spacing.l),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(modifier = Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
                if (value.isEmpty() && placeholder.isNotEmpty()) {
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
                    onValueChange = { next ->
                        if (maxLength == Int.MAX_VALUE || next.length <= maxLength) {
                            onValueChange(next)
                        }
                    },
                    textStyle = ClTheme.typography.body.merge(
                        color = ClTheme.colors.ink,
                    ),
                    cursorBrush = SolidColor(ClTheme.colors.primaryText),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
            }

            if (onEmoji != null) {
                Icon(
                    imageVector = ClIcons.spark,
                    contentDescription = "表情",
                    tint = ClTheme.colors.inkSecondary,
                    modifier = Modifier
                        .padding(start = ClTheme.spacing.s)
                        .size(ClTheme.size.iconM)
                        .clickable(onClick = onEmoji),
                )
            }
            if (onPickImage != null) {
                Icon(
                    imageVector = ClIcons.flip,
                    contentDescription = "图片",
                    tint = ClTheme.colors.inkSecondary,
                    modifier = Modifier
                        .padding(start = ClTheme.spacing.s)
                        .size(ClTheme.size.iconM)
                        .clickable(onClick = onPickImage),
                )
            }
        }

        Box(
            modifier = Modifier
                .width(72.dp)
                .height(ClTheme.size.buttonHeight)
                .clip(ClTheme.shapes.pill)
                .background(if (canSend) ClTheme.colors.primary else ClTheme.colors.primarySoft)
                .clickable(enabled = canSend, onClick = onSend),
            contentAlignment = Alignment.Center,
        ) {
            if (sending) {
                ClLoading(modifier = Modifier.size(ClTheme.size.iconM))
            } else {
                Text(
                    text = "发送",
                    style = ClTheme.typography.button,
                    color = if (canSend) ClTheme.colors.onPrimary else ClTheme.colors.primaryText,
                    maxLines = 1,
                )
            }
        }
    }
}
