package com.qichuang.changliao.feature.task

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.data.model.DailyTask

/**
 * P11 每日任务。
 *
 * 「签到」和「领取」调接口；「去完成」「去认识」是页面跳转 ——
 * 跳哪儿由服务端给的 `action` 决定，这里只做分派（完成条件客户端不判断）。
 */
@Composable
fun DailyTaskRoute(
    onBack: () -> Unit,
    onOpenEditProfile: () -> Unit,
    onOpenDiscover: () -> Unit,
    viewModel: DailyTaskViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    DailyTaskScreen(
        state = state,
        onBack = onBack,
        onAction = { task ->
            when (task.action) {
                DailyTask.ACTION_SIGN_IN -> viewModel.onSignIn()
                DailyTask.ACTION_CLAIM -> viewModel.onClaim(task.id)
                DailyTask.ACTION_EDIT_PROFILE -> onOpenEditProfile()
                DailyTask.ACTION_DISCOVER -> onOpenDiscover()
            }
        },
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
