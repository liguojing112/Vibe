package com.qichuang.changliao.feature.common

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.CoinNotEnough
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClOutlineSmallButton
import com.qichuang.changliao.core.designsystem.component.ClSoftButton
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
    // 余额已经是 0 的时候说「用完了」更直白，还有一点余额时才是「不足」
    ClBottomSheet(
        title = if (info.balance <= 0) "金币用完了" else "余额不足",
        onClose = onDismiss,
        // 往上抬两个气泡的高度：贴底时会正好压住最后一条消息，用户看不到刚才聊到哪
        modifier = Modifier.padding(bottom = ClTheme.spacing.xxxl * 4),
    ) {
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
                .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
        )
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = ClTheme.spacing.l, end = ClTheme.spacing.l, bottom = ClTheme.spacing.l),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            ClOutlineSmallButton(text = "暂不充值", onClick = onDismiss)
            androidx.compose.foundation.layout.Spacer(
                modifier = Modifier.padding(horizontal = ClTheme.spacing.s),
            )
            ClSoftButton(text = "去充值", onClick = onRecharge)
        }
    }
}
