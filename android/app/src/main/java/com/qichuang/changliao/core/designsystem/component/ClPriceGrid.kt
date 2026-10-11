package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextAlign
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 价格面板的一格。 */
data class ClPriceItem(
    val label: String,
    val price: String,
)

/**
 * 三格价格面板（docs/03 第 5.3 节）：私信 / 语音 / 视频。
 */
@Composable
fun ClPriceGrid(
    items: List<ClPriceItem>,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .clip(ClTheme.shapes.cardSmall)
            .background(ClTheme.colors.bgCream)
            .padding(vertical = ClTheme.spacing.m),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        items.forEach { item ->
            Column(
                modifier = Modifier.weight(1f),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
            ) {
                Text(
                    text = item.price,
                    style = ClTheme.typography.cardTitle,
                    color = ClTheme.colors.primaryText,
                    textAlign = TextAlign.Center,
                    maxLines = 1,
                )
                Text(
                    text = item.label,
                    style = ClTheme.typography.micro,
                    color = ClTheme.colors.muted,
                    textAlign = TextAlign.Center,
                    maxLines = 1,
                )
            }
        }
    }
}
