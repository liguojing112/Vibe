package com.qichuang.changliao.core.designsystem.component

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
import com.qichuang.changliao.core.designsystem.theme.ClGradients
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.designsystem.theme.clGradientBackground

/**
 * 金币余额卡 / 收益卡（docs/03 第 5.3 节，对应原型 `.balance-hero`）。
 */
@Composable
fun ClBalanceCard(
    label: String,
    amount: String,
    modifier: Modifier = Modifier,
    unit: String = "金币",
    desc: String? = null,
    trailing: (@Composable () -> Unit)? = null,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(ClTheme.shapes.card)
            .clGradientBackground(ClGradients.balance)
            .padding(horizontal = ClTheme.spacing.xl, vertical = ClTheme.spacing.xxl),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                text = label,
                style = ClTheme.typography.caption,
                color = ClTheme.colors.primaryText,
                modifier = Modifier.weight(1f),
            )
            trailing?.invoke()
        }

        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                text = amount,
                style = ClTheme.typography.amountLarge,
                color = ClTheme.colors.incomeText,
            )
            Text(
                text = " $unit",
                style = ClTheme.typography.body,
                color = ClTheme.colors.primaryText,
            )
        }

        if (desc != null) {
            Text(
                text = desc,
                style = ClTheme.typography.caption,
                color = ClTheme.colors.primaryText,
            )
        }
    }
}
