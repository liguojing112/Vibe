package com.qichuang.changliao.feature.main

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.local.UnreadStore
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ChatRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import javax.inject.Inject

/**
 * 主界面（底部导航）状态：速配 Tab 是否显示取决于 `feature.call_enabled`（docs/01 第 4 节），
 * 消息 Tab 的未读角标来自 [UnreadStore]。
 */
@HiltViewModel
class MainViewModel @Inject constructor(
    appConfigRepository: AppConfigRepository,
    unreadStore: UnreadStore,
    chatRepository: ChatRepository,
) : ViewModel() {

    private val selectedTab = MutableStateFlow(MainTab.DISCOVER)

    init {
        // 登录后（主界面出现）就把 WebSocket 连上：连上会补拉一次离线消息，
        // 之后收到推送直接进本地库（docs/10 第 1.4 节）。
        chatRepository.start()
    }

    val uiState: StateFlow<MainUiState> =
        combine(
            appConfigRepository.config,
            selectedTab,
            unreadStore.count,
        ) { config, selected, unread ->
            val tabs = visibleTabs(config?.callEnabled == true)
            MainUiState(
                tabs = tabs,
                selected = if (selected in tabs) selected else tabs.first(),
                badges = if (unread > 0) mapOf(MainTab.MESSAGE.route to unread) else emptyMap(),
            )
        }.stateIn(
            scope = viewModelScope,
            started = SharingStarted.Eagerly,
            initialValue = MainUiState(visibleTabs(false), MainTab.DISCOVER, emptyMap()),
        )

    fun onTabSelected(route: String) {
        MainTab.fromRoute(route)?.let { tab ->
            if (tab in uiState.value.tabs) {
                selectedTab.value = tab
            }
        }
    }

    private fun visibleTabs(callEnabled: Boolean): List<MainTab> =
        if (callEnabled) {
            MainTab.entries.toList()
        } else {
            MainTab.entries.filter { it != MainTab.MATCH }
        }
}
