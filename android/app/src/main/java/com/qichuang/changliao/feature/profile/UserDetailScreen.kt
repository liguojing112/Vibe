package com.qichuang.changliao.feature.profile

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.UserDetail
import com.qichuang.changliao.core.designsystem.component.ClBillGroup
import com.qichuang.changliao.core.designsystem.component.ClBillRow
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClConfirmDialog
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClIconButton
import com.qichuang.changliao.core.designsystem.component.ClImagePreview
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPhotoGrid
import com.qichuang.changliao.core.designsystem.component.ClPriceGrid
import com.qichuang.changliao.core.designsystem.component.ClPriceItem
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClProfileHero
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSelectOption
import com.qichuang.changliao.core.designsystem.component.ClTag
import com.qichuang.changliao.core.designsystem.component.ClTagKind
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 详情页的所有动作，集中成一个对象（省得 Screen 收十几个回调参数）。 */
data class UserDetailActions(
    val onChat: () -> Unit,
    val onOpenGift: () -> Unit,
    val onToggleFollow: () -> Unit,
    val onMenuVisibleChange: (Boolean) -> Unit,
    val onBlockConfirmVisibleChange: (Boolean) -> Unit,
    val onConfirmBlock: () -> Unit,
    val onReportVisibleChange: (Boolean) -> Unit,
    val onReportReasonChange: (ReportReason) -> Unit,
    val onReportContentChange: (String) -> Unit,
    val onSubmitReport: () -> Unit,
    val onPreview: (Int?) -> Unit,
    val onRetry: () -> Unit,
)

/**
 * 个人详情页（docs/01 P05，设计图 05）。
 *
 * 大图封面 + 标签 + 聊天价格面板 + 关于我 + 基本资料 + 相册 + 底部私信按钮；
 * 右上角「⋯」里是关注 / 拉黑 / 举报。
 */
@Composable
fun UserDetailScreen(
    state: UserDetailUiState,
    actions: UserDetailActions,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = {
            ClTopBar(
                title = "个人资料",
                onBack = onBack,
                action = {
                    ClIconButton(
                        icon = ClIcons.more,
                        contentDescription = "更多操作",
                        onClick = { actions.onMenuVisibleChange(true) },
                    )
                },
            )
        },
        fixedBottom = {
            if (state.detail != null) {
                ClFixedBottomArea {
                    // 送礼物只有男用户看女用户时才有（和价格面板同一个条件）；
                    // `01` P05 的底部栏没写它，但 P23 说「从个人详情进入」，所以放这儿
                    if (state.showPricePanel) {
                        ClSecondaryButton(
                            text = "送礼物",
                            modifier = Modifier.weight(1f),
                            onClick = actions.onOpenGift,
                        )
                    }
                    ClPrimaryButton(
                        text = state.chatLabel,
                        fillMaxWidth = false,
                        modifier = Modifier.weight(1f),
                        onClick = actions.onChat,
                    )
                }
            }
        },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
            }
        }

        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = actions.onRetry)

            state.detail != null -> UserDetailContent(
                state = state,
                detail = state.detail,
                actions = actions,
            )
        }

        if (state.menuVisible) {
            ClBottomSheet(title = "更多操作", onClose = { actions.onMenuVisibleChange(false) }) {
                ClSelectOption(
                    text = state.followLabel,
                    selected = false,
                    onClick = actions.onToggleFollow,
                )
                ClSelectOption(
                    text = "拉黑",
                    selected = false,
                    onClick = { actions.onBlockConfirmVisibleChange(true) },
                )
                ClSelectOption(
                    text = "举报",
                    selected = false,
                    onClick = { actions.onReportVisibleChange(true) },
                )
            }
        }

        if (state.blockConfirmVisible) {
            ClConfirmDialog(
                title = "拉黑 ${state.detail?.nickname.orEmpty()}",
                message = "拉黑后你们互相看不到对方，也不能再互相发消息。",
                confirmText = "拉黑",
                danger = true,
                onConfirm = actions.onConfirmBlock,
                onDismiss = { actions.onBlockConfirmVisibleChange(false) },
            )
        }

        if (state.reportVisible) {
            ReportSheet(state = state, actions = actions)
        }

        // 相册大图预览
        if (state.previewIndex != null && state.photoUrls.isNotEmpty()) {
            ClImagePreview(
                urls = state.photoUrls,
                initialIndex = state.previewIndex,
                onDismiss = { actions.onPreview(null) },
            )
        }
    }
}

