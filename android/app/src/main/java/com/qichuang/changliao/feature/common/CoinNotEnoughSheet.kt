package com.qichuang.changliao.feature.common

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.CoinNotEnough
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * P30 余额不足弹窗（docs/01 P30）。
 *
 * 发私信（P06）和送礼物（P23/P31）都会碰到，所以放在 `feature/common` 共用一份 ——
 * 它是业务弹窗（内容固定是「当前余额 / 需要多少 / 还差多少」），
 * 用的仍然是设计系统里的 `ClBottomSheet` 和按钮组件。
 */
@Composable
fun CoinNotEnoughSheet(
    info: CoinNotEnough,
    onDismiss: () -> Unit,
    onRecharge: () -> Unit,
) {
    ClBottomSheet(title = "余额不足", onClose = onDismiss) {
        Text(
            text = MoneyFormat.coins(info.balance),
            style = ClTheme.typography.amountLarge,
            color = ClTheme.colors.coinText,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth(),
        )
        Text(
            text = "本次操作需要 ${info.needed} 金币，还差 ${info.short} 金币",
            style = ClTheme.typography.bodySmall,
            color = ClTheme.colors.inkSecondary,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = ClTheme.spacing.m),
        )
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = ClTheme.spacing.l),
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            ClSecondaryButton(text = "暂不充值", modifier = Modifier.weight(1f), onClick = onDismiss)
            ClPrimaryButton(text = "去充值", modifier = Modifier.weight(1f), onClick = onRecharge)
        }
    }
}
