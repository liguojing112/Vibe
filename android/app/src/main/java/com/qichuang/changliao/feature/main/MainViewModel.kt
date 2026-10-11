package com.qichuang.changliao.feature.main

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import javax.inject.Inject

/**
 * 主界面（底部导航）状态：速配 Tab 是否显示取决于 `feature.call_enabled`（docs/01 第 4 节）。
 */
@HiltViewModel
class MainViewModel @Inject constructor(
    appConfigRepository: AppConfigRepository,
) : ViewModel() {

    private val selectedTab = MutableStateFlow(MainTab.DISCOVER)

    val uiState: StateFlow<MainUiState> =
        combine(appConfigRepository.config, selectedTab) { config, selected ->
            val tabs = visibleTabs(config?.callEnabled == true)
            MainUiState(
                tabs = tabs,
                selected = if (selected in tabs) selected else tabs.first(),
            )
        }.stateIn(
            scope = viewModelScope,
            started = SharingStarted.Eagerly,
            initialValue = MainUiState(visibleTabs(false), MainTab.DISCOVER),
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
