package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.theme.ClTheme

private const val GIFT_COLUMNS = 3

/** 一个礼物（docs/01 P23）。[priceText] 由调用方用 MoneyFormat 生成，组件不做金额格式化。 */
data class ClGiftItem(
    val id: Long,
    val name: String,
    val priceText: String,
    val icon: String = "🎁",
)

/**
 * 礼物网格（docs/03 第 5.4 节，设计图 23）：三列、奶白底、选中项金色描边。P23 与 P31 共用。
 */
@Composable
fun ClGiftGrid(
    gifts: List<ClGiftItem>,
    selectedId: Long?,
    onSelect: (ClGiftItem) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        gifts.chunked(GIFT_COLUMNS).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
                row.forEach { gift ->
                    ClGiftCell(
                        gift = gift,
                        selected = gift.id == selectedId,
                        modifier = Modifier.weight(1f),
                        onClick = { onSelect(gift) },
                    )
                }
                repeat(GIFT_COLUMNS - row.size) {
                    Box(modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun ClGiftCell(
    gift: ClGiftItem,
    selected: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Column(
        modifier = modifier
            .aspectRatio(1f)
            .clip(ClTheme.shapes.card)
            .background(if (selected) ClTheme.colors.primaryChip else ClTheme.colors.bgCream)
            .border(
                width = 1.dp,
                color = if (selected) ClTheme.colors.primaryChipBorder else ClTheme.colors.line,
                shape = ClTheme.shapes.card,
            )
            .clickable(onClick = onClick)
            .padding(ClTheme.spacing.s),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        // 图标两种存法都认：http 开头当图片，否则当 emoji 文本（后台改一次就能切换）
        if (gift.icon.startsWith("http")) {
            AsyncImage(
                model = gift.icon,
                contentDescription = gift.name,
                modifier = Modifier.size(ClTheme.size.iconL),
                contentScale = ContentScale.Fit,
            )
        } else {
            Text(text = gift.icon, style = ClTheme.typography.amountMedium, textAlign = TextAlign.Center)
        }
        Text(
            text = gift.name,
            style = ClTheme.typography.cardTitle,
            color = ClTheme.colors.ink,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(top = ClTheme.spacing.xs),
        )
        Text(
            text = gift.priceText,
            style = ClTheme.typography.tiny,
            color = ClTheme.colors.inkSecondary,
            maxLines = 1,
        )
    }
}
