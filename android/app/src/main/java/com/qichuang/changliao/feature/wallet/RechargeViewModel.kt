package com.qichuang.changliao.feature.wallet

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.RechargePack
import com.qichuang.changliao.core.data.repository.WalletRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** 充值页（P22）：拉档位与客服信息，管选中状态。**没有下单动作**（App 里不做支付）。 */
@HiltViewModel
class RechargeViewModel @Inject constructor(
    private val walletRepository: WalletRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(RechargeUiState())

    val uiState: StateFlow<RechargeUiState> = _uiState.asStateFlow()

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
}
