package com.qichuang.changliao.feature.auth

import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.UiState
import com.qichuang.changliao.core.data.model.Agreement
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 协议页（docs/01 P00）：用户协议 / 隐私政策，内容由后台编辑。
 *
 * 必须有加载 / 空 / 出错重试 / 内容四态（docs/03 第 6 节第 3 条）。
 */
@Composable
fun AgreementScreen(
    title: String,
    state: UiState<Agreement>,
    onBack: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        topBar = { ClTopBar(title = title, onBack = onBack) },
    ) {
        when (state) {
            is UiState.Loading -> ClLoading()

            is UiState.Empty -> ClEmpty(
                title = "暂无内容",
                desc = "协议内容还没有配置，请联系客服",
            )

            is UiState.Error -> ClErrorRetry(message = state.message, onRetry = onRetry)

            is UiState.Content -> Text(
                text = state.data.content,
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(ClTheme.spacing.l),
            )
        }
    }
}
