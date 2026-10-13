package com.qichuang.changliao.feature.task

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.DailyTask
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClGradientHero
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTaskCard
import com.qichuang.changliao.core.designsystem.component.ClTaskItem
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClGradients
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 每日任务（P11，设计图 11）。
 *
 * 结构：橙黄横幅 → 一条条任务卡（图标 + 名称 + 说明 + 右侧按钮）。
 *
 * 页面**不做任何完成条件的判断**：状态和按钮文案都是服务端给的，
 * 客户端只按 `action` 决定点下去是调接口还是跳页面（`docs/01` P11）。
 */
@Composable
fun DailyTaskScreen(
    state: DailyTaskUiState,
    onBack: () -> Unit,
    onAction: (DailyTask) -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val toast = rememberClToast()
    LaunchedEffect(state.toast) {
        state.toast?.let {
            toast.show(it)
            onToastShown()
        }
    }

    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "每日任务", onBack = onBack) },
    ) {
        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            state.isEmpty -> ClEmpty(
                title = "暂时没有任务",
                desc = "后台配好任务之后这里就会出现",
            )

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        // 底部留一点空，最后一张卡不贴着屏幕边
                        bottom = ClTheme.spacing.xl,
                    ),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClGradientHero(
                    gradient = ClGradients.banner,
                    title = state.bannerTitle,
                    subtitle = state.bannerDesc,
                    modifier = Modifier.padding(top = ClTheme.spacing.l),
                )

                state.tasks.forEach { task ->
                    ClTaskCard(
                        task = task.toItem(),
                        onAction = { onAction(task) },
                    )
                }
            }
        }
    }
}

/** 任务 → 卡片。图标后台没配时给个默认的，别让方块空着。 */
private fun DailyTask.toItem() = ClTaskItem(
    id = id,
    title = name,
    desc = description,
    actionText = buttonText,
    icon = icon.ifBlank { "✅" },
    done = claimed,
)
