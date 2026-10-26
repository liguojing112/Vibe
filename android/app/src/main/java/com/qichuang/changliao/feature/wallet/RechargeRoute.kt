package com.qichuang.changliao.feature.wallet

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.LifecycleResumeEffect
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

    // 客服在后台加完金币、用户回到这一页时，余额要跟着变（T9.7）
    LifecycleResumeEffect(Unit) {
        viewModel.refresh()
        onPauseOrDispose { }
    }

    RechargeScreen(
        state = state,
        onBack = onBack,
        onSelectPack = viewModel::onSelectPack,
        onPay = viewModel::onPay,
        onDismissPay = viewModel::onDismissPay,
        onCheckPaid = viewModel::onCheckPaid,
        onWechatCopied = viewModel::onWechatCopied,
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
