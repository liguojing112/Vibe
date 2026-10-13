package com.qichuang.changliao.feature.vip

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.CoinNotEnough
import com.qichuang.changliao.core.data.model.VipPlan
import com.qichuang.changliao.core.data.repository.VipRepository
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
 * 会员中心（P12）状态管理：拉套餐/权益/到期时间，选中套餐，用金币开通。
 *
 * 购买用的订单号和送礼物一样是**必须**的：**失败重试复用同一个订单号**（服务端靠它幂等，
 * 换了就会重复扣钱），成功之后清空、换套餐就重新生成。
 */
@HiltViewModel
class VipCenterViewModel @Inject constructor(
    private val vipRepository: VipRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(VipCenterUiState())

    val uiState: StateFlow<VipCenterUiState> = _uiState.asStateFlow()

    private var loaded = false

    /** 这一次购买的订单号；成功之后清空，下次重新生成。 */
    private var pendingOrderId: String? = null

    /** 进页面时拉一次（同一个 ViewModel 实例里只拉一次，重试走 [onRetry]）。 */
    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        _uiState.update { it.copy(loading = true, error = null) }
        load()
    }

    fun onSelect(plan: VipPlan) {
        // 换套餐就是另一单，订单号要重新生成
        pendingOrderId = null
        _uiState.update { it.copy(selectedId = plan.id) }
    }

    fun onBuy() {
        val state = _uiState.value
        val plan = state.selected ?: return
        if (state.buying) {
            return
        }

        val orderId = pendingOrderId ?: VipRepository.newOrderId().also { pendingOrderId = it }
        _uiState.update { it.copy(buying = true) }

        viewModelScope.launch {
            when (val result = vipRepository.buy(plan.id, orderId)) {
                is ApiResult.Success -> {
                    pendingOrderId = null
                    _uiState.update {
                        it.copy(
                            buying = false,
                            // 到期时间和余额都以服务端返回的为准（铁律 6）
                            isVip = true,
                            expireAt = result.data.expireAt,
                            balance = result.data.balance,
                            boughtCount = it.boughtCount + 1,
                            toast = "开通成功",
                        )
                    }
                }

                is ApiResult.BizError -> {
                    _uiState.update {
                        it.copy(
                            buying = false,
                            coinNotEnough = if (result.code == CODE_COIN_NOT_ENOUGH) {
                                parseCoinNotEnough(result.data, plan.priceCoins.toLong())
                            } else {
                                null
                            },
                            toast = if (result.code == CODE_COIN_NOT_ENOUGH) null else result.message,
                        )
                    }
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(buying = false, toast = result.message)
                }
            }
        }
    }

    fun onRetry() {
        loaded = false
        enter()
    }

    fun onDismissCoinNotEnough() {
        _uiState.update { it.copy(coinNotEnough = null) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    private fun load() {
        viewModelScope.launch {
            when (val result = vipRepository.info()) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        isVip = result.data.isVip,
                        expireAt = result.data.expireAt,
                        plans = result.data.plans,
                        benefits = result.data.benefits,
                        balance = result.data.balance,
                        notice = result.data.notice,
                        // 默认选中第一个：不然底部按钮一直写着「请选择套餐」
                        selectedId = it.selectedId ?: result.data.plans.firstOrNull()?.id,
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
     * 20001 的 `data` 是 `{"balance": 4, "needed": 390}`；解析不出来也要弹（用套餐价兜底），
     * 静默什么都不做的话用户会以为买成功了。
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
