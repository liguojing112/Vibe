package com.qichuang.changliao.feature.wallet

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/**
 * P22 金币充值。
 *
 * 从 P30（余额不足）跳过来时是**压在聊天页上面**的一层，返回就回到聊天页，
 * 那边的 ViewModel 还活着，所以输入框里的字自然还在（docs/01 P22 的要求）。
 */
@Composable
fun RechargeRoute(
    onBack: () -> Unit,
    viewModel: RechargeViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    RechargeScreen(
        state = state,
        onBack = onBack,
        onSelectPack = viewModel::onSelectPack,
        onWechatCopied = viewModel::onWechatCopied,
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
