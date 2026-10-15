package com.qichuang.changliao.feature.call

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * P25 通话记录（docs/01 P25，设计图 25）。
 *
 * **永远是空态**：通话本身没做，所以不可能有记录。设计图里那一条「小满 · 语音通话 · 120 金币」
 * 是原型示意，不能拿它当假数据摆在正式页面上 —— 摆上去就等于告诉用户「你打过这通电话」。
 */
@Composable
fun CallRecordsScreen(
    onBack: () -> Unit,
    onOpenDiscover: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(modifier = modifier, containerColor = ClTheme.colors.bgCream) {
        Column(modifier = Modifier.fillMaxSize()) {
            ClTopBar(title = "通话记录", onBack = onBack)

            ClEmpty(
                title = "还没有通话记录",
                desc = "语音和视频通话的功能还没开放",
                actionText = "去认识新朋友",
                onAction = onOpenDiscover,
            )
        }
    }
}
