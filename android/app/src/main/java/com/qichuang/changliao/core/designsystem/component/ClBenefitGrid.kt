package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.designsystem.theme.ClTheme

private const val BENEFIT_COLUMNS = 2

/** 一条会员权益（docs/01 P12）。 */
data class ClBenefitItem(
    val icon: String,
    val title: String,
    val desc: String,
)

/**
 * 会员权益网格（docs/03 第 5.4 节，设计图 12）：两列奶白卡片，表情图标 + 标题 + 说明。
 */
@Composable
fun ClBenefitGrid(
    items: List<ClBenefitItem>,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        items.chunked(BENEFIT_COLUMNS).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
                row.forEach { item ->
                    ClBenefitCell(item = item, modifier = Modifier.weight(1f))
                }
                repeat(BENEFIT_COLUMNS - row.size) {
                    Box(modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun ClBenefitCell(
    item: ClBenefitItem,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .clip(ClTheme.shapes.card)
            .background(ClTheme.colors.bgCream)
            .padding(ClTheme.spacing.l),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
    ) {
        Text(text = item.icon, style = ClTheme.typography.amountMedium)
        Text(
            text = item.title,
            style = ClTheme.typography.cardTitle,
            color = ClTheme.colors.ink,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        Text(
            text = item.desc,
            style = ClTheme.typography.tiny,
            color = ClTheme.colors.inkSecondary,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
    }
}
