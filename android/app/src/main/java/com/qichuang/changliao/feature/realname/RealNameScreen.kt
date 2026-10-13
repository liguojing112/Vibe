package com.qichuang.changliao.feature.realname

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClCardDivider
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClNoticeCard
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClStep
import com.qichuang.changliao.core.designsystem.component.ClStepList
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 实名认证（P29，设计图 29）。
 *
 * 结构：状态卡（未认证 / 审核中 / 已通过 / 被驳回 + 原因）→ 表单（姓名、身份证号）→ 步骤说明 → 钉底按钮。
 *
 * 三处按文档而不是设计图办：
 * 1. 设计图里的「真人活体检测」本期不做，那一步改成「人工审核」（`docs/01` P29 原文）；
 * 2. **已通过之后不再显示表单**（服务端 `can_submit = false`），页面只显示打码号码；
 * 3. 身份证号**只回显打码的**（`110***********1233`）—— 完整号码客户端根本拿不到。
 */
@Composable
fun RealNameScreen(
    state: RealNameUiState,
    onBack: () -> Unit,
    onNameChange: (String) -> Unit,
    onIdCardChange: (String) -> Unit,
    onSubmit: () -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "实名认证", onBack = onBack) },
        fixedBottom = {
            if (state.showForm) {
                ClFixedBottomArea {
                    ClPrimaryButton(
                        text = "提交认证",
                        enabled = state.canSubmit,
                        loading = state.submitting,
                        modifier = Modifier.weight(1f),
                        onClick = onSubmit,
                    )
                }
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

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        top = ClTheme.spacing.l,
                        bottom = ClTheme.spacing.xl,
                    ),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClCard {
                    StatusRow(label = "当前状态", value = state.info?.statusText.orEmpty())
                    ClCardDivider()
                    StatusRow(label = "真实姓名", value = state.info?.realName.orEmpty())
                    if (state.info?.idCardMask?.isNotEmpty() == true) {
                        ClCardDivider()
                        StatusRow(label = "身份证号", value = state.info?.idCardMask.orEmpty())
                    }
                }

                Text(
                    text = state.statusDesc,
                    style = ClTheme.typography.bodySmall,
                    color = ClTheme.colors.inkSecondary,
                )

                // 被驳回时把原因顶到最显眼的地方
                state.info?.rejectReason?.takeIf { state.info?.rejected == true }?.let { reason ->
                    ClNoticeCard(title = "未通过原因", content = reason, highlight = true)
                }

                if (state.showForm) {
                    ClSectionHeader(title = "填写身份信息")
                    ClTextField(
                        label = "真实姓名",
                        value = state.nameInput,
                        onValueChange = onNameChange,
                        placeholder = "与身份证一致",
                        maxLength = RealNameViewModel.NAME_MAX_LENGTH,
                    )
                    ClTextField(
                        label = "身份证号",
                        value = state.idCardInput,
                        onValueChange = onIdCardChange,
                        placeholder = "18 位身份证号",
                        keyboardType = KeyboardType.Ascii,
                        maxLength = RealNameUiState.ID_CARD_LENGTH,
                    )

                    ClSectionHeader(title = "认证流程")
                    ClStepList(
                        steps = listOf(
                            ClStep(title = "1. 填写身份信息", desc = "姓名和身份证号要和证件完全一致"),
                            ClStep(title = "2. 人工审核", desc = "提交后由人工核对，一般 1~3 个工作日"),
                            ClStep(title = "3. 认证完成", desc = "通过后分成比例按实名的标准计算"),
                        ),
                    )

                    ClNoticeCard(
                        title = "信息安全",
                        content = "身份证号在服务端加密存储，页面上只显示打码号码。",
                    )
                }
            }
        }
    }
}

@Composable
private fun StatusRow(label: String, value: String) {
    ClSettingRow(
        label = label,
        trailing = {
            Text(
                text = value.ifEmpty { "—" },
                style = ClTheme.typography.body,
                color = ClTheme.colors.ink,
            )
        },
    )
}
