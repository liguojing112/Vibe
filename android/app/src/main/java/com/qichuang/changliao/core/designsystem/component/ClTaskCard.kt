package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 一个每日任务（docs/01 P11）。 */
data class ClTaskItem(
    val id: Long,
    val title: String,
    val desc: String,
    val actionText: String,
    val icon: String = "✅",
    /** 奖励文案（「+5 金币」/「+20 积分」）；空串就不显示这一行。 */
    val reward: String = "",
    val done: Boolean = false,
)

/**
 * 每日任务卡（docs/03 第 5.4 节，设计图 11）：图标方块 + 标题 + 说明 + 奖励 + 右侧奶黄按钮。
 *
 * [ClTaskItem.done] = true 时按钮变灰不可点（已领过奖励）。
 *
 * [ClTaskItem.reward] 那一行是 `docs/01` P11 明确要求的（「男显示『+X 金币』，女显示『+X 积分』」）——
 * 设计图 11 上没画，但文档是客户确认过的需求，按文档办。
 */
@Composable
fun ClTaskCard(
    task: ClTaskItem,
    onAction: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .clip(ClTheme.shapes.card)
            .background(ClTheme.colors.surface)
            .padding(ClTheme.spacing.l),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(ClTheme.size.buttonHeight)
                .clip(ClTheme.shapes.cardSmall)
                .background(ClTheme.colors.bgCream),
            contentAlignment = Alignment.Center,
        ) {
            Text(text = task.icon, style = ClTheme.typography.amountMedium)
        }

        Column(
            modifier = Modifier
                .weight(1f)
                .padding(start = ClTheme.spacing.m),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
        ) {
            Text(
                text = task.title,
                style = ClTheme.typography.cardTitle,
                color = ClTheme.colors.ink,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = task.desc,
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            if (task.reward.isNotBlank()) {
                Text(
                    text = task.reward,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.incomeText,
                    maxLines = 1,
                )
            }
        }

        Box(
            modifier = Modifier
                .clip(ClTheme.shapes.pill)
                .background(
                    if (task.done) ClTheme.colors.bgInput else ClTheme.colors.primarySoft,
                )
                .clickable(enabled = !task.done, onClick = onAction)
                .padding(horizontal = ClTheme.spacing.m, vertical = ClTheme.spacing.s),
        ) {
            Text(
                text = task.actionText,
                style = ClTheme.typography.buttonSmall,
                color = if (task.done) ClTheme.colors.muted else ClTheme.colors.primaryText,
                maxLines = 1,
            )
        }
    }
}
