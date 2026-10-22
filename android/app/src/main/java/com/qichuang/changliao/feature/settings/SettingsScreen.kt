package com.qichuang.changliao.feature.settings

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
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import com.qichuang.changliao.BuildConfig
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClConfirmDialog
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClMenuGroup
import com.qichuang.changliao.core.designsystem.component.ClMenuRow
import com.qichuang.changliao.core.designsystem.component.ClPasswordField
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClSwitch
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.navigation.Routes

/** 连点几下版本号才进组件预览页（只有 debug 包能用）。 */
private const val VERSION_TAPS_TO_GALLERY = 7

/** 协议类型（`/app/agreement/{type}`）。 */
private const val AGREEMENT_TYPE_USER = "user"
private const val AGREEMENT_TYPE_PRIVACY = "privacy"

/**
 * 隐私与设置（P15，设计图 15）。
 *
 * 四块：通知（本地开关）→ 隐私（展示给附近的人 + 黑名单）→ 账号（改密码 / 注销 / 退出登录）
 * → 关于（协议、隐私政策、版本号）。
 *
 * 「连点版本号进组件预览页」是**仅 debug** 的暗门（`docs/08` T6.6 的要求）：
 * 开发入口页（DevEntriesPage）删掉之后，组件预览页就靠这个进。
 */
@Composable
fun SettingsScreen(
    state: SettingsUiState,
    onBack: () -> Unit,
    onOpenRoute: (String) -> Unit,
    onReminderChange: (Boolean) -> Unit,
    onShowInNearbyChange: (Boolean) -> Unit,
    onPasswordSheetVisibleChange: (Boolean) -> Unit,
    onOldPasswordChange: (String) -> Unit,
    onNewPasswordChange: (String) -> Unit,
    onConfirmPasswordChange: (String) -> Unit,
    onSubmitPassword: () -> Unit,
    onCancelConfirmVisibleChange: (Boolean) -> Unit,
    onConfirmCancelAccount: () -> Unit,
    onLogout: () -> Unit,
    onSecretUnlock: () -> Unit,
    onToastShown: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "隐私与设置", onBack = onBack) },
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
                ClSectionHeader(title = "通知")
                ClCard {
                    ClSettingRow(
                        label = "消息提醒",
                        desc = "新消息的声音和震动（只在本机生效）",
                        trailing = {
                            ClSwitch(checked = state.reminderEnabled, onCheckedChange = onReminderChange)
                        },
                    )
                }

                ClSectionHeader(title = "隐私")
                ClCard(contentPadding = PaddingValues()) {
                    ClMenuGroup {
                        ClSettingRow(
                            label = "展示给附近的人",
                            desc = "关闭后别人在同城列表里看不到你",
                            trailing = {
                                ClSwitch(
                                    checked = state.showInNearby,
                                    enabled = !state.switching,
                                    onCheckedChange = onShowInNearbyChange,
                                )
                            },
                        )
                    }
                    ClMenuGroup {
                        ClMenuRow(
                            icon = ClIcons.shield,
                            label = "黑名单",
                            showDivider = false,
                            onClick = { onOpenRoute(Routes.BLOCK_LIST) },
                        )
                    }
                }

                ClSectionHeader(title = "账号")
                ClCard(contentPadding = PaddingValues()) {
                    ClMenuGroup {
                        ClMenuRow(
                            icon = ClIcons.settings,
                            label = "修改密码",
                            onClick = { onPasswordSheetVisibleChange(true) },
                        )
                        ClMenuRow(
                            icon = ClIcons.warning,
                            label = "注销账号",
                            extra = "不可恢复",
                            showDivider = false,
                            onClick = { onCancelConfirmVisibleChange(true) },
                        )
                    }
                }

                ClSectionHeader(title = "关于")
                ClCard(contentPadding = PaddingValues()) {
                    ClMenuGroup {
                        ClMenuRow(
                            icon = ClIcons.shield,
                            label = "用户协议",
                            onClick = { onOpenRoute(Routes.agreement(AGREEMENT_TYPE_USER)) },
                        )
                        ClMenuRow(
                            icon = ClIcons.shield,
                            label = "隐私政策",
                            onClick = { onOpenRoute(Routes.agreement(AGREEMENT_TYPE_PRIVACY)) },
                        )
                        VersionRow(onSecretUnlock = onSecretUnlock)
                    }
                }

                // 安全提醒（设计图 15 底部那一段）。`docs/01` P15 的清单里没有它，
                // 但对一个付费交友产品来说这句是**该有的**：平台不做担保，用户得自己防转账诈骗。
                Text(
                    text = SAFETY_TIP,
                    style = ClTheme.typography.micro,
                    color = ClTheme.colors.muted,
                    modifier = Modifier.padding(horizontal = ClTheme.spacing.xs),
                )

                // 退出登录：客户反馈原来那个文字按钮太不显眼。改成大号红底按钮，
                // 满宽、居中，放在页面最底部。
                ClPrimaryButton(
                    text = "退出登录",
                    large = true,
                    danger = true,
                    modifier = Modifier.padding(top = ClTheme.spacing.l),
                    onClick = onLogout,
                )
            }
        }
    }

    if (state.passwordSheetVisible) {
        ChangePasswordSheet(state = state, actions = ChangePasswordActions(
            onOldChange = onOldPasswordChange,
            onNewChange = onNewPasswordChange,
            onConfirmChange = onConfirmPasswordChange,
            onSubmit = onSubmitPassword,
            onClose = { onPasswordSheetVisibleChange(false) },
        ))
    }

    if (state.cancelConfirmVisible) {
        ClConfirmDialog(
            title = "注销账号",
            message = "注销后账号无法恢复，余额和积分会由平台处理。确定要注销吗？",
            confirmText = "确定注销",
            danger = true,
            onConfirm = onConfirmCancelAccount,
            onDismiss = { onCancelConfirmVisibleChange(false) },
        )
    }
}

