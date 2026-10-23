package com.qichuang.changliao.feature.message

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.ChatConversation
import com.qichuang.changliao.core.data.model.DiscoverUser
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClConversationItem
import com.qichuang.changliao.core.designsystem.component.ClConversationRow
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClEntryRow
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClIconButton
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPageHeader
import com.qichuang.changliao.core.designsystem.component.ClPullRefreshList
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSwipeToDelete
import com.qichuang.changliao.core.designsystem.component.ClTag
import com.qichuang.changliao.core.designsystem.component.ClTagKind
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 消息页（P03，设计图 03）。
 *
 * 结构（自上而下）：标题栏（搜索 / 筛选）→ 最近联系人横条 → 功能入口行 → 私信会话列表。
 * 搜索和「只看未读」是本地筛选，筛选时就别再把横条和入口摆在那儿了。
 */
@Composable
fun MessageScreen(
    state: MessageUiState,
    onOpenChat: (Long) -> Unit,
    onOpenRoute: (String) -> Unit,
    onOpenDiscover: () -> Unit,
    onOpenDiscoverCity: () -> Unit,
    onToggleSearch: () -> Unit,
    onQueryChange: (String) -> Unit,
    onToggleUnreadOnly: () -> Unit,
    onDelete: (Long) -> Unit,
    onRefresh: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var filterSheetVisible by remember { mutableStateOf(false) }

    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
    ) {
        Column(modifier = Modifier.fillMaxSize()) {
            ClPageHeader(
                title = "消息",
                trailing = {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                    ) {
                        // 筛选中时挂个标签：不然用户会以为「我的会话怎么都不见了」
                        if (state.unreadOnly) {
                            ClTag(text = "只看未读", kind = ClTagKind.Pink)
                        }
                        ClIconButton(
                            icon = ClIcons.search,
                            contentDescription = "搜索会话",
                            onClick = onToggleSearch,
                        )
                        ClIconButton(
                            icon = ClIcons.filter,
                            contentDescription = "筛选",
                            onClick = { filterSheetVisible = true },
                        )
                    }
                },
            )

            if (state.searching) {
                SearchBar(
                    query = state.query,
                    onQueryChange = onQueryChange,
                    onCancel = onToggleSearch,
                )
            }

            Box(modifier = Modifier.weight(1f)) {
                when {
                    state.loading -> ClLoading()

                    state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

                    state.isEmpty -> Column {
                        // 没有会话时也得能进「系统消息 / 最近访客 / 看看谁喜欢我」——
                        // 这些入口不该因为「还没聊过天」而消失（新账号一进来就是这种情况）
                        if (!state.filtering) {
                            EntryRows(
                                state = state,
                                onOpenRoute = onOpenRoute,
                                onOpenDiscoverCity = onOpenDiscoverCity,
                            )
                        }
                        Box(modifier = Modifier.weight(1f)) {
                            ClEmpty(
                                title = state.emptyTitle,
                                desc = state.emptyDesc,
                                actionText = if (state.filtering) "全部会话" else "去发现页",
                                onAction = { if (state.filtering) onToggleUnreadOnly() else onOpenDiscover() },
                            )
                        }
                    }

                    else -> Column {
                        if (state.userResults.isNotEmpty()) {
                            UserResults(users = state.userResults, onOpenChat = onOpenChat)
                        }
                        ConversationList(
                            state = state,
                            onOpenChat = onOpenChat,
                            onOpenRoute = onOpenRoute,
                            onOpenDiscoverCity = onOpenDiscoverCity,
                            onDelete = onDelete,
                            onRefresh = onRefresh,
                            modifier = Modifier.weight(1f),
                        )
                    }
                }
            }
        }
    }

    if (filterSheetVisible) {
        ClBottomSheet(title = "筛选会话", onClose = { filterSheetVisible = false }) {
            ClChipGroup(
                options = listOf(FILTER_ALL, FILTER_UNREAD),
                selected = setOf(if (state.unreadOnly) FILTER_UNREAD else FILTER_ALL),
                onSelectionChange = { selection ->
                    // 「只看未读」是开关，再点一次就是关；「全部」就是关
                    val wantUnread = FILTER_UNREAD in selection
                    if (wantUnread != state.unreadOnly) {
                        onToggleUnreadOnly()
                    }
                    filterSheetVisible = false
                },
            )
        }
    }
}

/** 会话列表本体：头部（横条 + 入口行）跟着一起滚。 */
@Composable
private fun ConversationList(
    state: MessageUiState,
    onOpenChat: (Long) -> Unit,
    onOpenRoute: (String) -> Unit,
    onOpenDiscoverCity: () -> Unit,
    onDelete: (Long) -> Unit,
    onRefresh: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClPullRefreshList(
        modifier = modifier,
        items = state.visible,
        refreshing = state.refreshing,
        onRefresh = onRefresh,
        key = { it.peerId },
        header = {
            if (!state.filtering) {
                RecentPeersStrip(peers = state.recentPeers, onOpenChat = onOpenChat)
                EntryRows(
                    state = state,
                    onOpenRoute = onOpenRoute,
                    onOpenDiscoverCity = onOpenDiscoverCity,
                )
            } else if (state.userResults.isNotEmpty()) {
                SectionLabel("会话")
            }
        },
    ) { conversation ->
        ClSwipeToDelete(onDelete = { onDelete(conversation.peerId) }) {
            ClConversationRow(
                conversation = conversation.toItem(),
                onClick = { onOpenChat(conversation.peerId) },
            )
        }
    }
}

