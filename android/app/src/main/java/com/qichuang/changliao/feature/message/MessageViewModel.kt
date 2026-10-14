package com.qichuang.changliao.feature.message

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.local.UnreadStore
import com.qichuang.changliao.core.data.model.ProfileStats
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ChatRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 消息页（P03）状态管理。
 *
 * 会话列表直接订阅本地库（`ChatRepository.conversations()`），所以：
 * - 进页面先显示本地已有的（秒开，不转圈），同时静默拉一次服务端刷新（docs/10 第 3 节）；
 * - WebSocket 来了新消息，本地库一变，列表和角标**自己就更新了**，不用手动刷新。
 */
@HiltViewModel
class MessageViewModel @Inject constructor(
    private val chatRepository: ChatRepository,
    private val userRepository: UserRepository,
    appConfigRepository: AppConfigRepository,
    unreadStore: UnreadStore,
) : ViewModel() {

    private val query = MutableStateFlow("")

    private val unreadOnly = MutableStateFlow(false)

    private val searching = MutableStateFlow(false)

    private val loading = MutableStateFlow(true)

    private val refreshing = MutableStateFlow(false)

    private val error = MutableStateFlow<String?>(null)

    /** 进页面 / 下拉刷新时拉一次服务端会话列表（补对方资料、校准未读）。 */
    private val extras = combine(
        appConfigRepository.config,
        userRepository.currentUser,
        unreadStore.notice,
    ) { config, me, unreadNotices ->
        Extras(
            callEnabled = config?.callEnabled == true,
            stats = me?.stats ?: ProfileStats(),
            unreadNotices = unreadNotices,
        )
    }

    /**
     * `combine` 只有 2~5 个流的重载，超过 5 个会掉进 vararg 版本（参数变成一个数组），
     * 所以分两级拼：先拼筛选条件、再拼加载状态。
     */
    private val filters = combine(query, unreadOnly, searching) { query, unreadOnly, searching ->
        Filters(query, unreadOnly, searching)
    }

    private val loadState = combine(loading, refreshing, error) { loading, refreshing, error ->
        LoadState(loading, refreshing, error)
    }

    private val controls = combine(filters, loadState) { filters, loadState ->
        Controls(
            query = filters.query,
            unreadOnly = filters.unreadOnly,
            searching = filters.searching,
            loading = loadState.loading,
            refreshing = loadState.refreshing,
            error = loadState.error,
        )
    }

    val uiState: StateFlow<MessageUiState> =
        combine(chatRepository.conversations(), controls, extras) { conversations, ctrl, extra ->
            MessageUiState(
                conversations = conversations,
                query = ctrl.query,
                unreadOnly = ctrl.unreadOnly,
                searching = ctrl.searching,
                loading = ctrl.loading,
                refreshing = ctrl.refreshing,
                error = ctrl.error,
                fanCount = extra.stats.fans,
                visitorCount = extra.stats.visitors,
                unreadVisitors = extra.stats.unreadVisitors,
                unreadNotices = extra.unreadNotices,
                callEnabled = extra.callEnabled,
            )
        }.stateIn(
            scope = viewModelScope,
            // Eagerly：上游就是本地库的一个 Flow，很便宜；用 WhileSubscribed 的话
            // 没有订阅者时 uiState.value 会一直停在初始值（列表页会被这个坑到）
            started = SharingStarted.Eagerly,
            initialValue = MessageUiState(),
        )

    init {
        refresh()
        ensureProfile()
    }

    fun onQueryChange(value: String) {
        query.value = value
    }

    /** 点顶部搜索图标：展开/收起搜索框，收起时把关键词也清掉。 */
    fun onToggleSearch() {
        searching.update { it.not() }
        if (!searching.value) {
            query.value = ""
        }
    }

    fun onToggleUnreadOnly() {
        unreadOnly.update { it.not() }
    }

    fun onRefresh() {
        refresh()
    }

    /** 删除会话：本地先隐藏（秒生效），服务端那边只对我隐藏（docs/01 P06）。 */
    fun onDelete(peerId: Long) {
        viewModelScope.launch { chatRepository.deleteConversation(peerId) }
    }

    fun onRetry() {
        loading.value = true
        refresh()
    }

    /**
     * 拉一遍服务端的会话列表。
     *
     * 失败只在**本地一条都没有**的时候整页报错：本来就有本地数据的话，
     * 静默刷新失败不该把已经显示出来的列表换成错误页（docs/10 第 3 节，缓存优先）。
     */
    private fun refresh() {
        viewModelScope.launch {
            refreshing.value = true
            // 通知的红点只有服务端有，顺路校准一次（docs/01 P03「系统消息」角标）
            chatRepository.refreshNoticeUnread()
            when (val result = chatRepository.refreshConversations()) {
                is ApiResult.Success -> {
                    error.value = null
                    loading.value = false
                }

                is ApiResult.BizError -> onRefreshFailed(result.message)
                is ApiResult.NetworkError -> onRefreshFailed(result.message)
            }
            refreshing.value = false
        }
    }

    private fun onRefreshFailed(message: String) {
        loading.value = false
        if (uiState.value.conversations.isEmpty()) {
            error.value = message
        }
    }

    /**
     * 功能入口行要显示「有 N 人喜欢了你」「有 N 人看过你的主页」和访客未读角标，
     * 这些数在 `/user/me` 的 `stats` 里。正常是发现页先加载过一遍，这里兜个底。
     */
    private fun ensureProfile() {
        if (userRepository.currentUser.value != null) {
            return
        }
        viewModelScope.launch { userRepository.me() }
    }

    private data class Filters(
        val query: String,
        val unreadOnly: Boolean,
        val searching: Boolean,
    )

    private data class LoadState(
        val loading: Boolean,
        val refreshing: Boolean,
        val error: String?,
    )

    private data class Controls(
        val query: String,
        val unreadOnly: Boolean,
        val searching: Boolean,
        val loading: Boolean,
        val refreshing: Boolean,
        val error: String?,
    )

    private data class Extras(
        val callEnabled: Boolean,
        val stats: ProfileStats,
        val unreadNotices: Int,
    )
}