/** 版本号那一行：连点 7 下进组件预览页（仅 debug）。 */
@Composable
private fun VersionRow(onSecretUnlock: () -> Unit) {
    var taps by remember { mutableIntStateOf(0) }

    ClMenuRow(
        icon = ClIcons.spark,
        label = "关于畅撩交友",
        extra = "版本 ${BuildConfig.VERSION_NAME}",
        showDivider = false,
        onClick = {
            taps += 1
            if (taps >= VERSION_TAPS_TO_GALLERY) {
                taps = 0
                onSecretUnlock()
            }
        },
    )
}

private data class ChangePasswordActions(
    val onOldChange: (String) -> Unit,
    val onNewChange: (String) -> Unit,
    val onConfirmChange: (String) -> Unit,
    val onSubmit: () -> Unit,
    val onClose: () -> Unit,
)

/** 修改密码弹窗（P15）：旧密码 + 新密码 + 再输一次。 */
@Composable
private fun ChangePasswordSheet(state: SettingsUiState, actions: ChangePasswordActions) {
    ClBottomSheet(title = "修改密码", onClose = actions.onClose) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = ClTheme.spacing.l),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            ClPasswordField(
                label = "旧密码",
                value = state.oldPassword,
                onValueChange = actions.onOldChange,
                error = state.passwordError,
                maxLength = SettingsViewModel.PASSWORD_MAX,
            )
            ClPasswordField(
                label = "新密码",
                value = state.newPassword,
                onValueChange = actions.onNewChange,
                placeholder = "8~20 位，字母加数字",
                error = state.newPasswordHint,
                maxLength = SettingsViewModel.PASSWORD_MAX,
            )
            ClPasswordField(
                label = "确认新密码",
                value = state.confirmPassword,
                onValueChange = actions.onConfirmChange,
                error = state.confirmHint,
                maxLength = SettingsViewModel.PASSWORD_MAX,
            )

            ClPrimaryButton(
                text = "确定修改",
                enabled = state.canSubmitPassword,
                loading = state.submittingPassword,
                modifier = Modifier.fillMaxWidth(),
                onClick = actions.onSubmit,
            )

            Text(
                text = "改完密码之后当前登录不会掉。",
                style = ClTheme.typography.tiny,
                color = ClTheme.colors.muted,
                modifier = Modifier.padding(bottom = ClTheme.spacing.l),
            )
        }
    }
}

/** 设置页底部的安全提醒（设计图 15）。 */
private const val SAFETY_TIP =
    "畅撩交友提醒你警惕交友陷阱：平台不提供任何担保，请保护好个人信息，谨慎处理转账请求。"
