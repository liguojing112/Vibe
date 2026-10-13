package com.qichuang.changliao.feature.chat

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.navigation.Routes

/**
 * 私信聊天页（P06）的路由层。
 *
 * @param userId 聊天对象的 id（从路由参数来）
 */
@Composable
fun ChatRoute(
    userId: Long,
    onBack: () -> Unit,
    onOpenRoute: (String) -> Unit,
    viewModel: ChatViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(userId) { viewModel.enter(userId) }

    ChatScreen(
        state = state,
        onBack = onBack,
        onOpenProfile = { onOpenRoute(Routes.userDetail(userId)) },
        onOpenRecharge = { onOpenRoute(Routes.RECHARGE) },
        onOpenGift = { onOpenRoute(Routes.giftPanel(userId)) },
        // 语音/视频页面在 P8 才做（本期只做页面），先按 docs/01 第 4 节跳过去
        onOpenCall = { onOpenRoute(Routes.callConfirm(userId)) },
        onOpenFeeRules = viewModel::onOpenFeeRules,
        onDismissFeeRules = viewModel::onDismissFeeRules,
        onDismissCoinNotEnough = viewModel::onDismissCoinNotEnough,
        onInputChange = viewModel::onInputChange,
        onSend = viewModel::onSend,
        onResend = viewModel::onResend,
        onImagePicked = viewModel::onImagePicked,
        onLoadMore = viewModel::onLoadMore,
        onRetry = viewModel::onRetry,
        onToastShown = viewModel::onToastShown,
    )
}
