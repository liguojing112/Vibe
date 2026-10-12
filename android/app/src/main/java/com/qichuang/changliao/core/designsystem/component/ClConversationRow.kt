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
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** [ClConversationRow] 要展示的会话（docs/01 P03）。 */
data class ClConversationItem(
    val id: Long,
    val name: String,
    val avatarUrl: String? = null,
    val lastMessage: String = "",
    val time: String = "",
    val unreadCount: Int = 0,
    val online: Boolean = false,
)

/**
 * 私信会话行（docs/03 第 5.4 节，设计图 03）：头像 + 昵称 + 最后一条消息 + 时间 + 未读角标。
 *
 * 摘要文案由服务端/上层给（图片是「[图片]」、礼物是「[礼物] 名称」），这里不做判断。
 */
@Composable
fun ClConversationRow(
    conversation: ClConversationItem,
    modifier: Modifier = Modifier,
    showDivider: Boolean = true,
    onClick: () -> Unit,
) {
    Box(modifier = modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable(onClick = onClick)
                .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            ClAvatar(
                url = conversation.avatarUrl,
                size = ClTheme.size.avatarS,
                online = conversation.online,
                contentDescription = conversation.name,
            )

            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = ClTheme.spacing.m),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = conversation.name,
                        style = ClTheme.typography.cardTitle,
                        color = ClTheme.colors.ink,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f),
                    )
                    Text(
                        text = conversation.time,
                        style = ClTheme.typography.tiny,
                        color = ClTheme.colors.muted,
                        maxLines = 1,
                    )
                }

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = conversation.lastMessage,
                        style = ClTheme.typography.bodySmall,
                        color = ClTheme.colors.inkSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f),
                    )
                    if (conversation.unreadCount > 0) {
                        Box(modifier = Modifier.padding(start = ClTheme.spacing.s)) {
                            ClBadgeDot(count = conversation.unreadCount)
                        }
                    }
                }
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
