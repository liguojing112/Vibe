package com.qichuang.changliao.feature.match

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.DiscoverRepository
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
 * 速配（P02）/ 语音大厅（P16）。
 *
 * 两个页面共用这一个 ViewModel，靠 [enter] 传进来的 [CallKind] 区分：数据是同一个接口
 * （`/discover` 的 `video` / `voice` Tab，服务端取的都是「现在能接」的人），
 * 只是 P02 用视频单价、P16 用语音单价。
 *
 * **没有任何真实通话**：这里只负责把「谁现在能接」列出来，点拨打走到 P17 就停了（docs/01 第 4 节）。
 */
@HiltViewModel
class MatchViewModel @Inject constructor(
    private val discoverRepository: DiscoverRepository,
    private val userRepository: UserRepository,
    appConfigRepository: AppConfigRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(MatchUiState())

    val uiState: StateFlow<MatchUiState> = _uiState.asStateFlow()

    private var userId: Long = 0

    /** 最近一次的启动配置：`enter` 里要按 kind 重新取示例价，所以得留一份。 */
    private var config: AppConfig? = null

    init {
        viewModelScope.launch {
            appConfigRepository.config.collect { value ->
                config = value
                _uiState.update { it.copy(priceMin = priceMinOf(it.kind, value)) }
            }
        }
    }

    fun enter(kind: CallKind) {
        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    kind = kind,
                    // 示例价要跟着 kind 走：P16 用语音单价、P02 用视频单价。
                    // 只在配置变化时更新的话，先建 P02 再进 P16 会一直显示视频价。
                    priceMin = priceMinOf(kind, config),
                    loading = it.users.isEmpty(),
                )
            }

            applyBalance()

            if (userId <= 0) {
                return@launch
            }

            // 和 P01 一样收整条流：先出缓存（秒开）再出网络结果（静默刷新）
            discoverRepository.firstPage(kind.apiValue, userId, DiscoverFilter()).collect { result ->
                when (result) {
                    is ApiResult.Success -> _uiState.update {
                        it.copy(users = result.data.list, loading = false, error = null)
                    }

                    is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }

                    is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
                }
            }
        }
    }

    /** 男用户显示金币、女用户显示积分（和 P01 一个口径）。 */
    private suspend fun applyBalance() {
        val profile = userRepository.currentUser.value ?: when (val result = userRepository.me()) {
            is ApiResult.Success -> result.data
            else -> null
        } ?: return

        userId = profile.id
        val male = profile.gender == Gender.MALE
        _uiState.update {
            it.copy(
                balanceAmount = if (male) profile.coinBalance?.toString() else profile.pointBalance?.toString(),
                balanceUnit = if (male) MoneyFormat.UNIT_COIN else MoneyFormat.UNIT_POINT,
            )
        }
    }

    private fun priceMinOf(kind: CallKind, config: AppConfig?): Int = when (kind) {
        CallKind.VOICE -> config?.voicePriceMin ?: 0
        CallKind.VIDEO -> config?.videoPriceMin ?: 0
    }
}
