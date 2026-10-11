package com.qichuang.changliao.core.designsystem.gallery

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClBadgeDot
import com.qichuang.changliao.core.designsystem.component.ClBalanceCard
import com.qichuang.changliao.core.designsystem.component.ClBillGroup
import com.qichuang.changliao.core.designsystem.component.ClBillRow
import com.qichuang.changliao.core.designsystem.component.ClBottomBar
import com.qichuang.changliao.core.designsystem.component.ClBottomBarItem
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClCoinPill
import com.qichuang.changliao.core.designsystem.component.ClDemoLabel
import com.qichuang.changliao.core.designsystem.component.ClFeeStrip
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClGradientHero
import com.qichuang.changliao.core.designsystem.component.ClIconButton
import com.qichuang.changliao.core.designsystem.component.ClLevelBadge
import com.qichuang.changliao.core.designsystem.component.ClMenuGroup
import com.qichuang.changliao.core.designsystem.component.ClMenuRow
import com.qichuang.changliao.core.designsystem.component.ClNoticeCard
import com.qichuang.changliao.core.designsystem.component.ClOutlineSmallButton
import com.qichuang.changliao.core.designsystem.component.ClPageHeader
import com.qichuang.changliao.core.designsystem.component.ClPriceGrid
import com.qichuang.changliao.core.designsystem.component.ClPriceItem
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClProgressBar
import com.qichuang.changliao.core.designsystem.component.ClRecordRow
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClSoftButton
import com.qichuang.changliao.core.designsystem.component.ClSoftButtonKind
import com.qichuang.changliao.core.designsystem.component.ClStatCell
import com.qichuang.changliao.core.designsystem.component.ClStatRow
import com.qichuang.changliao.core.designsystem.component.ClSwitch
import com.qichuang.changliao.core.designsystem.component.ClTag
import com.qichuang.changliao.core.designsystem.component.ClTagKind
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.ClVipBadge
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClGradients
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 组件预览页（docs/03 第 7 节）。
 *
 * **每新增一个公共组件，必须同时加到这一页**（`scripts/check-ui.sh` 会逐个检查）。
 * 正式入口：设置页连续点击版本号 5 次（仅 debug，T6.6 接）；开发期 debug 包直接进。
 */
