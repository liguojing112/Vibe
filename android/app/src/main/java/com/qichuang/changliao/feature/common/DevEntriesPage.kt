package com.qichuang.changliao.feature.common

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClMenuGroup
import com.qichuang.changliao.core.designsystem.component.ClMenuRow
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 开发入口的一项。 */
data class DevEntry(
    val label: String,
    val icon: ImageVector,
    val onClick: () -> Unit,
)

/**
 * **仅 debug 包用的临时入口页**（docs/08 里 P14、P08、P09 这几个页面已经做完，
 * 但它们的入口页 P04「我的」、P03「消息」还在后面的阶段），先在这里放几个跳转好走查。
 *
 * P04/P03 做完后**把这个文件和它的用法一起删掉**。
 */
@Composable
fun DevEntriesPage(
    title: String,
    entries: List<DevEntry>,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = title) },
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(ClTheme.spacing.l),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
        ) {
            Text(
                text = "开发入口（仅 debug 包）",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.muted,
            )

            ClCard {
                ClMenuGroup {
                    entries.forEachIndexed { index, entry ->
                        ClMenuRow(
                            icon = entry.icon,
                            label = entry.label,
                            showDivider = index != entries.lastIndex,
                            onClick = entry.onClick,
                        )
                    }
                }
            }
        }
    }
}
