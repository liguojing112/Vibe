package com.qichuang.changliao.feature.vip

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.VipPlan
import com.qichuang.changliao.core.designsystem.component.ClBenefitGrid
import com.qichuang.changliao.core.designsystem.component.ClBenefitItem
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClGradientHero
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClNoticeCard
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClRechargePack
import com.qichuang.changliao.core.designsystem.component.ClRechargePackGrid
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClGradients
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.feature.common.CoinNotEnoughSheet

/**
 * 会员中心（P12，设计图 12）。
 *
 * 结构：深色 VIP 横幅（右下角是选中套餐的人民币价）→ 会员专属权益 → 选择套餐 → 会员与聊天费用 → 小字。
 *
 * 三处按文档而不是设计图办：
 * 1. 权益四项用 `docs/01` P12 列的（查看访客 / 查看喜欢我的人 / 会员标识 / 每日额外免费私信 N 条），
 *    设计图上那四项是通用模板；
 * 2. 价格卡（选择套餐）是 `docs/01` P12 明确要求的，设计图上没画；
 * 3. 横幅右下角显示的是**选中套餐**的价，切到季卡/年卡会跟着变。
 *
 * 只给男用户 —— `docs/01` P12 标注「男」，女用户的「我」页面里根本没有这个入口。
 */
@Composable
fun VipCenterScreen(
    state: VipCenterUiState,
    onBack: () -> Unit,
    onSelect: (VipPlan) -> Unit,
    onBuy: () -> Unit,
    onDismissCoinNotEnough: () -> Unit,
    onOpenRecharge: () -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "会员中心", onBack = onBack) },
        fixedBottom = {
            ClFixedBottomArea {
                ClPrimaryButton(
                    text = state.buttonText,
                    enabled = state.canBuy,
                    loading = state.buying,
                    modifier = Modifier.weight(1f),
                    onClick = onBuy,
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

            state.isEmpty -> ClEmpty(
                title = "暂时没有可开通的套餐",
                desc = "后台配好套餐之后这里就会出现",
            )

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState()),
            ) {
                ClGradientHero(
                    gradient = ClGradients.vipCard,
                    title = "畅撩交友 VIP",
                    // 已是会员时这里改显示到期时间（`docs/01` P12：续费到期时间顺延）
                    subtitle = state.heroSubtitle,
                    // 深色渐变上的字要用浅色，默认的 onPrimary 是深色，会糊在一起
                    contentColor = ClTheme.colors.surface,
                    modifier = Modifier.padding(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        top = ClTheme.spacing.l,
                    ),
                    trailing = {
                        Column(horizontalAlignment = Alignment.End) {
                            Text(
                                text = state.priceYuan,
                                style = ClTheme.typography.amountMedium,
                                color = ClTheme.colors.primary,
                            )
                            Text(
                                text = state.daysText,
                                style = ClTheme.typography.caption,
                                color = ClTheme.colors.surface,
                            )
                        }
                    },
                )

                ClSectionHeader(title = "会员专属权益")
                ClBenefitGrid(
                    items = state.benefits.map { ClBenefitItem(it.icon, it.title, it.desc) },
                    modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                )

                ClSectionHeader(title = "选择套餐")
                ClRechargePackGrid(
                    packs = state.plans.map {
                        ClRechargePack(
                            id = it.id,
                            // 网格格子是「大数字 + 金币 + 一行说明」的结构，所以这里给纯数字
                            coins = it.priceCoins.toString(),
                            priceText = "${it.name} · ${it.days} 天",
                        )
                    },
                    selectedId = state.selectedId,
                    onSelect = { item -> state.plans.firstOrNull { it.id == item.id }?.let(onSelect) },
                    modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                )

                ClNoticeCard(
                    title = "会员与聊天费用",
                    content = state.notice,
                    modifier = Modifier.padding(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        top = ClTheme.spacing.l,
                    ),
                )

                Text(
                    text = "也可联系客服开通",
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                    modifier = Modifier.padding(
                        start = ClTheme.spacing.l,
                        top = ClTheme.spacing.m,
                        bottom = ClTheme.spacing.xl,
                    ),
                )
            }
        }
    }

    state.coinNotEnough?.let { info ->
        CoinNotEnoughSheet(
            info = info,
            onDismiss = onDismissCoinNotEnough,
            // 去充值之前先关弹窗：不然从充值页返回时它又冒出来（T4.8 走查踩到的）
            onRecharge = {
                onDismissCoinNotEnough()
                onOpenRecharge()
            },
        )
    }
}
