package com.qichuang.changliao.feature.gift

import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.Gift
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.feature.common.CoinNotEnoughSheet

/**
 * 聊天页的礼物弹窗（P31，设计图 31）。**和 P23 用的是同一套内容和同一个 ViewModel 类型**
 * （docs/01 P23：「两者用同一个礼物网格组件」）。
 *
 * 余额不足时的 P30 会叠在这个弹窗上面，关掉 P30 回到礼物弹窗，用户可以重新选或去充值。
 */
@Composable
fun GiftSheet(
    state: GiftPanelUiState,
    title: String,
    onSelect: (Gift) -> Unit,
    onSend: () -> Unit,
    onDismiss: () -> Unit,
    onOpenRecharge: () -> Unit,
    onDismissCoinNotEnough: () -> Unit,
    onRetry: () -> Unit,
) {
    ClBottomSheet(title = title, onClose = onDismiss) {
        GiftGridSection(state = state, onSelect = onSelect, onRetry = onRetry)
        GiftSendButton(
            state = state,
            onSend = onSend,
            modifier = Modifier.padding(top = ClTheme.spacing.l),
        )
    }

    state.coinNotEnough?.let { info ->
        CoinNotEnoughSheet(
            info = info,
            onDismiss = onDismissCoinNotEnough,
            onRecharge = {
                onDismissCoinNotEnough()
                onOpenRecharge()
            },
        )
    }
}
