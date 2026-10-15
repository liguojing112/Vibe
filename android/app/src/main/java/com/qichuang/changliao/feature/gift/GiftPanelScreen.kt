package com.qichuang.changliao.feature.gift

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.Gift
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.feature.common.CoinNotEnoughSheet

/**
 * 礼物面板整页版（P23，设计图 23）。
 *
 * 顶部标题「送给 X」+ 礼物网格 + 底部「赠送 XX · N 金币」。
 *
 * [peerName] 由上一页（P05）通过路由带过来，**不是在这里再查一次 `/user/{id}`** ——
 * 那个接口会**记一次访客**（T3.8 的行为），为了一个标题多记一次访客不值当。
 * 也正因为如此，设计图里那张「对方头像 + 昵称」的卡片没有做：头像一个请求都不打拿不到。
 */
@Composable
fun GiftPanelScreen(
    state: GiftPanelUiState,
    peerName: String,
    onBack: () -> Unit,
    onSelect: (Gift) -> Unit,
    onSend: () -> Unit,
    onDismissCoinNotEnough: () -> Unit,
    onOpenRecharge: () -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "送给 " + peerName.ifBlank { "TA" }, onBack = onBack) },
        fixedBottom = {
            ClFixedBottomArea {
                GiftSendButton(state = state, onSend = onSend)
            }
        },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = ClTheme.spacing.l),
        ) {
            Text(
                text = "送一份小心意，表达此刻的心情",
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
                modifier = Modifier.padding(vertical = ClTheme.spacing.m),
            )

            GiftGridSection(state = state, onSelect = onSelect, onRetry = onRetry)
        }
    }

    state.coinNotEnough?.let { info ->
        CoinNotEnoughSheet(
            info = info,
            onDismiss = onDismissCoinNotEnough,
            // 去充值之前先关弹窗：不然从充值页返回时它又冒出来（T4.8 走查踩到的）
            onRecharge = {
                onDismissCoinNotEnough()
                onOpenRecharge()
            },
        )
    }
}
