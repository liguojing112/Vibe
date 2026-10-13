package com.qichuang.changliao.feature.income

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
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.IncomeLogEntry
import com.qichuang.changliao.core.designsystem.component.ClBalanceCard
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClCardDivider
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClRecordRow
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClStatCell
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 我的收益（P26，设计图 26）。
 *
 * 结构：大卡（可提现积分 + 约合人民币 + 冻结中）→ 今日三项 → 申请提现 / 我的等级两个按钮
 * → 收益明细（分页）→ 底部说明。
 *
 * 三处按文档而不是设计图办：
 * 1. 设计图的三个统计格是「今日收益豆 / 今日接通次数 / 今日通话分钟」，本期没有通话收益，
 *    按 `docs/01` P26 改成**今日收益积分 / 今日收到消息数 / 今日收到礼物数**；
 * 2. 设计图的「接听与定价」按 `docs/01` P26 叫「我的等级」（P27）；
 * 3. 明细列表是**积分流水**（收益、任务奖励、提现都在里面），不是设计图那个「收益构成」。
 */
@Composable
fun IncomeScreen(
    state: IncomeUiState,
    onBack: () -> Unit,
    onOpenWithdraw: () -> Unit,
    onOpenLevel: () -> Unit,
    onLoadMore: () -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "我的收益", onBack = onBack) },
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
                    .padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClBalanceCard(
                    label = "可提现积分",
                    amount = state.balanceText,
                    unit = MoneyFormat.UNIT_POINT,
                    desc = state.balanceDesc,
                    modifier = Modifier.padding(top = ClTheme.spacing.l),
                )

                Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
                    ClStatCell(
                        number = state.todayPointsText,
                        label = "今日收益积分",
                        modifier = Modifier.weight(1f),
                    )
                    ClStatCell(
                        number = state.todayMessagesText,
                        label = "今日收到消息",
                        modifier = Modifier.weight(1f),
                    )
                    ClStatCell(
                        number = state.todayGiftsText,
                        label = "今日收到礼物",
                        modifier = Modifier.weight(1f),
                    )
                }

                Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m)) {
                    ClPrimaryButton(
                        text = "申请提现",
                        modifier = Modifier.weight(1f),
                        onClick = onOpenWithdraw,
                    )
                    ClSecondaryButton(
                        text = "我的等级",
                        modifier = Modifier.weight(1f),
                        onClick = onOpenLevel,
                    )
                }

                ClSectionHeader(title = "收益明细")

                when {
                    state.logsEmpty -> ClEmpty(
                        title = "还没有收益记录",
                        desc = "男用户付费私信和礼物之后这里会有明细",
                    )

                    else -> ClCard(contentPadding = PaddingValues()) {
                        state.logs.forEachIndexed { index, entry ->
                            LogRow(entry = entry, showDivider = index != state.logs.lastIndex)
                        }
                    }
                }

                if (state.hasMoreLogs) {
                    ClTextButton(
                        text = if (state.logsLoading) "加载中…" else "加载更多",
                        modifier = Modifier.fillMaxWidth(),
                        onClick = onLoadMore,
                    )
                }

                Text(
                    text = state.notice,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                    modifier = Modifier.padding(bottom = ClTheme.spacing.xl),
                )
            }
        }
    }
}

/** 一条收益明细：图标 + 标题 + 时间 + 金额。 */
@Composable
private fun LogRow(entry: IncomeLogEntry, showDivider: Boolean) {
    ClRecordRow(
        icon = iconOf(entry),
        title = entry.title,
        time = TimeFormat.friendly(entry.createdAt / 1000),
        // 提现冻结是「出账」（可用 −N），收益是进账；冻结变化在提现记录页看
        amount = MoneyFormat.signed(entry.change),
        positive = entry.change >= 0,
        showDivider = showDivider,
    )
}

/** 明细类型 → 图标（文案用服务端给的 title，客户端只挑图标）。 */
private fun iconOf(entry: IncomeLogEntry) = when {
    entry.isWithdraw -> ClIcons.wallet
    entry.type == "GIFT_INCOME" -> ClIcons.gift
    entry.type == "TASK" -> ClIcons.star
    entry.type == "MESSAGE_INCOME" -> ClIcons.message
    else -> ClIcons.spark
}
