package com.qichuang.changliao.feature.settings

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.BuildConfig
import com.qichuang.changliao.core.navigation.Routes

/**
 * P15 隐私与设置。从 P04「我的」的菜单进来。
 *
 * 「连点版本号进组件预览页」是仅 debug 的暗门 —— 开发入口页删掉之后，
 * 组件预览页（`docs/03` 第 7 节要求每加一个组件都登记）就靠它进。
 */
@Composable
fun SettingsRoute(
    onBack: () -> Unit,
    onOpenRoute: (String) -> Unit,
    viewModel: SettingsViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    SettingsScreen(
        state = state,
        onBack = onBack,
        onOpenRoute = onOpenRoute,
        onReminderChange = viewModel::onReminderChange,
        onShowInNearbyChange = viewModel::onShowInNearbyChange,
        onPasswordSheetVisibleChange = viewModel::onPasswordSheetVisibleChange,
        onOldPasswordChange = viewModel::onOldPasswordChange,
        onNewPasswordChange = viewModel::onNewPasswordChange,
        onConfirmPasswordChange = viewModel::onConfirmPasswordChange,
        onSubmitPassword = viewModel::onSubmitPassword,
        onCancelConfirmVisibleChange = viewModel::onCancelConfirmVisibleChange,
        onConfirmCancelAccount = viewModel::onConfirmCancelAccount,
        onLogout = viewModel::onLogout,
        onSecretUnlock = { if (BuildConfig.DEBUG) onOpenRoute(Routes.GALLERY) },
        onToastShown = viewModel::onToastShown,
    )
}
