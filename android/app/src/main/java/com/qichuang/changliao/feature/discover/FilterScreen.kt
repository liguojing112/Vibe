package com.qichuang.changliao.feature.discover

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClNoticeCard
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClRangeSlider
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClSwitch
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 筛选条件页（docs/01 P07，设计图 07）。
 *
 * 底部「重置」「查看匹配的人」；筛选条件保存到本地，回到 P01 生效。
 *
 * @param onboarding 注册后的偏好引导：换标题、去掉返回箭头、右上角给「跳过」，
 *                   底部主按钮改成「开始遇见」。
 */
@Composable
fun FilterScreen(
    state: FilterUiState,
    onCitySelected: (String) -> Unit,
    onAgeRangeChange: (ClosedFloatingPointRange<Float>) -> Unit,
    onPurposesChange: (Set<String>) -> Unit,
    onVerificationsChange: (Set<String>) -> Unit,
    onOnlineOnlyChange: (Boolean) -> Unit,
    onReset: () -> Unit,
    onApply: () -> Unit,
    onRetry: () -> Unit,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
    onboarding: Boolean = false,
    onSkip: (() -> Unit)? = null,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = {
            ClTopBar(
                title = if (onboarding) "你的偏好" else "筛选条件",
                onBack = if (onboarding) null else onBack,
                action = if (onboarding && onSkip != null) {
                    @Composable { ClTextButton(text = "跳过", onClick = onSkip) }
                } else {
                    null
                },
            )
        },
        fixedBottom = {
            ClFixedBottomArea {
                ClSecondaryButton(
                    text = "重置",
                    fillMaxWidth = false,
                    modifier = Modifier.weight(1f),
                    onClick = onReset,
                )
                ClPrimaryButton(
                    text = if (onboarding) "开始遇见" else "查看匹配的人",
                    fillMaxWidth = false,
                    modifier = Modifier.weight(1f),
                    onClick = onApply,
                )
            }
        },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
            }
        }

        when {
            state.loading -> ClLoading()

            state.loadError != null -> ClErrorRetry(message = state.loadError, onRetry = onRetry)

            else -> FilterForm(
                state = state,
                onCitySelected = onCitySelected,
                onAgeRangeChange = onAgeRangeChange,
                onPurposesChange = onPurposesChange,
                onVerificationsChange = onVerificationsChange,
                onOnlineOnlyChange = onOnlineOnlyChange,
                onboarding = onboarding,
            )
        }
    }
}

@Composable
private fun FilterForm(
    state: FilterUiState,
    onCitySelected: (String) -> Unit,
    onAgeRangeChange: (ClosedFloatingPointRange<Float>) -> Unit,
    onPurposesChange: (Set<String>) -> Unit,
    onVerificationsChange: (Set<String>) -> Unit,
    onOnlineOnlyChange: (Boolean) -> Unit,
    onboarding: Boolean = false,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = ClTheme.spacing.l),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
    ) {
        if (onboarding) {
            Text(
                text = "选几个条件，我们会优先推荐符合的人。以后随时可以在筛选里改。",
                style = ClTheme.typography.tiny,
                color = ClTheme.colors.inkSecondary,
            )
        }

        FilterSection(title = "所在城市") {
            ClChipGroup(
                options = state.cityOptions,
                selected = state.selectedCities,
                onSelectionChange = { labels ->
                    labels.lastOrNull()?.let(onCitySelected)
                },
            )
        }

        FilterSection(title = ageLabel(state)) {
            ClRangeSlider(
                value = state.draft.ageMin.toFloat()..state.draft.ageMax.toFloat(),
                onValueChange = onAgeRangeChange,
                valueRange = DiscoverFilter.DEFAULT_AGE_MIN.toFloat()..
                    DiscoverFilter.DEFAULT_AGE_MAX.toFloat(),
            )
        }

        FilterSection(title = "交友期待") {
            ClChipGroup(
                options = state.purposes,
                selected = state.selectedPurposes,
                onSelectionChange = onPurposesChange,
                multi = true,
            )
        }

        FilterSection(title = "认证状态") {
            ClChipGroup(
                options = listOf(FilterUiState.VERIFIED_REAL_NAME, FilterUiState.VERIFIED_COMPLETE),
                selected = state.selectedVerifications,
                onSelectionChange = onVerificationsChange,
                multi = true,
            )
        }

        ClSettingRow(
            label = "只看在线",
            desc = "只显示最近活跃的人",
            trailing = {
                ClSwitch(checked = state.draft.onlineOnly, onCheckedChange = onOnlineOnlyChange)
            },
        )

        ClNoticeCard(
            title = "让相遇更合拍",
            content = "选择你在意的条件，系统会优先展示相似的人。适当放宽条件，也许会遇见意想不到的缘分。",
        )
    }
}

@Composable
private fun FilterSection(
    title: String,
    content: @Composable () -> Unit,
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
    ) {
        Text(
            text = title,
            style = ClTheme.typography.sectionTitle,
            color = ClTheme.colors.ink,
        )
        content()
    }
}

/** 「年龄范围 · 22–35 岁」（设计图 07 的文案）。 */
private fun ageLabel(state: FilterUiState): String =
    "年龄范围 · ${state.draft.ageMin}–${state.draft.ageMax} 岁"
