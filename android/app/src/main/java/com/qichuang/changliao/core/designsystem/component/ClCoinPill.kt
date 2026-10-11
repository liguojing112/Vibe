package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 金币 / 积分胶囊（docs/03 第 5.3 节，对应原型 `.coin-pill`）：
 * 发现页右上角显示「◈ 余额 币」/「◈ 积分」。
 */
@Composable
fun ClCoinPill(
    amount: String,
    modifier: Modifier = Modifier,
    unit: String = "币",
    onClick: (() -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .clip(ClTheme.shapes.pill)
            .background(ClTheme.colors.coinBg)
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
            .padding(horizontal = ClTheme.spacing.m, vertical = ClTheme.spacing.s),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            imageVector = ClIcons.spark,
            contentDescription = null,
            tint = ClTheme.colors.coinText,
            modifier = Modifier.padding(end = ClTheme.spacing.xxs).then(Modifier),
        )
        Text(
            text = amount,
            style = ClTheme.typography.caption,
            color = ClTheme.colors.coinText,
            maxLines = 1,
        )
        Text(
            text = " $unit",
            style = ClTheme.typography.micro,
            color = ClTheme.colors.coinText,
            maxLines = 1,
        )
    }
}
