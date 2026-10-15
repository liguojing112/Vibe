package com.qichuang.changliao.feature.match

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.data.model.CallKind

/**
 * 把 [MatchViewModel] 和两个页面连起来。
 *
 * P02（视频速配）和 P16（语音大厅）是**同一个东西的两面**：同一份数据、同一对 chips，
 * 只是展示方式和用哪一栏单价不同 —— 所以共用一个 ViewModel，靠 [kind] 选页面。
 * 两个页面在导航图里是两个目的地（各自的返回栈），切 chips 就是在这两个目的地之间跳。
 */
@Composable
fun MatchRoute(
    kind: CallKind,
    onSwitchKind: (CallKind) -> Unit,
    onOpenFilter: () -> Unit,
    onOpenWallet: () -> Unit,
    onOpenUser: (Long) -> Unit,
    onCall: (Long) -> Unit,
    viewModel: MatchViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(kind) { viewModel.enter(kind) }

    when (kind) {
        CallKind.VIDEO -> MatchScreen(
            state = state,
            onSwitchKind = onSwitchKind,
            onOpenFilter = onOpenFilter,
            onOpenWallet = onOpenWallet,
            onOpenUser = onOpenUser,
            onCall = onCall,
            onRetry = { viewModel.enter(kind) },
        )

        CallKind.VOICE -> VoiceHallScreen(
            state = state,
            onSwitchKind = onSwitchKind,
            onOpenFilter = onOpenFilter,
            onOpenWallet = onOpenWallet,
            onOpenUser = onOpenUser,
            onCall = onCall,
            onRetry = { viewModel.enter(kind) },
        )
    }
}
