package com.qichuang.changliao.feature.chat

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.navigation.Routes
import com.qichuang.changliao.feature.gift.GiftPanelViewModel
import com.qichuang.changliao.feature.gift.GiftSheet

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

    // 礼物弹窗（P31）用**另一个** ViewModel 实例（同一个导航目的地里可以有两个，
    // 用 key 区分），这样礼物面板的状态和聊天状态互不干扰。
    val giftViewModel: GiftPanelViewModel = hiltViewModel(key = "gift")
    val giftState by giftViewModel.uiState.collectAsStateWithLifecycle()
    var giftVisible by remember { mutableStateOf(false) }

    LaunchedEffect(userId) { viewModel.enter(userId) }

    LaunchedEffect(giftVisible) {
        if (giftVisible) {
            giftViewModel.enter(userId)
        }
    }

    // 送出去之后聊天页的收费条要跟着刷新（余额变了）
    LaunchedEffect(giftState.sentCount) {
        if (giftState.sentCount > 0) {
            viewModel.refreshFee()
        }
    }

    ChatScreen(
        state = state,
        onBack = onBack,
        onOpenProfile = { onOpenRoute(Routes.userDetail(userId)) },
        onOpenRecharge = { onOpenRoute(Routes.RECHARGE) },
        // 聊天页的「送礼物」是**弹窗**（P31），不是跳页面（P23 才是整页）
        onOpenGift = { giftVisible = true },
        // 语音/视频只做页面（docs/01 第 4 节）：点进去是 P17，确认拨打只提示「功能暂未开放」
        onOpenCall = { kind -> onOpenRoute(Routes.callConfirm(userId, kind)) },
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

    if (giftVisible) {
        GiftSheet(
            state = giftState,
            title = "送给 ${state.peer.nickname}",
            onSelect = giftViewModel::onSelect,
            onSend = giftViewModel::onSend,
            onDismiss = { giftVisible = false },
            onOpenRecharge = { onOpenRoute(Routes.RECHARGE) },
            onDismissCoinNotEnough = giftViewModel::onDismissCoinNotEnough,
            onRetry = giftViewModel::onRetry,
        )
    }
}
