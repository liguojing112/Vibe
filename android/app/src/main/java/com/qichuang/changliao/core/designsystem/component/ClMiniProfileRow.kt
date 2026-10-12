package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 小头像 + 昵称 + 说明 + 右侧小按钮的一行（docs/03 第 5.4 节，设计图 08）。
 *
 * P08 最近访客、P09 喜欢我的人、P16 语音大厅、黑名单共用。
 *
 * [blurred] = true 时把**头像**糊掉（非会员看不全是谁，P08/P09）。
 * 注意文档的口径是「昵称打码 + 头像模糊」：昵称由**服务端**打成「小*」，
 * 客户端只管把头像糊掉 —— 别再糊文字，那样连打码后的首字都看不清了。
 */
@Composable
fun ClMiniProfileRow(
    name: String,
    modifier: Modifier = Modifier,
    avatarUrl: String? = null,
    desc: String? = null,
    online: Boolean = false,
    blurred: Boolean = false,
    buttonText: String? = null,
    showDivider: Boolean = true,
    onClick: (() -> Unit)? = null,
    onAction: (() -> Unit)? = null,
) {
    Box(modifier = modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
                .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            ClAvatar(
                url = avatarUrl,
                size = ClTheme.size.avatarS,
                online = online,
                contentDescription = name,
                modifier = if (blurred) Modifier.blur(ClTheme.spacing.s) else Modifier,
            )

            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = ClTheme.spacing.m),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
            ) {
                Text(
                    text = name,
                    style = ClTheme.typography.cardTitle,
                    color = ClTheme.colors.ink,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                if (desc != null) {
                    Text(
                        text = desc,
                        style = ClTheme.typography.bodySmall,
                        color = ClTheme.colors.inkSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }

            if (buttonText != null) {
                ClOutlineSmallButton(text = buttonText, onClick = { onAction?.invoke() })
            }
        }

        if (showDivider) {
            ClCardDivider(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(start = ClTheme.spacing.l),
            )
        }
    }
}
