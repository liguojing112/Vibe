package com.qichuang.changliao.feature.wallet

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import com.qichuang.changliao.core.designsystem.component.ClBalanceCard
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClQrCode
import com.qichuang.changliao.core.designsystem.component.ClRechargePack
import com.qichuang.changliao.core.designsystem.component.ClRechargePackGrid
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 金币充值（P22，设计图 22）。
 *
 * **这里没有任何支付动作**（docs/06 第 8 节：充值由后台客服人工完成，铁律：无在线支付）：
 * 档位网格只做展示和选中，用户选好之后**联系客服**充值。
 * 设计图 22 上那个「模拟支付」按钮是原型的演示按钮，不是要做的东西。
 *
 * 铁律 13：合规声明**固定显示**在这一页上（文案来自服务端，后台可改但不能改空）。
 */
@Composable
fun RechargeScreen(
    state: RechargeUiState,
    onBack: () -> Unit,
    onSelectPack: (com.qichuang.changliao.core.data.model.RechargePack) -> Unit,
    onWechatCopied: () -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val clipboard = LocalClipboardManager.current
    val toast = rememberClToast()

    LaunchedEffect(state.toast) {
        state.toast?.let {
            toast.show(it)
            onToastShown()
        }
    }

    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "充值金币", onBack = onBack) },
    ) {
        val info = state.info
        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            info == null -> ClErrorRetry(message = "数据加载失败，请重试", onRetry = onRetry)

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
            ) {
                ClBalanceCard(
                    label = "当前余额",
                    amount = state.balance.toString(),
                    desc = "充值后可用于私信与礼物",
                )

                ClSectionHeader(title = "选择充值金额")

                if (info.packs.isEmpty()) {
                    // 后台没配档位时给一句话，别摆一个空网格
                    Text(
                        text = "暂未配置充值档位，请联系客服。",
                        style = ClTheme.typography.bodySmall,
                        color = ClTheme.colors.muted,
                    )
                } else {
                    ClRechargePackGrid(
                        packs = info.packs.map {
                            ClRechargePack(
                                id = it.id,
                                coins = it.coins.toString(),
                                priceText = "¥${it.yuan}",
                            )
                        },
                        selectedId = state.selectedPack?.id,
                        onSelect = { cell ->
                            info.packs.firstOrNull { it.id == cell.id }?.let(onSelectPack)
                        },
                    )
                }

                // 「本次到账」紧跟档位网格：它说的是**选中的那一个**档位
                ClCard(contentPadding = PaddingValues()) {
                    ClSettingRow(
                        label = "本次到账",
                        trailing = {
                            Text(
                                text = state.selectedCoinsText,
                                style = ClTheme.typography.body,
                                color = ClTheme.colors.ink,
                            )
                        },
                    )
                }

                if (info.service.hasAny) {
                    // 「充值方式」就是客服联系方式（docs/06 第 8 节：没有在线支付，充值靠客服人工做）
                    ClSectionHeader(title = "充值方式")
                    ServiceCard(
                        state = state,
                        onCopyWechat = {
                            clipboard.setText(AnnotatedString(info.service.wechat))
                            onWechatCopied()
                        },
                    )
                }

                Text(
                    text = info.notice,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                )

                ComplianceCard(text = info.complianceDisclosure)

                Text(
                    text = "",
                    style = ClTheme.typography.tiny,
                    modifier = Modifier.padding(bottom = ClTheme.spacing.xl),
                )
            }
        }
    }
}

/** 客服区：微信 / 电话 / 二维码 + 复制按钮。 */
@Composable
private fun ServiceCard(
    state: RechargeUiState,
    onCopyWechat: () -> Unit,
) {
    val service = state.info?.service ?: return

    ClCard {
        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            Text(
                text = "联系客服充值，客服确认后金币到账",
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
            )

            if (service.wechat.isNotBlank()) {
                ClSettingRow(
                    label = "客服微信",
                    trailing = {
                        Text(
                            text = service.wechat,
                            style = ClTheme.typography.body,
                            color = ClTheme.colors.ink,
                        )
                    },
                )
            }
            if (service.phone.isNotBlank()) {
                ClSettingRow(
                    label = "客服电话",
                    trailing = {
                        Text(
                            text = service.phone,
                            style = ClTheme.typography.body,
                            color = ClTheme.colors.ink,
                        )
                    },
                )
            }
            if (service.qrcodeUrl.isNotBlank()) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    ClQrCode(url = service.qrcodeUrl)
                }
            }

            if (state.canCopyWechat) {
                ClPrimaryButton(text = "复制客服微信", onClick = onCopyWechat)
            }
        }
    }
}

/** 合规声明（铁律 13）：固定显示，文案来自服务端。 */
@Composable
private fun ComplianceCard(text: String) {
    ClCard {
        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
        ) {
            Text(
                text = "合规声明",
                style = ClTheme.typography.tiny,
                color = ClTheme.colors.muted,
            )
            Text(
                text = text,
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
            )
        }
    }
}
