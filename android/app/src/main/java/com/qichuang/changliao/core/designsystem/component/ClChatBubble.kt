package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material3.Icon
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 消息内容类型（docs/01 P06）。 */
enum class ClChatContent {
    Text,
    Image,
    Gift,
}

/** 消息发送状态（docs/02 第 4.4 节的状态机）。 */
enum class ClChatStatus {
    Sending,
    Sent,
    Failed,
}

/** [ClChatBubble] 要展示的一条消息。 */
data class ClChatMessage(
    val id: String,
    val content: ClChatContent = ClChatContent.Text,
    /** 文字内容；图片是 URL；礼物是礼物名。 */
    val text: String = "",
    /** 礼物图标（emoji）。 */
    val giftIcon: String? = null,
    val status: ClChatStatus = ClChatStatus.Sent,
)

private val BUBBLE_MAX_WIDTH = 250.dp
private val CHAT_PHOTO_HEIGHT = 160.dp

/**
 * 聊天气泡（docs/03 第 5.4 节，设计图 06）：文字 / 图片 / 礼物三种内容，
 * 自己的消息靠右（黄底）、对方靠左（白底），头像在外侧。
 *
 * 发送中/失败的状态挂在气泡外侧：失败是红色感叹号，点击 [onRetry] 重发。
 */
@Composable
fun ClChatBubble(
    message: ClChatMessage,
    isMine: Boolean,
    modifier: Modifier = Modifier,
    avatarUrl: String? = null,
    onRetry: (() -> Unit)? = null,
    onImageClick: (() -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.xs),
        horizontalArrangement = if (isMine) Arrangement.End else Arrangement.Start,
        verticalAlignment = Alignment.Top,
    ) {
        if (!isMine) {
            ClAvatar(url = avatarUrl, size = ClTheme.size.avatarXS, contentDescription = null)
        }

        if (isMine) {
            ChatStatusMark(status = message.status, onRetry = onRetry, modifier = Modifier.padding(end = ClTheme.spacing.s))
        }

        Box(
            modifier = Modifier
                .widthIn(max = BUBBLE_MAX_WIDTH)
                .clip(if (isMine) ClTheme.shapes.bubbleMine else ClTheme.shapes.bubbleOther)
                .background(if (isMine) ClTheme.colors.bubbleMine else ClTheme.colors.bubbleOther)
                .then(
                    if (message.content == ClChatContent.Image && onImageClick != null) {
                        Modifier.clickable(onClick = onImageClick)
                    } else {
                        Modifier
                    },
                )
                .padding(horizontal = ClTheme.spacing.m, vertical = ClTheme.spacing.s),
        ) {
            when (message.content) {
                ClChatContent.Text -> Text(
                    text = message.text,
                    style = ClTheme.typography.body,
                    color = ClTheme.colors.ink,
                )

                ClChatContent.Image -> AsyncImage(
                    model = message.text,
                    contentDescription = "图片消息",
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .height(CHAT_PHOTO_HEIGHT)
                        .clip(ClTheme.shapes.photo)
                        .background(ClTheme.colors.bgInput),
                )

                ClChatContent.Gift -> Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(text = message.giftIcon.orEmpty(), style = ClTheme.typography.amountMedium)
                    Column(
                        modifier = Modifier.padding(start = ClTheme.spacing.s),
                        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
                    ) {
                        Text(
                            text = message.text,
                            style = ClTheme.typography.cardTitle,
                            color = ClTheme.colors.ink,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                        Text(
                            text = "礼物",
                            style = ClTheme.typography.tiny,
                            color = ClTheme.colors.inkSecondary,
                        )
                    }
                }
            }
        }

        if (!isMine) {
            Box(modifier = Modifier.width(ClTheme.spacing.s))
        }

        if (isMine) {
            ClAvatar(url = avatarUrl, size = ClTheme.size.avatarXS, contentDescription = null)
        }
    }
}

@Composable
private fun ChatStatusMark(
    status: ClChatStatus,
    onRetry: (() -> Unit)?,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier = modifier
            .size(ClTheme.size.iconM)
            .then(
                if (status == ClChatStatus.Failed && onRetry != null) {
                    Modifier.clickable(onClick = onRetry)
                } else {
                    Modifier
                },
            ),
        contentAlignment = Alignment.Center,
    ) {
        when (status) {
            ClChatStatus.Sending -> CircularProgressIndicator(
                modifier = Modifier.size(ClTheme.size.iconM),
                strokeWidth = 2.dp,
                color = ClTheme.colors.inkSecondary,
            )

            ClChatStatus.Failed -> Icon(
                imageVector = ClIcons.warning,
                contentDescription = "发送失败，点击重发",
                tint = ClTheme.colors.danger,
                modifier = Modifier.size(ClTheme.size.iconS),
            )

            ClChatStatus.Sent -> Unit
        }
    }
}
