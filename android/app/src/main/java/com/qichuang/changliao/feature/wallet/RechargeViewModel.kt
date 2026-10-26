package com.qichuang.changliao.feature.wallet

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.RechargeOrder
import com.qichuang.changliao.core.data.model.RechargePack
import com.qichuang.changliao.core.data.repository.WalletRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 充值页（P22）：拉档位与客服信息，管选中状态，以及码支付在线充值。
 *
 * 在线支付只负责创建订单和轮询状态：二维码内容、唤醒支付宝都在页面层，
 * 金额和到账金币全部以服务端返回为准。
 */
@HiltViewModel
class RechargeViewModel @Inject constructor(
    private val walletRepository: WalletRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(RechargeUiState())

    val uiState: StateFlow<RechargeUiState> = _uiState.asStateFlow()

    /** 支付结果轮询；关弹窗或成功/关闭时取消。 */
    private var pollJob: Job? = null

    init {
        load()
    }

    fun onRetry() {
        _uiState.update { it.copy(loading = true, error = null) }
        load()
    }

    /**
     * 从别的页面回来时静默拉一次。
     *
     * **这个产品没有线上支付**（`R1-2`）：用户看到「联系客服充值」→ 客服在后台加金币 →
     * 用户还停在这一页。不加这个，页面上的余额永远是进页面那一刻的旧值，
     * 用户会以为没充上（T9.7 查出来的）。
     */
    fun refresh() {
        load()
    }

    fun onSelectPack(pack: RechargePack) {
        _uiState.update { it.copy(selectedPack = pack) }
    }

    /** 复制完客服微信给个提示（复制本身在页面里用系统剪贴板做）。 */
    fun onWechatCopied() {
        _uiState.update { it.copy(toast = "客服微信已复制") }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    /** 点「立即支付」：按选中档位下单并取二维码链接。 */
    fun onPay() {
        val pack = _uiState.value.selectedPack ?: return
        if (_uiState.value.paying) {
            return
        }

        _uiState.update { it.copy(paying = true) }
        viewModelScope.launch {
            when (val result = walletRepository.createRechargeOrder(pack.index)) {
                is ApiResult.Success -> {
                    _uiState.update { it.copy(paying = false, payOrder = result.data) }
                    pollOrder(result.data.orderNo)
                }

                is ApiResult.BizError -> _uiState.update { it.copy(paying = false, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(paying = false, toast = result.message) }
            }
        }
    }

    /** 用户点了「我已支付」：立刻查一次，不重开轮询。 */
    fun onCheckPaid() {
        val order = _uiState.value.payOrder ?: return
        viewModelScope.launch {
            when (val result = walletRepository.rechargeOrderStatus(order.orderNo)) {
                is ApiResult.Success -> applyOrderStatus(result.data)
                is ApiResult.BizError -> _uiState.update { it.copy(toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(toast = result.message) }
            }
        }
    }

    /** 关掉二维码弹窗：停止轮询，订单留在服务端，等回调到账。 */
    fun onDismissPay() {
        pollJob?.cancel()
        pollJob = null
        _uiState.update { it.copy(payOrder = null) }
    }

    override fun onCleared() {
        pollJob?.cancel()
        pollJob = null
        super.onCleared()
    }

    /**
     * 每 3 秒查一次本地订单状态，最多 10 分钟。
     *
     * 单次网络错误不打断轮询：用户可能只是切了网络；真到超时就提示重新下单。
     */
    private fun pollOrder(orderNo: String) {
        pollJob?.cancel()
        pollJob = viewModelScope.launch {
            repeat(POLL_MAX_TIMES) {
                delay(POLL_INTERVAL_MS)
                if (!isActive) {
                    return@launch
                }

                when (val result = walletRepository.rechargeOrderStatus(orderNo)) {
                    is ApiResult.Success -> {
                        if (applyOrderStatus(result.data)) {
                            return@launch
                        }
                    }
                    // 单次失败继续等，别让用户一断网就丢掉支付结果
                    is ApiResult.BizError, is ApiResult.NetworkError -> Unit
                }
            }

            _uiState.update {
                it.copy(payOrder = null, paying = false, toast = "支付结果确认超时，请重新下单或联系客服")
            }
        }
    }

    /**
     * 应用一次订单状态。返回 true 表示订单已终结（成功/关闭），调用方停止轮询。
     */
    private fun applyOrderStatus(order: RechargeOrder): Boolean {
        return when {
            order.isPaid -> {
                _uiState.update {
                    it.copy(payOrder = null, paying = false, toast = "充值成功，${order.coins} 金币已到账")
                }
                load()
                true
            }

            order.isClosed -> {
                _uiState.update {
                    it.copy(payOrder = null, paying = false, toast = "支付订单已关闭，请重新下单")
                }
                true
            }

            else -> {
                _uiState.update { it.copy(payOrder = order) }
                false
            }
        }
    }

    private fun load() {
        viewModelScope.launch {
            when (val result = walletRepository.rechargeInfo()) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        info = result.data,
                        // 默认选中第一个档位：不然「本次到账」空着，用户不知道要点一下
                        selectedPack = result.data.packs.firstOrNull(),
                        loading = false,
                        error = null,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
            }
        }
    }

    private companion object {
        const val POLL_INTERVAL_MS = 3_000L
        const val POLL_MAX_TIMES = 200
    }
}
