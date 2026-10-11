package com.qichuang.changliao.feature.common

import androidx.compose.runtime.Composable
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar

/**
 * 尚未实现的页面占位（T2.7）：[title] 是页面名，对应 docs/01 的页面清单。
 *
 * 后续每个页面任务把对应的 `composable(...)` 换成真页面，这个占位不做别的用途。
 */
@Composable
fun PlaceholderPage(
    title: String,
    onBack: (() -> Unit)? = null,
    actionText: String? = null,
    onAction: (() -> Unit)? = null,
) {
    ClScaffold(
        topBar = { ClTopBar(title = title, onBack = onBack) },
    ) {
        ClEmpty(
            title = "开发中",
            desc = title,
            actionText = actionText,
            onAction = onAction,
        )
    }
}
