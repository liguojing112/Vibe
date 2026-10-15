package com.qichuang.changliao.feature.call

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.data.model.UserDetail
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
 * 拨打前价格确认（docs/01 P17）。
 *
 * **只读 + 只提示**：这个 ViewModel 里除了「拿对方的资料来显示」之外**不发任何请求** ——
 * `onConfirm()` 只往界面塞一句 toast。这是 `01` 第 4 节的硬要求：
 * 「在 P17 点『确认拨打』时，弹出提示『功能暂未开放』，**不发请求、不扣币**」。
 *
 * 以后接真的音视频服务时，`onConfirm()` 里才是发起呼叫的地方；现在**不能**为了「先占位」
 * 加一个 API 调用 —— 那等于「点一下就开始计费」，而根本没有人接。
 */
@HiltViewModel
class CallViewModel @Inject constructor(
    private val userRepository: UserRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(CallUiState())

    val uiState: StateFlow<CallUiState> = _uiState.asStateFlow()

    private var loadedFor: Pair<Long, CallKind>? = null

    fun enter(peerId: Long, kind: CallKind, force: Boolean = false) {
        // 已经加载过同一个人就不用再拉一次（从 P19 返回、来回切页面时不重复请求）
        if (! force && loadedFor == peerId to kind && _uiState.value.error == null) {
            return
        }
        loadedFor = peerId to kind

        _uiState.update { it.copy(kind = kind, peerId = peerId, loading = true, error = null) }

        viewModelScope.launch {
            applyMyBalance()

            when (val result = userRepository.detail(peerId)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        peerName = result.data.nickname,
                        peerAvatar = result.data.avatar.ifBlank { null },
                        peerOnline = result.data.online,
                        peerVerified = result.data.verified,
                        price = priceOf(result.data, kind),
                        loading = false,
                        error = null,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }

                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
            }
        }
    }

    /**
     * 「确认拨打」：**只弹提示**。
     *
     * 见类注释 —— 这里刻意不调任何接口。
     */
    fun onConfirm() {
        _uiState.update { it.copy(toast = NOT_OPEN_HINT) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    private suspend fun applyMyBalance() {
        val profile = userRepository.currentUser.value ?: when (val result = userRepository.me()) {
            is ApiResult.Success -> result.data
            else -> null
        } ?: return

        _uiState.update { it.copy(balance = profile.coinBalance ?: 0) }
    }

    /** 开关关着时服务端不返回这两项（`UserDetailPrice` 里就是 null），页面据此不显示价格。 */
    private fun priceOf(detail: UserDetail, kind: CallKind): Long {
        val price = detail.price ?: return 0

        return when (kind) {
            CallKind.VOICE -> price.voicePrice ?: 0
            CallKind.VIDEO -> price.videoPrice ?: 0
        }
    }

    companion object {
        /** `docs/01` 第 4 节写死的那句话。 */
        const val NOT_OPEN_HINT = "功能暂未开放"
    }
}
