package com.qichuang.changliao.feature.call

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.BuildConfig
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.navigation.Routes

/**
 * P17~P21、P25 的接线（docs/01 第 4 节）。
 *
 * **这些页面只做界面、不做功能**：没有通话状态机、没有信令、没有扣费。
 * P17 点「确认拨打」只弹一句「功能暂未开放」；P18~P21 用的是本地假数据，
 * 走一整套全靠 P18 上那两颗「模拟」按钮（真产品里接通与否是对面决定的）。
 *
 * 每个页面各用一个 [CallViewModel]（各自拉一次 `/user/{id}` 拿对方的昵称头像和单价）——
 * 演示流程里多点几次请求，不值得为此引入跨页面的共享状态。
 */
@Composable
fun CallConfirmRoute(
    userId: Long,
    kind: CallKind,
    onBack: () -> Unit,
    onOpenRoute: (String) -> Unit,
    viewModel: CallViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(userId, kind) { viewModel.enter(userId, kind) }

    CallConfirmScreen(
        state = state,
        onBack = onBack,
        onConfirm = viewModel::onConfirm,
        // 长按进 P18 演示：**只在 debug 包里**（docs/01 P17 明确要求的）
        onDemo = if (BuildConfig.DEBUG) {
            { onOpenRoute(Routes.callWaiting(userId, kind)) }
        } else {
            null
        },
        onToastShown = viewModel::onToastShown,
        onRetry = { viewModel.enter(userId, kind, force = true) },
    )
}

@Composable
fun CallWaitingRoute(
    userId: Long,
    kind: CallKind,
    onBack: () -> Unit,
    onOpenRoute: (String) -> Unit,
    viewModel: CallViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(userId, kind) { viewModel.enter(userId, kind) }

    CallWaitingScreen(
        peerName = state.peerName,
        peerAvatar = state.peerAvatar,
        kind = kind,
        price = state.price,
        onAccepted = {
            onOpenRoute(
                if (kind == CallKind.VOICE) Routes.voiceCall(userId) else Routes.videoCall(userId),
            )
        },
        // 无人接听：退回 P17（真实产品里是「对方未接听」的中间态，本期不做）
        onNoAnswer = onBack,
        onCancel = onBack,
    )
}

@Composable
fun CallInProgressRoute(
    userId: Long,
    kind: CallKind,
    onOpenRoute: (String) -> Unit,
    viewModel: CallViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(userId, kind) { viewModel.enter(userId, kind) }

    CallInProgressScreen(
        kind = kind,
        peerName = state.peerName,
        peerAvatar = state.peerAvatar,
        price = state.price,
        balance = state.balance,
        // 挂断把本地数出来的秒数带给 P21，账单按它算
        onEnd = { seconds -> onOpenRoute(Routes.callBill(userId, kind, seconds)) },
    )
}

@Composable
fun CallBillRoute(
    userId: Long,
    kind: CallKind,
    seconds: Int,
    onBack: () -> Unit,
    onOpenRoute: (String) -> Unit,
    viewModel: CallViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(userId, kind) { viewModel.enter(userId, kind) }

    CallBillScreen(
        peerName = state.peerName,
        kind = kind,
        bill = CallBill.of(seconds, state.price, state.balance),
        onBack = onBack,
        // 「查看明细」进的是**真的**金币流水页：那里不会出现这次通话的记录（因为没真扣过）
        onOpenLogs = { onOpenRoute(Routes.COIN_LOGS) },
        onContinueChat = { onOpenRoute(Routes.chat(userId)) },
        onCallAgain = { onOpenRoute(Routes.callConfirm(userId, kind)) },
    )
}

@Composable
fun CallRecordsRoute(
    onBack: () -> Unit,
    onOpenDiscover: () -> Unit,
) {
    CallRecordsScreen(onBack = onBack, onOpenDiscover = onOpenDiscover)
}
