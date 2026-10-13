package com.qichuang.changliao.feature.gift

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.CoinNotEnough
import com.qichuang.changliao.core.data.model.Gift
import com.qichuang.changliao.core.data.repository.GiftRepository
import com.qichuang.changliao.core.data.repository.WalletRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.clJson
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.decodeFromJsonElement
import javax.inject.Inject

/**
 * 礼物面板（P23/P31）状态管理：拉礼物列表 + 余额、选中、赠送。
 *
 * 赠送的订单号是**必须**的（服务端靠它幂等）：这里用「同一个待送礼物复用同一个订单号」的策略 ——
 * 网络失败后用户再点一次不会被重复扣钱（和聊天里重发不换 `client_msg_id` 是一个道理）。
 * 换一个礼物就是另一单了，所以换礼物时会重新生成订单号。
 */
@HiltViewModel
class GiftPanelViewModel @Inject constructor(
    private val giftRepository: GiftRepository,
    private val walletRepository: WalletRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(GiftPanelUiState())

    val uiState: StateFlow<GiftPanelUiState> = _uiState.asStateFlow()

    /** 已经为哪个对象拉过数据（同一个对象再打开面板不重复请求）。 */
    private var loadedFor: Long = 0

    /** 这一次赠送的订单号；成功之后清空，下次重新生成。 */
    private var pendingOrderId: String? = null

    /** 打开发送对象的面板（P23 进页面时 / P31 打开弹窗时都会调，幂等）。 */
    fun enter(receiverId: Long, force: Boolean = false) {
        if (!force && receiverId == loadedFor && _uiState.value.gifts.isNotEmpty()) {
            return
        }
        loadedFor = receiverId
        _uiState.update { it.copy(receiverId = receiverId, loading = true, error = null) }
        load()
    }

    fun onSelect(gift: Gift) {
        // 换礼物就是另一单，订单号要重新生成
        pendingOrderId = null
        _uiState.update { it.copy(selectedId = gift.id) }
    }

    fun onSend() {
        val state = _uiState.value
        val gift = state.selected ?: return
        if (state.sending) {
            return
        }

        val orderId = pendingOrderId ?: GiftRepository.newOrderId().also { pendingOrderId = it }
        _uiState.update { it.copy(sending = true) }

        viewModelScope.launch {
            when (val result = giftRepository.send(state.receiverId, gift.id, orderId)) {
                is ApiResult.Success -> {
                    pendingOrderId = null
                    _uiState.update {
                        it.copy(
                            sending = false,
                            // 余额以服务端返回的为准（铁律 6）
                            balance = result.data.balance,
                            sentCount = it.sentCount + 1,
                            toast = "礼物已送出",
                        )
                    }
                }

                is ApiResult.BizError -> {
                    _uiState.update {
                        it.copy(
                            sending = false,
                            coinNotEnough = if (result.code == CODE_COIN_NOT_ENOUGH) {
                                parseCoinNotEnough(result.data, gift.priceCoins.toLong())
                            } else {
                                null
                            },
                            toast = if (result.code == CODE_COIN_NOT_ENOUGH) null else result.message,
                        )
                    }
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(sending = false, toast = result.message)
                }
            }
        }
    }

    fun onRetry() {
        loadedFor = 0
        enter(_uiState.value.receiverId, force = true)
    }

    fun onDismissCoinNotEnough() {
        _uiState.update { it.copy(coinNotEnough = null) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    private fun load() {
        viewModelScope.launch {
            when (val result = giftRepository.list()) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        gifts = result.data,
                        // 默认选中第一个：不然底部按钮一直写着「请选择礼物」
                        selectedId = it.selectedId ?: result.data.firstOrNull()?.id,
                        loading = false,
                        error = null,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
            }

            loadBalance()
        }
    }

    /** 余额拿不到不影响送礼（送礼的返回里也有余额），所以静默失败。 */
    private suspend fun loadBalance() {
        when (val result = walletRepository.coin()) {
            is ApiResult.Success -> _uiState.update { it.copy(balance = result.data.balance) }
            is ApiResult.BizError -> Unit
            is ApiResult.NetworkError -> Unit
        }
    }

    /**
     * 20001 的 `data` 是 `{"balance": 4, "needed": 120}`；解析不出来也要弹（用礼物价兜底），
     * 静默什么都不做的话用户会以为送出去了。
     */
    private fun parseCoinNotEnough(data: JsonElement?, fallbackNeeded: Long): CoinNotEnough {
        val parsed = data?.let { runCatching { clJson.decodeFromJsonElement<CoinNotEnough>(it) }.getOrNull() }
        if (parsed != null) {
            return parsed
        }

        return CoinNotEnough(balance = _uiState.value.balance, needed = fallbackNeeded)
    }

    companion object {
        /** 余额不足（docs/05 第 3 节），弹 P30。 */
        const val CODE_COIN_NOT_ENOUGH = 20001
    }
}
