package com.qichuang.changliao.core.designsystem.gallery

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.clickable
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClCheckboxAgreement
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClConfirmDialog
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClImagePreview
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPasswordField
import com.qichuang.changliao.core.designsystem.component.ClPullRefreshList
import com.qichuang.changliao.core.designsystem.component.ClRangeSlider
import com.qichuang.changliao.core.designsystem.component.ClSelectField
import com.qichuang.changliao.core.designsystem.component.ClTextField
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
import com.qichuang.changliao.core.designsystem.component.ClBenefitGrid
import com.qichuang.changliao.core.designsystem.component.ClBenefitItem
import com.qichuang.changliao.core.designsystem.component.ClCallControl
import com.qichuang.changliao.core.designsystem.component.ClCallControls
import com.qichuang.changliao.core.designsystem.component.ClChatBubble
import com.qichuang.changliao.core.designsystem.component.ClChatContent
import com.qichuang.changliao.core.designsystem.component.ClChatInputBar
import com.qichuang.changliao.core.designsystem.component.ClChatMessage
import com.qichuang.changliao.core.designsystem.component.ClChatStatus
import com.qichuang.changliao.core.designsystem.component.ClChatToolBar
import com.qichuang.changliao.core.designsystem.component.ClChatToolItem
import com.qichuang.changliao.core.designsystem.component.ClConversationItem
import com.qichuang.changliao.core.designsystem.component.ClConversationRow
import com.qichuang.changliao.core.designsystem.component.ClDatePickerDialog
import com.qichuang.changliao.core.designsystem.component.ClEntryRow
import com.qichuang.changliao.core.designsystem.component.ClGiftGrid
import com.qichuang.changliao.core.designsystem.component.ClGiftItem
import com.qichuang.changliao.core.designsystem.component.ClMatchCard
import com.qichuang.changliao.core.designsystem.component.ClMatchCardData
import com.qichuang.changliao.core.designsystem.component.ClMiniProfileRow
import com.qichuang.changliao.core.designsystem.component.ClPersonCard
import com.qichuang.changliao.core.designsystem.component.ClPersonCardData
import com.qichuang.changliao.core.designsystem.component.ClPersonTag
import com.qichuang.changliao.core.designsystem.component.ClPhotoGrid
import com.qichuang.changliao.core.designsystem.component.ClRechargePack
import com.qichuang.changliao.core.designsystem.component.ClRechargePackGrid
import com.qichuang.changliao.core.designsystem.component.ClStep
import com.qichuang.changliao.core.designsystem.component.ClStepList
import com.qichuang.changliao.core.designsystem.component.ClTaskCard
import com.qichuang.changliao.core.designsystem.component.ClTaskItem
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
        // 必须在 ClScaffold 的内容里取，才能拿到它提供的那个 Toast 宿主（在外面取到的是空壳）
        val toast = rememberClToast()

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

            // ============ 5.4 业务复用组件 ============
            ClSectionHeader(title = "业务复用组件")
            Column(
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
            ) {
                var giftId by remember { mutableStateOf<Long?>(1L) }
                var packId by remember { mutableStateOf<Long?>(2L) }
                var chatDraft by remember { mutableStateOf("") }
                var photoList by remember { mutableStateOf(listOf("", "")) }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    ClPersonCard(
                        user = ClPersonCardData(
                            name = "小满",
                            online = true,
                            tags = listOf(
                                ClPersonTag("可接听", ClTagKind.Green),
                                ClPersonTag("已认证", ClTagKind.Gold),
                            ),
                            meta = "26 岁 · 上海 · 品牌设计师",
                            intro = "喜欢逛展、拍照和在周末寻找好吃的面包店。",
                        ),
                        onClick = {},
                        actions = {
                            ClSoftButton(
                                text = "语音 60 币/分",
                                kind = ClSoftButtonKind.Yellow,
                                leadingIcon = ClIcons.mic,
                                onClick = {},
                            )
                            ClSoftButton(
                                text = "视频 120 币/分",
                                kind = ClSoftButtonKind.Peach,
                                leadingIcon = ClIcons.video,
                                onClick = {},
                            )
                            ClSoftButton(
                                text = "私信",
                                kind = ClSoftButtonKind.Gray,
                                leadingIcon = ClIcons.message,
                                onClick = {},
                            )
                        },
                    )
                }

                Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
                    ClMatchCard(
                        user = ClMatchCardData(name = "小满", meta = "上海 · 26 岁"),
                        actionText = "视频聊 · 120币/分",
                        modifier = Modifier.weight(1f),
                        onAction = {},
                        onClick = {},
                    )
                    ClMatchCard(
                        user = ClMatchCardData(name = "阿宁", meta = "上海 · 28 岁"),
                        actionText = "视频聊 · 120币/分",
                        modifier = Modifier.weight(1f),
                        available = false,
                        onAction = {},
                        onClick = {},
                    )
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    ClMiniProfileRow(
                        name = "小满",
                        desc = "今天看了你的资料 · 上海",
                        online = true,
                        buttonText = "查看",
                        onAction = {},
                    )
                    ClMiniProfileRow(
                        name = "阿宁",
                        desc = "昨天看了你的资料 · 上海",
                        buttonText = "查看",
                        onAction = {},
                    )
                    ClMiniProfileRow(
                        name = "晚晴",
                        desc = "非会员看不全谁看过你",
                        blurred = true,
                        buttonText = "开通会员",
                        showDivider = false,
                        onAction = {},
                    )
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    ClConversationRow(
                        conversation = ClConversationItem(
                            id = 1,
                            name = "小满",
                            lastMessage = "周末有个摄影展，一起去看看？",
                            time = "09:41",
                            unreadCount = 2,
                            online = true,
                        ),
                        onClick = {},
                    )
                    ClConversationRow(
                        conversation = ClConversationItem(
                            id = 2,
                            name = "阿宁",
                            lastMessage = "[礼物] 浪漫花束",
                            time = "昨天 21:03",
                        ),
                        showDivider = false,
                        onClick = {},
                    )
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    ClEntryRow(
                        icon = ClIcons.heart,
                        tint = ClTheme.colors.pink,
                        title = "看看谁喜欢我",
                        desc = "你有新的心动消息，快去看看",
                        trailingText = "刚刚",
                        onClick = {},
                    )
                    ClEntryRow(
                        icon = ClIcons.pin,
                        tint = ClTheme.colors.orange,
                        title = "附近的人",
                        desc = "发现一批距离很近的人",
                        trailingText = "昨天",
                        onClick = {},
                    )
                    ClEntryRow(
                        icon = ClIcons.eye,
                        tint = ClTheme.colors.green,
                        title = "最近访客",
                        desc = "小满刚刚看了你的主页",
                        badge = 2,
                        onClick = {},
                    )
                    ClEntryRow(
                        icon = ClIcons.bell,
                        tint = ClTheme.colors.primaryText,
                        title = "系统消息",
                        desc = "欢迎来到遇见你，完善资料更容易被发现",
                        trailingText = "昨天",
                        showDivider = false,
                        onClick = {},
                    )
                }

                ClCard {
                    ClChatBubble(
                        message = ClChatMessage(id = "1", text = "嗨，很高兴认识你！看你也喜欢看展～"),
                        isMine = false,
                    )
                    ClChatBubble(
                        message = ClChatMessage(id = "2", text = "你好呀！最近有什么推荐的展吗？"),
                        isMine = true,
                    )
                    ClChatBubble(
                        message = ClChatMessage(
                            id = "3",
                            content = ClChatContent.Gift,
                            text = "一朵玫瑰",
                            giftIcon = "🌹",
                        ),
                        isMine = true,
                    )
                    ClChatBubble(
                        message = ClChatMessage(id = "4", text = "发送失败的样子", status = ClChatStatus.Failed),
                        isMine = true,
                        onRetry = {},
                    )
                    ClChatBubble(
                        message = ClChatMessage(id = "5", text = "发送中…", status = ClChatStatus.Sending),
                        isMine = true,
                    )
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    ClChatToolBar(
                        items = listOf(
                            ClChatToolItem("voice", "语音通话", ClIcons.mic),
                            ClChatToolItem("video", "视频通话", ClIcons.video),
                            ClChatToolItem("gift", "送礼物", ClIcons.gift),
                        ),
                        onSelect = {},
                    )
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    ClChatInputBar(
                        value = chatDraft,
                        onValueChange = { chatDraft = it },
                        onSend = { toast.show("发送：$chatDraft") },
                        placeholder = "发一句问候 · 5 币/条",
                        maxLength = 500,
                        onEmoji = {},
                        onPickImage = {},
                    )
                }

                ClGiftGrid(
                    gifts = listOf(
                        ClGiftItem(1, "一朵玫瑰", "10 金币", "🌹"),
                        ClGiftItem(2, "热拿铁", "30 金币", "☕"),
                        ClGiftItem(3, "心动气球", "60 金币", "🎈"),
                        ClGiftItem(4, "浪漫花束", "120 金币", "💐"),
                        ClGiftItem(5, "星河许愿", "300 金币", "🌟"),
                        ClGiftItem(6, "心动城堡", "680 金币", "🏰"),
                    ),
                    selectedId = giftId,
                    onSelect = { giftId = it.id },
                )

                ClRechargePackGrid(
                    packs = listOf(
                        ClRechargePack(1, "60", "¥6"),
                        ClRechargePack(2, "300", "¥30"),
                        ClRechargePack(3, "680", "¥68"),
                        ClRechargePack(4, "1280", "¥128"),
                        ClRechargePack(5, "2980", "¥298"),
                        ClRechargePack(6, "6480", "¥648"),
                    ),
                    selectedId = packId,
                    onSelect = { packId = it.id },
                )

                ClTaskCard(
                    task = ClTaskItem(
                        id = 1,
                        title = "每日签到",
                        desc = "连续签到可领取玫瑰",
                        actionText = "签到",
                        icon = "📅",
                    ),
                    onAction = { toast.show("已签到") },
                )
                ClTaskCard(
                    task = ClTaskItem(
                        id = 2,
                        title = "完善照片",
                        desc = "上传 3 张生活照，展示更多面的你",
                        actionText = "已完成",
                        icon = "📷",
                        done = true,
                    ),
                    onAction = {},
                )

                ClBenefitGrid(
                    items = listOf(
                        ClBenefitItem("✨", "优先推荐", "让合适的人更早发现你"),
                        ClBenefitItem("👀", "查看访客", "不错过每一份好奇"),
                        ClBenefitItem("💌", "更多招呼", "主动开启更多对话"),
                        ClBenefitItem("🎉", "个性装扮", "展示你的特别之处"),
                    ),
                )

                ClCard {
                    ClStepList(
                        steps = listOf(
                            ClStep("核对身份信息", "姓名与证件信息一致"),
                            ClStep("真人活体检测", "按提示完成动作校验"),
                            ClStep("等待审核结果", "审核通过后开通对应权限"),
                        ),
                    )
                }

                ClCard {
                    ClPhotoGrid(
                        urls = photoList,
                        editable = true,
                        maxCount = 3,
                        onAdd = { photoList = photoList + "" },
                        onRemove = { index -> photoList = photoList.filterIndexed { i, _ -> i != index } },
                        onClick = {},
                        onMove = { from, to ->
                            photoList = photoList.toMutableList().also { it.add(to, it.removeAt(from)) }
                        },
                    )
                }

                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(ClTheme.shapes.card)
                        .background(ClTheme.colors.callBgDark)
                        .padding(ClTheme.spacing.l),
                ) {
                    ClCallControls(
                        controls = listOf(
                            ClCallControl("mute", "静音", ClIcons.mic),
                            ClCallControl("speaker", "扬声器", ClIcons.speaker, active = true),
                            ClCallControl("gift", "礼物", ClIcons.gift),
                        ),
                        onSelect = {},
                        onHangup = { toast.show("已结束通话") },
                    )
                }
            }

            // ============ 5.5 输入类 ============
            ClSectionHeader(title = "输入类")
            Column(
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
            ) {
                var text by remember { mutableStateOf("") }
                var password by remember { mutableStateOf("") }
                var city by remember { mutableStateOf("") }
                var chips by remember { mutableStateOf(setOf("推荐")) }
                var tags by remember { mutableStateOf(setOf("音乐", "旅行")) }
                var ageRange by remember { mutableStateOf(18f..60f) }
                var agreed by remember { mutableStateOf(false) }

                ClTextField(
                    label = "手机号",
                    value = text,
                    onValueChange = { text = it },
                    placeholder = "请输入手机号",
                    maxLength = 11,
                )
                ClTextField(
                    label = "带错误提示",
                    value = "",
                    onValueChange = {},
                    error = "手机号不能为空",
                )
                ClPasswordField(label = "密码", value = password, onValueChange = { password = it })
                ClSelectField(label = "城市", value = city, onClick = { city = "杭州" })

                ClChipGroup(
                    options = listOf("推荐", "新人", "同城"),
                    selected = chips,
                    onSelectionChange = { chips = it },
                )
                ClChipGroup(
                    options = listOf("认真交友", "兴趣搭子", "长期关系"),
                    selected = tags,
                    onSelectionChange = { tags = it },
                    multi = true,
                )

                ClRangeSlider(value = ageRange, onValueChange = { ageRange = it })

                ClCheckboxAgreement(checked = agreed, onCheckedChange = { agreed = it })
            }

            // ============ 5.6 反馈类 ============
            ClSectionHeader(title = "反馈类")
            Column(
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
            ) {
                var showSheet by remember { mutableStateOf(false) }
                var showDialog by remember { mutableStateOf(false) }
                var showPreview by remember { mutableStateOf(false) }
                var showDatePicker by remember { mutableStateOf(false) }
                var refreshing by remember { mutableStateOf(false) }
                val demoItems = remember { (1..8).map { "列表项 $it" } }

                Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m)) {
                    ClOutlineSmallButton(text = "点我看 ClToast", onClick = { toast.show("这是一条 Toast") })
                    ClOutlineSmallButton(text = "底部弹窗", onClick = { showSheet = true })
                    ClOutlineSmallButton(text = "确认对话框", onClick = { showDialog = true })
                }

                Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m)) {
                    ClOutlineSmallButton(text = "图片预览", onClick = { showPreview = true })
                    ClOutlineSmallButton(text = "下拉刷新", onClick = { refreshing = true })
                    ClOutlineSmallButton(text = "选日期", onClick = { showDatePicker = true })
                }

                ClCard {
                    Text(text = "ClLoading", style = ClTheme.typography.caption, color = ClTheme.colors.muted)
                    Box(modifier = Modifier.height(120.dp)) {
                        ClLoading()
                    }
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    Box(modifier = Modifier.height(200.dp)) {
                        ClEmpty(title = "还没有通话记录", desc = "去认识新朋友吧", actionText = "去发现")
                    }
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    Box(modifier = Modifier.height(200.dp)) {
                        ClErrorRetry(message = "网络不太好，请稍后重试", onRetry = {})
                    }
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    Box(modifier = Modifier.height(240.dp)) {
                        ClPullRefreshList(
                            items = demoItems,
                            refreshing = refreshing,
                            onRefresh = { refreshing = false },
                            hasMore = false,
                            itemContent = { item ->
                                ClSettingRow(label = item, modifier = Modifier.clickable { })
                            },
                        )
                    }
                }

                ClCard(contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp)) {
                    Box(modifier = Modifier.height(200.dp)) {
                        ClImagePreview(
                            urls = listOf("", ""),
                            onDismiss = {},
                        )
                    }
                }

                if (showSheet) {
                    ClBottomSheet(title = "余额不足", onClose = { showSheet = false }) {
                        Box(modifier = Modifier.padding(horizontal = ClTheme.spacing.xl)) {
                            Text(
                                text = "本次操作需要 5 金币，还差 2 金币",
                                style = ClTheme.typography.body,
                                color = ClTheme.colors.inkSecondary,
                            )
                        }
                    }
                }

                if (showDialog) {
                    ClConfirmDialog(
                        title = "退出登录",
                        message = "确定要退出当前账号吗？",
                        onConfirm = { showDialog = false },
                        onDismiss = { showDialog = false },
                        danger = true,
                    )
                }

                if (showPreview) {
                    ClImagePreview(urls = listOf(""), onDismiss = { showPreview = false })
                }

                if (showDatePicker) {
                    ClDatePickerDialog(
                        title = "选择生日",
                        onConfirm = {
                            toast.show("选了：$it")
                            showDatePicker = false
                        },
                        onDismiss = { showDatePicker = false },
                    )
                }
            }

            Box(modifier = Modifier.height(ClTheme.spacing.xxxl).fillMaxWidth())
        }
    }
}
