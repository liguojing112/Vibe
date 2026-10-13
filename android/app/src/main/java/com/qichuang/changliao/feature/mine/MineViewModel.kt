package com.qichuang.changliao.feature.mine

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** 拉不到资料时显示的话（有资料就不显示）。 */
private const val FAIL_MESSAGE = "资料加载失败，请重试"

/**
 * 我的（P04）状态管理。
 *
 * 资料**跟着 `currentUser` 这个流走**（登录、编辑资料、切号都会让它变），
 * 进页面时再主动拉一次 `/user/me` 把统计数和余额刷新到最新。
 *
 * 通话记录那一项受 `feature.call_enabled` 控制，所以也要读启动配置。
 */
@HiltViewModel
class MineViewModel @Inject constructor(
    private val userRepository: UserRepository,
    appConfigRepository: AppConfigRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(MineUiState())

    val uiState: StateFlow<MineUiState> = _uiState.asStateFlow()

    private var loaded = false

    init {
        viewModelScope.launch {
            userRepository.currentUser.collect { profile ->
                _uiState.update { it.copy(user = profile, loading = profile == null) }
            }
        }
        viewModelScope.launch {
            appConfigRepository.config.collect { config ->
                _uiState.update { it.copy(callEnabled = config?.callEnabled == true) }
            }
        }
    }

    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        refresh()
    }

    /** 下拉刷新。 */
    fun onRefresh() {
        _uiState.update { it.copy(refreshing = true) }
        refresh()
    }

    /** 有资料就别报错（页面照常显示上一次的），什么都没拉到才让页面显示错误。 */
    private fun errorFor(state: MineUiState): String? = if (state.user == null) FAIL_MESSAGE else null

    /**
     * 拉一次 `/user/me`。
     *
     * 失败**不整页报错**：`currentUser` 里已经有上一次的资料了，页面照常显示，
     * 只有真的什么都没有（刚登录还没拉到）时才让页面显示错误。
     */
    private fun refresh() {
        viewModelScope.launch {
            when (val result = userRepository.me()) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(refreshing = false, loading = false, error = null)
                }

                // 失败一定要把 loading 收掉：不然「没有资料 + 拉失败」会一直转圈，
                // 页面永远进不到错误态（用户只能杀进程）
                is ApiResult.BizError -> _uiState.update {
                    it.copy(refreshing = false, loading = false, error = errorFor(it))
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(refreshing = false, loading = false, error = errorFor(it))
                }
            }
        }
    }
}