@Composable
fun DesignSystemGalleryScreen(onBack: (() -> Unit)? = null) {
    val toast = rememberClToast()
    var selectedTab by remember { mutableStateOf("home") }
    var switchOn by remember { mutableStateOf(true) }

    ClScaffold(
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "组件预览", onBack = onBack) },
        bottomBar = {
            ClBottomBar(
                items = listOf(
                    ClBottomBarItem("home", "遇见", ClIcons.home),
                    ClBottomBarItem("message", "消息", ClIcons.message),
                    ClBottomBarItem("mine", "我", ClIcons.user),
                ),
                selected = selectedTab,
                badges = mapOf("message" to 3),
                onSelect = { selectedTab = it },
            )
        },
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            ClPageHeader(
                title = "设计系统",
                subtitle = "所有页面只能用这里的组件和 token",
                trailing = {
                    ClIconButton(icon = ClIcons.search, contentDescription = "搜索", onClick = {})
                },
            )

            // ============ 5.1 结构类 ============
            ClSectionHeader(title = "结构类", actionText = "查看全部", onAction = {})
            Text(
                text = "ClScaffold / ClTopBar / ClPageHeader / ClBottomBar / ClSectionHeader / ClFixedBottomArea",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.muted,
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
            )
            ClFixedBottomArea {
                ClPrimaryButton(
                    text = "固定底部按钮区",
                    onClick = {},
                    fillMaxWidth = false,
                    modifier = Modifier.weight(1f),
                )
            }

            // ============ 5.2 按钮类 ============
            ClSectionHeader(title = "按钮类")
            Column(
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClPrimaryButton(text = "主按钮", onClick = {})
                ClPrimaryButton(text = "主按钮", subText = "5 币/条", onClick = {})
                ClPrimaryButton(text = "主按钮", enabled = false, onClick = {})
                ClPrimaryButton(text = "主按钮", loading = true, onClick = {})

                ClSecondaryButton(text = "次按钮", onClick = {})
                ClSecondaryButton(text = "次按钮", enabled = false, onClick = {})

                ClOutlineSmallButton(text = "打招呼", onClick = {})

                Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
                    ClSoftButton(text = "私信", kind = ClSoftButtonKind.Yellow, leadingIcon = ClIcons.message, onClick = {})
                    ClSoftButton(text = "语音", kind = ClSoftButtonKind.Peach, leadingIcon = ClIcons.mic, onClick = {})
                    ClSoftButton(text = "视频", kind = ClSoftButtonKind.Gray, leadingIcon = ClIcons.video, onClick = {})
                }

                ClTextButton(text = "文字链接按钮", onClick = {})

                Row(
                    horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    ClIconButton(icon = ClIcons.search, contentDescription = "搜索", onClick = {})
                    ClOutlineSmallButton(text = "回关", onClick = {})
                }
            }

            // ============ 5.3 展示类 ============
            ClSectionHeader(title = "展示类")
            Column(
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
            ) {
                Row(
                    horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    ClAvatar(url = null, size = ClTheme.size.avatarS, online = true)
                    ClAvatar(url = null, size = ClTheme.size.avatarS, vip = true, contentDescription = "头像")
                    ClLevelBadge(level = 3)
                    ClVipBadge(active = true)
                    ClVipBadge(active = false)
                }

                Row(
                    horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    ClTag(text = "在线", kind = ClTagKind.Green)
                    ClTag(text = "已认证", kind = ClTagKind.Gold)
                    ClTag(text = "新人", kind = ClTagKind.Pink)
                    ClCoinPill(amount = "360")
                    Box {
                        ClBadgeDot(count = 120, modifier = Modifier.align(Alignment.Center))
                    }
                    ClDemoLabel()
                }

                ClCard {
                    Text(text = "ClCard", style = ClTheme.typography.cardTitle, color = ClTheme.colors.ink)
                }

                ClNoticeCard(title = "有 12 人看过你的主页 ✨", content = "开通会员查看全部访客", highlight = true)
                ClNoticeCard(title = "普通提示卡")

                ClGradientHero(
                    gradient = ClGradients.roomHero,
                    title = "有空，一起聊会儿？",
                    subtitle = "60 币/分钟",
                )

                ClBalanceCard(
                    label = "可用金币",
                    amount = "1,280",
                    unit = "金币",
                    desc = "私信 5 币/条",
                )

                ClPriceGrid(
                    items = listOf(
                        ClPriceItem("私信", "5 币/条"),
                        ClPriceItem("语音", "60 币"),
                        ClPriceItem("视频", "120 币"),
                    ),
                )

                ClBillGroup {
                    ClBillRow(label = "通话方式", value = "语音")
                    ClBillRow(label = "单价", value = "60 币/分钟")
                    ClBillRow(label = "最多可聊", value = "6 分钟", showDivider = false)
                }

                ClCard {
                    ClRecordRow(
                        icon = ClIcons.message,
                        title = "私信 · 小满",
                        time = "今天 10:24",
                        amount = "-5",
                        positive = false,
                    )
                    ClRecordRow(
                        icon = ClIcons.gift,
                        title = "礼物 · 浪漫花束",
                        time = "昨天 21:03",
                        amount = "+30",
                        showDivider = false,
                    )
                }

                ClMenuGroup {
                    ClMenuRow(icon = ClIcons.wallet, label = "金币收支明细", onClick = {})
                    ClMenuRow(icon = ClIcons.shield, label = "隐私与设置", extra = "2 项", onClick = {})
                    ClMenuRow(icon = ClIcons.user, label = "基本资料", showChevron = false, showDivider = false, onClick = {})
                }

                ClCard {
                    ClSettingRow(
                        label = "消息提醒",
                        desc = "App 内声音 / 震动",
                        trailing = {
                            ClSwitch(checked = switchOn, onCheckedChange = { switchOn = it })
                        },
                    )
                    ClSettingRow(
                        label = "展示给附近的人",
                        trailing = {
                            ClSwitch(checked = false, onCheckedChange = {})
                        },
                    )
                }

                ClCard {
                    ClStatRow {
                        ClStatCell(number = "12", label = "我关注的", modifier = Modifier.weight(1f))
                        ClStatCell(number = "3", label = "好友", modifier = Modifier.weight(1f))
                        ClStatCell(number = "48", label = "关注我的", modifier = Modifier.weight(1f))
                        ClStatCell(number = "9", label = "访客", modifier = Modifier.weight(1f))
                    }
                }

                ClFeeStrip(text = "还可免费发送 2 条 · 之后 5 币/条 · 余额 360 币", actionText = "收费说明")

                ClCard {
                    Text(text = "等级进度", style = ClTheme.typography.body, color = ClTheme.colors.ink)
                    Box(modifier = Modifier.height(ClTheme.spacing.s))
                    ClProgressBar(progress = 0.62f)
                }
            }

            // ============ 5.6 反馈类 ============
            ClSectionHeader(title = "反馈类")
            Column(
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClOutlineSmallButton(text = "点我看 ClToast", onClick = { toast.show("这是一条 Toast") })
            }

            Box(modifier = Modifier.height(ClTheme.spacing.xxxl).fillMaxWidth())
            Box(modifier = Modifier.size(ClTheme.spacing.xxl))
        }
    }
}