/** 搜索命中的用户：还没聊过的人也能点进来直接开聊。 */
@Composable
private fun UserResults(
    users: List<DiscoverUser>,
    onOpenChat: (Long) -> Unit,
) {
    Column {
        SectionLabel("用户")
        users.take(MAX_USER_RESULTS).forEach { user ->
            ClConversationRow(
                conversation = ClConversationItem(
                    id = user.id,
                    name = user.nickname,
                    avatarUrl = user.avatar,
                    lastMessage = listOfNotNull(
                        user.city.ifBlank { null },
                        user.age?.let { "$it 岁" },
                    ).joinToString(" · "),
                    online = user.online,
                ),
                onClick = { onOpenChat(user.id) },
            )
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(
        text = text,
        style = ClTheme.typography.tiny,
        color = ClTheme.colors.inkSecondary,
        modifier = Modifier.padding(
            start = ClTheme.spacing.l,
            top = ClTheme.spacing.s,
            bottom = ClTheme.spacing.xs,
        ),
    )
}

/** 顶部横条：最近联系的几个人，点进聊天页（设计图 03）。 */
@Composable
private fun RecentPeersStrip(
    peers: List<ChatConversation>,
    onOpenChat: (Long) -> Unit,
) {
    if (peers.isEmpty()) {
        return
    }

    LazyRow(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = ClTheme.spacing.s),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = ClTheme.spacing.l),
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
    ) {
        items(peers, key = { it.peerId }) { peer ->
            Column(
                modifier = Modifier
                    .width(ClTheme.size.peerStripItem)
                    .clickable { onOpenChat(peer.peerId) },
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
            ) {
                ClAvatar(
                    url = peer.peerAvatar,
                    size = ClTheme.size.avatarM,
                    contentDescription = peer.peerNickname,
                )
                Text(
                    text = peer.peerNickname,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.inkSecondary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

/**
 * 功能入口行（设计图 03）。
 *
 * 说明文案只在**有真数据**的时候才显示（`fanDesc` / `visitorDesc`），没有数据就不摆一句
 * 「你有新的心动消息」在那儿 —— 那是假数据（见全局规则里的毕设假数据诊断）。
 */
@Composable
private fun EntryRows(
    state: MessageUiState,
    onOpenRoute: (String) -> Unit,
    onOpenDiscoverCity: () -> Unit,
) {
    Column {
        ClEntryRow(
            icon = ClIcons.heart,
            tint = ClTheme.colors.pink,
            title = "看看谁喜欢我",
            desc = state.fanDesc,
            onClick = { onOpenRoute(MessageRoutes.likedMe) },
        )
        ClEntryRow(
            icon = ClIcons.pin,
            tint = ClTheme.colors.orange,
            title = "附近的人",
            onClick = onOpenDiscoverCity,
        )
        ClEntryRow(
            icon = ClIcons.eye,
            tint = ClTheme.colors.green,
            title = "最近访客",
            desc = state.visitorDesc,
            badge = state.unreadVisitors,
            onClick = { onOpenRoute(MessageRoutes.visitors) },
        )
        ClEntryRow(
            icon = ClIcons.bell,
            tint = ClTheme.colors.coinText,
            title = "系统消息",
            badge = state.unreadNotices,
            onClick = { onOpenRoute(MessageRoutes.notices) },
        )
        if (state.callEnabled) {
            // 语音/视频开关关着的时候这个入口不显示（docs/01 第 4 节）
            ClEntryRow(
                icon = ClIcons.phone,
                tint = ClTheme.colors.pink,
                title = "连线记录",
                desc = "查看语音、视频时长与消费账单",
                onClick = { onOpenRoute(MessageRoutes.callRecords) },
            )
        }
        ClEntryRow(
            icon = ClIcons.spark,
            tint = ClTheme.colors.primary,
            title = "互动通知",
            desc = "关注、送礼物等互动提醒",
            showDivider = false,
            onClick = { onOpenRoute(MessageRoutes.noticeInteract) },
        )
    }
}

/** 顶部搜索框（只在 [MessageUiState.searching] 时显示）。 */
@Composable
private fun SearchBar(
    query: String,
    onQueryChange: (String) -> Unit,
    onCancel: () -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.s),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        ClTextField(
            label = "搜索会话",
            value = query,
            onValueChange = onQueryChange,
            placeholder = "输入昵称",
            modifier = Modifier.weight(1f),
        )
        Text(
            text = "取消",
            style = ClTheme.typography.body,
            color = ClTheme.colors.primaryText,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .clickable(onClick = onCancel)
                .padding(vertical = ClTheme.spacing.s, horizontal = ClTheme.spacing.xs),
        )
    }
}

/** 会话模型 → 组件模型。时间按 docs/03 第 6 节的统一规则格式化。 */
private fun ChatConversation.toItem() = ClConversationItem(
    id = peerId,
    name = peerNickname,
    avatarUrl = peerAvatar,
    lastMessage = lastMessagePreview,
    time = TimeFormat.friendly(lastMessageAt / 1000),
    unreadCount = unread,
)

private const val FILTER_ALL = "全部"

private const val FILTER_UNREAD = "未读"

/** 搜索结果里的人最多展示几个：再多就盖住会话列表了。 */
private const val MAX_USER_RESULTS = 5
