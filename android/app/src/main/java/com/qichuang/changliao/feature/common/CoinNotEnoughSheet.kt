package com.qichuang.changliao.feature.common

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
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
    // 余额已经是 0 的时候说「用完了」更直白，还有一点余额时才是「不足」
    ClBottomSheet(
        title = if (info.balance <= 0) "金币用完了" else "余额不足",
        onClose = onDismiss,
        // 四角圆角的悬浮卡片：左右留边、底部只抬 16dp
        shape = ClTheme.shapes.card,
        // 高度固定成屏幕的一半：内容就四块，按内容撑开会显得又扁又小
        heightFraction = 0.5f,
        modifier = Modifier.padding(
            start = ClTheme.spacing.l,
            end = ClTheme.spacing.l,
            bottom = ClTheme.spacing.l,
        ),
    ) {
        Column(
            modifier = Modifier
                .fillMaxHeight()
                .padding(horizontal = ClTheme.spacing.l),
            verticalArrangement = Arrangement.SpaceEvenly,
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            // 金币插图（先用 emoji 占位，换成图片时把这段换成 AsyncImage 即可）
            Text(text = "🪙", style = ClTheme.typography.amountLarge)

            // 金额放在浅黄色圆角块里，比裸露的大字更像「卡片」
            Box(
                modifier = Modifier
                    .clip(ClTheme.shapes.card)
                    .background(ClTheme.colors.primaryChip)
                    .padding(horizontal = ClTheme.spacing.xxl, vertical = ClTheme.spacing.m),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    text = MoneyFormat.coins(info.balance),
                    style = ClTheme.typography.amountLarge,
                    color = ClTheme.colors.coinText,
                    textAlign = TextAlign.Center,
                )
            }

            Text(
                text = "本次操作需要 ${info.needed} 金币，还差 ${info.short} 金币",
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth(),
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClSecondaryButton(
                    text = "暂不充值",
                    modifier = Modifier.weight(1f),
                    onClick = onDismiss,
                )
                ClPrimaryButton(
                    text = "去充值",
                    modifier = Modifier.weight(1f),
                    onClick = onRecharge,
                )
            }
        }
    }
}
