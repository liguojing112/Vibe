package com.qichuang.changliao.feature.mine

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/**
 * P04 我的。底部导航第三个 Tab（男女菜单不一样，见 [MineUiState]）。
 *
 * 进页面会拉一次 `/user/me`，把统计数和余额刷新到最新；页面本身不缓存
 * （资料在别的地方改完回来就该是新的）。
 */
@Composable
fun MineRoute(
    onOpenRoute: (String) -> Unit,
    viewModel: MineViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    MineScreen(
        state = state,
        onOpenRoute = onOpenRoute,
        onRetry = viewModel::onRefresh,
    )
}
