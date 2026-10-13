package com.qichuang.changliao.feature.withdraw

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.WithdrawAccountType
import com.qichuang.changliao.core.data.model.WithdrawRecord
import com.qichuang.changliao.core.designsystem.component.ClBalanceCard
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClCardDivider
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClNoticeCard
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClRechargePack
import com.qichuang.changliao.core.designsystem.component.ClRechargePackGrid
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 收益提现（P28，设计图 28）。
 *
 * 结构：大卡（可提现金额 + 积分）→ 提现档位 → 收款信息 → 规则说明 → 提现记录 → 钉底按钮。
 *
 * 三处按文档而不是设计图办：
 * 1. 设计图只有一个「本次申请金额」，`docs/01` P28 要求**档位可选、余额不够的置灰**，所以做成档位网格；
 * 2. 收款人姓名**必须与实名一致**，服务端给、页面不可改；
 * 3. 未实名时按钮变成「先完成实名认证」，点了去 P29。
 */
@Composable
fun WithdrawScreen(
    state: WithdrawUiState,
    onBack: () -> Unit,
    onSelectTier: (Int) -> Unit,
    onAccountTypeChange: (WithdrawAccountType) -> Unit,
    onAccountNoChange: (String) -> Unit,
    onBankNameChange: (String) -> Unit,
    onSubmit: () -> Unit,
    onOpenRealName: () -> Unit,
    onLoadMore: () -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "收益提现", onBack = onBack) },
        fixedBottom = {
            ClFixedBottomArea {
                ClPrimaryButton(
                    text = state.buttonText,
                    // 未实名时按钮仍然可点 —— 点了是去实名，不是灰着不给反馈
                    enabled = state.realNameMissing || state.canSubmit,
                    loading = state.submitting,
                    modifier = Modifier.weight(1f),
                    onClick = { if (state.realNameMissing) onOpenRealName() else onSubmit() },
                )
            }
        },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            state.isEmpty -> ClErrorRetry(message = "数据加载失败，请重试", onRetry = onRetry)

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        bottom = ClTheme.spacing.xl,
                    ),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClBalanceCard(
                    label = "可提现金额",
                    amount = state.amountText,
                    unit = "",
                    desc = state.pointsText,
                    modifier = Modifier.padding(top = ClTheme.spacing.l),
                )

                ClSectionHeader(title = "选择提现档位")
                ClRechargePackGrid(
                    packs = state.info?.tiers.orEmpty().map {
                        ClRechargePack(
                            id = it.yuan.toLong(),
                            coins = it.yuan.toString(),
                            priceText = "到账 ${MoneyFormat.rmb(it.actualFen)}",
                            enabled = it.enabled,
                        )
                    },
                    selectedId = state.selectedYuan?.toLong(),
                    onSelect = { onSelectTier(it.id.toInt()) },
                    // 提现的档位单位是「元」，不是金币
                    unit = "元",
                )

                ClSectionHeader(title = "收款信息")
                ClChipGroup(
                    options = WithdrawAccountType.entries.map { it.label },
                    selected = setOf(state.accountType.label),
                    onSelectionChange = { labels ->
                        labels.firstOrNull()
                            ?.let { label -> WithdrawAccountType.entries.firstOrNull { it.label == label } }
                            ?.let(onAccountTypeChange)
                    },
                )
                ClTextField(
                    label = "收款账号",
                    value = state.accountNo,
                    onValueChange = onAccountNoChange,
                    placeholder = when (state.accountType) {
                        WithdrawAccountType.ALIPAY -> "支付宝账号"
                        WithdrawAccountType.WECHAT -> "微信号"
                        WithdrawAccountType.BANK -> "银行卡号"
                    },
                    maxLength = 64,
                )
                if (state.needBankName) {
                    ClTextField(
                        label = "开户行",
                        value = state.bankName,
                        onValueChange = onBankNameChange,
                        placeholder = "例如：招商银行",
                        maxLength = 64,
                    )
                }
                ClCard {
                    ClSettingRow(
                        label = "收款人姓名",
                        desc = "必须与实名一致，不可修改",
                        trailing = { ValueText(state.accountNameText.ifEmpty { "—" }) },
                    )
                }

                Text(
                    text = state.ruleText,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                )

                ClSectionHeader(title = "提现记录")

                when {
                    state.recordsEmpty -> ClEmpty(
                        title = "还没有提现记录",
                        desc = "提交提现申请之后这里会显示进度",
                    )

                    else -> ClCard(contentPadding = PaddingValues()) {
                        state.records.forEachIndexed { index, record ->
                            RecordRow(record = record, showDivider = index != state.records.lastIndex)
                        }
                    }
                }

                if (state.hasMoreRecords) {
                    ClTextButton(
                        text = if (state.recordsLoading) "加载中…" else "加载更多",
                        modifier = Modifier.fillMaxWidth(),
                        onClick = onLoadMore,
                    )
                }

                if (state.realNameMissing) {
                    ClNoticeCard(
                        title = "提现需要先实名认证",
                        content = "完成实名认证后才能申请提现，收款人必须是实名本人。",
                        highlight = true,
                    )
                }
            }
        }
    }
}

/** 一条提现记录：状态 + （驳回原因或时间）+ 金额。 */
@Composable
private fun RecordRow(record: WithdrawRecord, showDivider: Boolean) {
    ClSettingRow(
        label = record.statusText,
        desc = record.rejectReason ?: TimeFormat.friendly(record.createdAt / 1000),
        trailing = {
            Text(
                text = "-${MoneyFormat.rmb(record.amountFen)}",
                style = ClTheme.typography.body,
                color = if (record.paid) ClTheme.colors.muted else ClTheme.colors.ink,
            )
        },
    )
    if (showDivider) {
        ClCardDivider()
    }
}

@Composable
private fun ValueText(value: String) {
    Text(
        text = value,
        style = ClTheme.typography.body,
        color = ClTheme.colors.ink,
    )
}
