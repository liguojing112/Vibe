package com.qichuang.changliao.feature.auth

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.data.model.Agreement

/** 把 [AgreementViewModel] 和 [AgreementScreen] 连起来。[type] 是路由参数（user / privacy）。 */
@Composable
fun AgreementRoute(
    type: String,
    onBack: () -> Unit,
    viewModel: AgreementViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(type) { viewModel.load(type) }

    AgreementScreen(
        title = if (type == Agreement.TYPE_PRIVACY) "隐私政策" else "用户协议",
        state = state,
        onBack = onBack,
        onRetry = { viewModel.load(type) },
    )
}