@Composable
private fun UserDetailContent(
    state: UserDetailUiState,
    detail: UserDetail,
    actions: UserDetailActions,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
    ) {
        ClProfileHero(
            name = detail.nickname,
            photoUrl = detail.coverUrl.ifBlank { null },
            age = detail.age,
            meta = metaOf(detail),
        )

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = ClTheme.spacing.l),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
        ) {
            ProfileTags(state, detail)

            if (state.showPricePanel) {
                PricePanel(detail)
            }

            detail.bio.ifBlank { null }?.let { bio ->
                Section(title = "关于我") {
                    Text(
                        text = bio,
                        style = ClTheme.typography.body,
                        color = ClTheme.colors.inkSecondary,
                    )
                }
            }

            if (hasBasicInfo(detail)) {
                Section(title = "基本资料") {
                    ClBillGroup {
                        val rows = listOfNotNull(
                            detail.city.ifBlank { null }?.let { "城市" to it },
                            detail.height?.let { "身高" to "$it cm" },
                            detail.job.ifBlank { null }?.let { "职业" to it },
                            detail.education.ifBlank { null }?.let { "学历" to it },
                            detail.datingPurpose.ifBlank { null }?.let { "交友期待" to it },
                        )
                        rows.forEachIndexed { index, (label, value) ->
                            ClBillRow(
                                label = label,
                                value = value,
                                showDivider = index != rows.lastIndex,
                            )
                        }
                    }
                }
            }

            if (detail.photos.isNotEmpty()) {
                Section(title = "相册") {
                    ClPhotoGrid(
                        urls = state.photoUrls,
                        onClick = { index -> actions.onPreview(index) },
                    )
                }
            }

            if (state.tagNames.isNotEmpty()) {
                Section(title = "兴趣话题") {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        state.tagNames.forEach { name ->
                            ClTag(text = name, kind = ClTagKind.Gold)
                        }
                    }
                }
            }

            Box(modifier = Modifier.padding(bottom = ClTheme.spacing.l))
        }
    }
}

/** 真人认证 / 在线 / 新人 —— 都是服务端算好的标记（docs/01 P05）。 */
@Composable
private fun ProfileTags(state: UserDetailUiState, detail: UserDetail) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (detail.verified) {
            ClTag(text = "真人认证", kind = ClTagKind.Gold)
        }
        if (detail.online) {
            ClTag(text = "在线", kind = ClTagKind.Green)
        }
        if (detail.isNew) {
            ClTag(text = "新人", kind = ClTagKind.Pink)
        }
        if (state.isMutual) {
            ClTag(text = "互相关注", kind = ClTagKind.Pink)
        } else if (state.isFollowing) {
            ClTag(text = "已关注", kind = ClTagKind.Pink)
        }
    }
}

/** 聊天价格面板（只有男用户看女用户时有）。 */
@Composable
private fun PricePanel(detail: UserDetail) {
    val price = detail.price ?: return

    Section(title = "聊天价格") {
        Column(verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
            ClPriceGrid(
                items = buildList {
                    add(
                        ClPriceItem(
                            label = "私信 / " + MoneyFormat.UNIT_PER_MESSAGE,
                            price = MoneyFormat.coinsShort(price.messagePrice),
                        ),
                    )
                    price.voicePrice?.let {
                        add(
                            ClPriceItem(
                                label = "语音 / " + MoneyFormat.UNIT_PER_MINUTE,
                                price = MoneyFormat.coinsShort(it),
                            ),
                        )
                    }
                    price.videoPrice?.let {
                        add(
                            ClPriceItem(
                                label = "视频 / " + MoneyFormat.UNIT_PER_MINUTE,
                                price = MoneyFormat.coinsShort(it),
                            ),
                        )
                    }
                },
            )

            price.freeHint?.let {
                Text(
                    text = it,
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.muted,
                )
            }
        }
    }
}

@Composable
private fun ReportSheet(state: UserDetailUiState, actions: UserDetailActions) {
    ClBottomSheet(title = "举报", onClose = { actions.onReportVisibleChange(false) }) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = ClTheme.spacing.l),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
        ) {
            ClChipGroup(
                options = ReportReason.entries.map { it.label },
                selected = setOfNotNull(state.reportReason?.label),
                onSelectionChange = { labels ->
                    ReportReason.entries.firstOrNull { it.label in labels }
                        ?.let(actions.onReportReasonChange)
                },
            )

            ClTextField(
                label = "补充说明（选填）",
                value = state.reportContent,
                onValueChange = actions.onReportContentChange,
                placeholder = "说说发生了什么",
                singleLine = false,
                maxLength = 500,
            )

            ClPrimaryButton(
                text = "提交举报",
                enabled = state.reportReason != null,
                onClick = actions.onSubmitReport,
            )

            Box(modifier = Modifier.padding(bottom = ClTheme.spacing.l))
        }
    }
}

@Composable
private fun Section(title: String, content: @Composable () -> Unit) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        ClSectionHeader(title = title)
        content()
    }
}

/** 「上海 · 品牌设计师 · 本科」，缺哪项就不显示哪项。 */
private fun metaOf(detail: UserDetail): String = listOfNotNull(
    detail.city.ifBlank { null },
    detail.job.ifBlank { null },
    detail.education.ifBlank { null },
).joinToString(" · ")

/** 一个字段都没有时不画「基本资料」这块，省得留一个空框。 */
private fun hasBasicInfo(detail: UserDetail): Boolean =
    detail.city.isNotBlank() ||
        detail.height != null ||
        detail.job.isNotBlank() ||
        detail.education.isNotBlank() ||
        detail.datingPurpose.isNotBlank()
