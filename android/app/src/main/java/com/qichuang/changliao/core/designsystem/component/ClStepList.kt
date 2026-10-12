package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
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

/** 步骤条里的一步（docs/01 P29）。 */
data class ClStep(
    val title: String,
    val desc: String,
)

/**
 * 步骤条（docs/03 第 5.4 节，设计图 29）：左边奶黄圆圈里的序号，右边标题 + 说明，步与步之间一条分割线。
 */
@Composable
fun ClStepList(
    steps: List<ClStep>,
    modifier: Modifier = Modifier,
) {
    Column(modifier = modifier.fillMaxWidth()) {
        steps.forEachIndexed { index, step ->
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = ClTheme.spacing.m),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    modifier = Modifier
                        .size(ClTheme.size.buttonHeight)
                        .clip(ClTheme.shapes.circle)
                        .background(ClTheme.colors.primarySoft),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        text = (index + 1).toString(),
                        style = ClTheme.typography.cardTitle,
                        color = ClTheme.colors.primaryText,
                    )
                }

                Column(
                    modifier = Modifier
                        .weight(1f)
                        .padding(start = ClTheme.spacing.m),
                    verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
                ) {
                    Text(
                        text = step.title,
                        style = ClTheme.typography.cardTitle,
                        color = ClTheme.colors.ink,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Text(
                        text = step.desc,
                        style = ClTheme.typography.bodySmall,
                        color = ClTheme.colors.inkSecondary,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }

            if (index != steps.lastIndex) {
                ClCardDivider(
                    modifier = Modifier.padding(
                        start = ClTheme.size.buttonHeight + ClTheme.spacing.m,
                    ),
                )
            }
        }
    }
}
