package com.qichuang.changliao.feature.message

import com.qichuang.changliao.core.data.model.ChatConversation
import com.qichuang.changliao.core.data.model.DiscoverUser

/**
 * 消息页（P03）状态。
 *
 * 会话列表**来自本地库**（`ChatRepository.conversations()`，docs/10 第 1 节），
 * 所以它是个一直活着的 `Flow`：WebSocket 收到新消息 → 本地库变化 → 这里自动刷新，
 * 不需要页面自己轮询或手动"重新加载"。
 *
 * 搜索和「只看未读」都是**本地筛选**（会话本来就只有几十条），不额外打接口。
 */
data class MessageUiState(
    val conversations: List<ChatConversation> = emptyList(),
    val query: String = "",
    val unreadOnly: Boolean = false,
    val searching: Boolean = false,
    val loading: Boolean = true,
    val refreshing: Boolean = false,
    val error: String? = null,
    /** 搜索框里的关键词命中的**用户**（走 `/discover/search`，含还没聊过的人）。 */
    val userResults: List<DiscoverUser> = emptyList(),
    val searchingUsers: Boolean = false,
    /** 「看看谁喜欢我」的说明文案，来自 `/user/me` 的统计。 */
    val fanCount: Int = 0,
    val visitorCount: Int = 0,
    val unreadVisitors: Int = 0,
    /** 「系统消息」入口的红点（docs/01 P03）。服务端的 `/chat/unread` 把它算在 `notice` 里。 */
    val unreadNotices: Int = 0,
    val callEnabled: Boolean = false,
) {

    /** 真正显示在列表里的会话（搜索 + 只看未读都作用在这里）。 */
    val visible: List<ChatConversation> = conversations.filter { conversation ->
        (!unreadOnly || conversation.hasUnread) &&
            (query.isBlank() || conversation.peerNickname.contains(query.trim(), ignoreCase = true))
    }

    /** 顶部头像横条：最近联系的几个人（会话列表已按时间倒序）。 */
    val recentPeers: List<ChatConversation> = conversations.take(RECENT_PEER_COUNT)

    /** 正在筛选（搜索词非空或只看未读）——筛选时把头部的横条和功能入口收起来。 */
    val filtering: Boolean = query.isNotBlank() || unreadOnly

    /** 会话和用户都没命中才算空 —— 只搜到人（还没聊过）时要能点进去开聊。 */
    val isEmpty: Boolean = !loading && error == null && visible.isEmpty() && userResults.isEmpty()

    /** 筛选筛空了和「压根没聊过」是两种说法，文案不能混。 */
    val emptyTitle: String = if (filtering) "没有符合条件的会话" else "还没有聊天"

    val emptyDesc: String? = if (filtering) "换个关键词或切回全部试试" else "去发现页找个人打个招呼吧"

    /** 「看看谁喜欢我」的说明：没有新粉丝就不摆一句假话在那里。 */
    val fanDesc: String? = if (fanCount > 0) "有 $fanCount 人喜欢了你" else null

    /** 「最近访客」的说明。 */
    val visitorDesc: String? = if (visitorCount > 0) "有 $visitorCount 人看过你的主页" else null

    companion object {
        const val RECENT_PEER_COUNT = 5
    }
}
