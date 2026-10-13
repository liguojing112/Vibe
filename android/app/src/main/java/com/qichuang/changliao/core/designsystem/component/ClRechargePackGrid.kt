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

/**
 * 一个金额档位。金额文案由调用方格式化。
 *
 * 这个组件最早是给 P22 充值档位写的，后来 P12 会员套餐、P28 提现档位都复用了它 ——
 * 三处的视觉完全一样（三列、选中金边、大数字 + 单位 + 一行说明），所以只有这一个组件。
 */
data class ClRechargePack(
    val id: Long,
    val coins: String,
    val priceText: String,
    /** 置灰不可选（积分/余额不够时，P28 的档位就是这么用的）。 */
    val enabled: Boolean = true,
)

/**
 * 金额档位网格（docs/03 第 5.4 节，设计图 22）：三列，选中档位金色描边。
 *
 * @param unit 大数字下面那行的单位：充值和会员是「金币」，提现是「元」。
 */
@Composable
fun ClRechargePackGrid(
    packs: List<ClRechargePack>,
    selectedId: Long?,
    onSelect: (ClRechargePack) -> Unit,
    modifier: Modifier = Modifier,
    unit: String = "金币",
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
                        selected = pack.enabled && pack.id == selectedId,
                        unit = unit,
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
    unit: String,
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
            .clickable(enabled = pack.enabled, onClick = onClick)
            .padding(vertical = ClTheme.spacing.l, horizontal = ClTheme.spacing.s),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
    ) {
        Text(
            text = pack.coins,
            style = ClTheme.typography.amountMedium,
            color = if (pack.enabled) ClTheme.colors.ink else ClTheme.colors.muted,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        Text(
            text = unit,
            style = ClTheme.typography.tiny,
            color = if (pack.enabled) ClTheme.colors.inkSecondary else ClTheme.colors.muted,
        )
        Text(
            text = pack.priceText,
            style = ClTheme.typography.cardTitle,
            color = if (pack.enabled) ClTheme.colors.incomeText else ClTheme.colors.muted,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(top = ClTheme.spacing.xs),
        )
    }
}
