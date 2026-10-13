package com.qichuang.changliao.feature.task

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.TaskReward
import com.qichuang.changliao.core.data.repository.TaskRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 每日任务（P11）状态管理：拉列表、签到、领奖。
 *
 * 签到和领奖成功之后**重新拉一次列表**，而不是在本地改状态：任务的「已完成 / 可领取」由服务端说了算，
 * 本地推一遍容易和真正的判断逻辑跑偏（多写一份规则就多一处会不一致的地方）。
 * 奖励文案（「+5 金币」）用服务端返回的那份做 toast。
 */
@HiltViewModel
class DailyTaskViewModel @Inject constructor(
    private val taskRepository: TaskRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(DailyTaskUiState())

    val uiState: StateFlow<DailyTaskUiState> = _uiState.asStateFlow()

    private var loaded = false

    /** 进页面时拉一次（重试走 [onRetry]）。 */
    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        load()
    }

    /** 签到（一次点击就拿到奖励，服务端把签到任务的奖励一起发了）。 */
    fun onSignIn() {
        if (_uiState.value.acting) {
            return
        }
        act { taskRepository.signIn() }
    }

    /** 领奖。服务端会重新判断完成条件，没完成会返回 40001。 */
    fun onClaim(taskId: Long) {
        if (_uiState.value.acting) {
            return
        }
        act { taskRepository.claim(taskId) }
    }

    fun onRetry() {
        loaded = false
        enter()
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    private fun act(block: suspend () -> ApiResult<TaskReward>) {
        _uiState.update { it.copy(acting = true) }
        viewModelScope.launch {
            when (val result = block()) {
                is ApiResult.Success -> {
                    // 奖励文案是服务端拼的（「+5 金币」/「+20 积分」），客户端不拼
                    _uiState.update { it.copy(acting = false, toast = "已领取 ${result.data.rewardText}") }
                    load()
                }

                is ApiResult.BizError -> _uiState.update { it.copy(acting = false, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(acting = false, toast = result.message) }
            }
        }
    }

    private fun load() {
        _uiState.update { it.copy(loading = it.tasks.isEmpty(), error = null) }
        viewModelScope.launch {
            when (val result = taskRepository.list()) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(tasks = result.data, loading = false, error = null)
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
            }
        }
    }
}
