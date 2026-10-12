package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

private const val PACK_COLUMNS = 3

/** 一个充值档位（docs/01 P22）。金额文案由调用方格式化。 */
data class ClRechargePack(
    val id: Long,
    val coins: String,
    val priceText: String,
)

/**
 * 充值档位网格（docs/03 第 5.4 节，设计图 22）：三列，选中档位金色描边。
 */
@Composable
fun ClRechargePackGrid(
    packs: List<ClRechargePack>,
    selectedId: Long?,
    onSelect: (ClRechargePack) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        packs.chunked(PACK_COLUMNS).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
                row.forEach { pack ->
                    ClRechargePackCell(
                        pack = pack,
                        selected = pack.id == selectedId,
                        modifier = Modifier.weight(1f),
                        onClick = { onSelect(pack) },
                    )
                }
                repeat(PACK_COLUMNS - row.size) {
                    Box(modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun ClRechargePackCell(
    pack: ClRechargePack,
    selected: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Column(
        modifier = modifier
            .clip(ClTheme.shapes.card)
            .background(if (selected) ClTheme.colors.primaryChip else ClTheme.colors.surface)
            .border(
                width = if (selected) 2.dp else 1.dp,
                color = if (selected) ClTheme.colors.primaryChipBorder else ClTheme.colors.line,
                shape = ClTheme.shapes.card,
            )
            .clickable(onClick = onClick)
            .padding(vertical = ClTheme.spacing.l, horizontal = ClTheme.spacing.s),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
    ) {
        Text(
            text = pack.coins,
            style = ClTheme.typography.amountMedium,
            color = ClTheme.colors.ink,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        Text(
            text = "金币",
            style = ClTheme.typography.tiny,
            color = ClTheme.colors.inkSecondary,
        )
        Text(
            text = pack.priceText,
            style = ClTheme.typography.cardTitle,
            color = ClTheme.colors.incomeText,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(top = ClTheme.spacing.xs),
        )
    }
}
