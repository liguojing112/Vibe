package com.qichuang.changliao.feature.vip

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.navigation.Routes

/**
 * P12 会员中心。三个入口都指到这里：「我」页面的菜单、P08 访客、P09 喜欢我的人。
 */
@Composable
fun VipCenterRoute(
    onBack: () -> Unit,
    onOpenRoute: (String) -> Unit,
    viewModel: VipCenterViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    VipCenterScreen(
        state = state,
        onBack = onBack,
        onSelect = viewModel::onSelect,
        onBuy = viewModel::onBuy,
        onDismissCoinNotEnough = viewModel::onDismissCoinNotEnough,
        onOpenRecharge = { onOpenRoute(Routes.RECHARGE) },
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
