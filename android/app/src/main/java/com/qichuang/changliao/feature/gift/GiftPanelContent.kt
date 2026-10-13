package com.qichuang.changliao.feature.gift

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.Gift
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClGiftGrid
import com.qichuang.changliao.core.designsystem.component.ClGiftItem
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 礼物面板的内容，**P23（整页）和 P31（聊天页底部弹窗）共用**（docs/01 P23 的要求）。
 *
 * 拆成上下两块：[GiftGridSection] 是可滚动的那部分，[GiftSendButton] 是钉在底部的那颗按钮 ——
 * P23 用 `ClScaffold(fixedBottom = …)` 把按钮钉住，P31 直接按顺序放进弹窗里。
 */
@Composable
fun GiftGridSection(
    state: GiftPanelUiState,
    onSelect: (Gift) -> Unit,
    modifier: Modifier = Modifier,
    onRetry: () -> Unit = {},
) {
    when {
        state.loading -> ClLoading(modifier = modifier)

        state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry, modifier = modifier)

        state.isEmpty -> ClEmpty(
            title = "暂时没有可送的礼物",
            desc = "后台配好礼物之后这里就会出现",
            modifier = modifier,
        )

        else -> Column(
            modifier = modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
        ) {
            ClGiftGrid(
                gifts = state.gifts.map { it.toItem() },
                selectedId = state.selectedId,
                onSelect = { item -> state.gifts.firstOrNull { it.id == item.id }?.let(onSelect) },
            )

            Text(
                text = state.balanceText,
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
            )
        }
    }
}

/** 底部那颗「赠送 XX · N 金币」按钮（余额不足由宿主页面弹 P30）。 */
@Composable
fun GiftSendButton(
    state: GiftPanelUiState,
    onSend: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClPrimaryButton(
        text = state.buttonText,
        enabled = state.canSend,
        loading = state.sending,
        modifier = modifier,
        onClick = onSend,
    )
}

/** 礼物 → 网格项。价格文案统一走 `MoneyFormat`，页面里不手拼单位（`check-ui.sh` 会拦）。 */
private fun Gift.toItem() = ClGiftItem(
    id = id,
    name = name,
    priceText = MoneyFormat.coins(priceCoins.toLong()),
    // 后台没配图标时给个默认的，别让格子空着
    icon = icon.ifBlank { "🎁" },
)
