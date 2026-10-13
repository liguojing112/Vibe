package com.qichuang.changliao.feature.income

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.IncomeRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 我的等级与收费（P27）状态管理。
 *
 * 「在线接听」开关控制的是「自己是否出现在发现页的推荐里」（`cl_user.show_in_recommend`）——
 * 用的是**已有的** `/user/settings` 接口（`docs/05` 第 4.8 节的收益接口清单里没有单独的开关接口，
 * 见 Q0-23），所以不用新增后端。
 */
@HiltViewModel
class IncomeLevelViewModel @Inject constructor(
    private val incomeRepository: IncomeRepository,
    private val userRepository: UserRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(IncomeLevelUiState())

    val uiState: StateFlow<IncomeLevelUiState> = _uiState.asStateFlow()

    private var loaded = false

    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        // 开关的当前值跟着当前登录用户走（`/user/me` 里带回来的）
        _uiState.update { it.copy(showInRecommend = userRepository.currentUser.value?.showInRecommend != 0) }
        load()
    }

    /** 切换「在线接听」。 */
    fun onToggleOnline(online: Boolean) {
        if (_uiState.value.switching) {
            return
        }

        // 先本地切过去，失败再切回来（开关类操作，等网络回来再动会显得很卡）
        _uiState.update { it.copy(showInRecommend = online, switching = true) }
        viewModelScope.launch {
            when (val result = userRepository.updateSettings(showInRecommend = if (online) 1 else 0)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(switching = false, toast = if (online) "已开启在线接听" else "已关闭在线接听")
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(switching = false, showInRecommend = !online, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(switching = false, showInRecommend = !online, toast = result.message)
                }
            }
        }
    }

    fun onRetry() {
        loaded = false
        enter()
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    private fun load() {
        _uiState.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            when (val result = incomeRepository.level()) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(loading = false, error = null, level = result.data)
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
            }
        }
    }
}
