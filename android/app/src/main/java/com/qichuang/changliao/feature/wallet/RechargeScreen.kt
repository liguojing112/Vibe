package com.qichuang.changliao.feature.wallet

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import com.qichuang.changliao.core.data.model.RechargeOrder
import com.qichuang.changliao.core.designsystem.component.ClBalanceCard
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClQrCode
import com.qichuang.changliao.core.designsystem.component.ClQrCodeContent
import com.qichuang.changliao.core.designsystem.component.ClRechargePack
import com.qichuang.changliao.core.designsystem.component.ClRechargePackGrid
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 支付宝包名：唤醒时优先指定，避免被浏览器接走。 */
private const val ALIPAY_PACKAGE = "com.eg.android.AlipayGphone"

/**
 * 金币充值（P22，设计图 22）。
 *
 * 客服充值仍然保留；后台把商户号配好并打开在线支付后，选中档位会出现「立即支付」。
 * 下单成功后在弹窗里**本地生成二维码**，下面再给一个「打开支付宝支付」按钮 ——
 * 不跳转码支付网站（经理要求）。
 *
 * 铁律 13：合规声明**固定显示**在这一页上（文案来自服务端，后台可改但不能改空）。
 */
@Composable
fun RechargeScreen(
    state: RechargeUiState,
    onBack: () -> Unit,
    onSelectPack: (com.qichuang.changliao.core.data.model.RechargePack) -> Unit,
    onPay: () -> Unit,
    onDismissPay: () -> Unit,
    onCheckPaid: () -> Unit,
    onWechatCopied: () -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val clipboard = LocalClipboardManager.current

    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "充值金币", onBack = onBack) },
    ) {
        // Toast 的宿主是 ClScaffold 提供的，必须在它**里面**取（外面拿到的是兜底实例）
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        val info = state.info
        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            info == null -> ClErrorRetry(message = "数据加载失败，请重试", onRetry = onRetry)

            else -> {
                Column(
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

                    // 在线支付：后台配好商户号并打开开关后才出现（否则只保留下面的客服充值）
                    if (state.canPay) {
                        ClPrimaryButton(
                            text = "立即支付 ${state.payAmountText}",
                            loading = state.paying,
                            large = true,
                            onClick = onPay,
                        )
                    }

                    if (info.service.hasAny) {
                        // 客服充值作为兜底：支付不可用或用户习惯找客服时还能充
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

                state.payOrder?.let { order ->
                    RechargePaySheet(
                        order = order,
                        onDismiss = onDismissPay,
                        onCheckPaid = onCheckPaid,
                    )
                }
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

/**
 * 在线支付弹窗：二维码在上，唤醒按钮在下（经理要求的形态）。
 *
 * 二维码内容就是服务端从码支付取到的支付宝付款链接，**在本地画**；
 * 唤醒按钮把同一条链接交给支付宝 App，不经过码支付网页。
 */
@Composable
private fun RechargePaySheet(
    order: RechargeOrder,
    onDismiss: () -> Unit,
    onCheckPaid: () -> Unit,
) {
    val context = LocalContext.current

    ClBottomSheet(
        title = "扫码支付",
        onClose = onDismiss,
        shape = ClTheme.shapes.card,
        titleStyle = ClTheme.typography.pageTitle,
        compactBottom = true,
        modifier = Modifier.padding(
            start = ClTheme.spacing.l,
            end = ClTheme.spacing.l,
            bottom = ClTheme.spacing.xxxl,
        ),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.l),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            Text(
                text = "应付 ¥${order.amountText}",
                style = ClTheme.typography.amountLarge,
                color = ClTheme.colors.coinText,
            )
            Text(
                text = "支付成功后自动到账 ${order.coins} 金币",
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
            )

            ClQrCodeContent(content = order.payUrl)

            Text(
                text = "用另一台手机扫码支付；本机可点下方按钮直接打开支付宝",
                style = ClTheme.typography.tiny,
                color = ClTheme.colors.muted,
            )

            ClPrimaryButton(
                text = "打开支付宝支付",
                large = true,
                onClick = {
                    if (!openAlipay(context, order.payUrl)) {
                        Toast.makeText(context, "未安装支付宝，请用另一台手机扫码支付", Toast.LENGTH_LONG).show()
                    }
                },
            )
            ClSecondaryButton(
                text = "我已支付",
                onClick = onCheckPaid,
            )
        }
    }
}

/**
 * 把支付宝付款链接交给支付宝 App。
 *
 * 先试 `alipays://` 扫码 scheme（最稳），再试原始链接；两个都指定支付宝包名，
 * 避免被浏览器接走。没装支付宝时返回 false，由调用方提示扫码。
 */
private fun openAlipay(context: Context, payUrl: String): Boolean {
    if (payUrl.isBlank()) {
        return false
    }

    val targets = listOf(
        "alipays://platformapi/startapp?saId=10000007&qrcode=" + Uri.encode(payUrl),
        payUrl,
    )

    for (url in targets) {
        try {
            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url)).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                setPackage(ALIPAY_PACKAGE)
            }
            context.startActivity(intent)
            return true
        }
        catch (_: Exception) {
            // 换下一个入口
        }
    }

    return false
}
