package com.qichuang.changliao.feature.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.component.ClCheckboxAgreement
import com.qichuang.changliao.core.designsystem.component.ClPasswordField
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 登录页（docs/01 P00）。
 *
 * 只负责画界面：所有字段状态与校验都在 [LoginViewModel]。
 */
@Composable
fun LoginScreen(
    state: LoginUiState,
    onAccountChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onAgreedChange: (Boolean) -> Unit,
    onSubmit: () -> Unit,
    onRegister: () -> Unit,
    onUserAgreement: () -> Unit,
    onPrivacy: () -> Unit,
    onToastShown: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(modifier = modifier) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        BoxWithConstraints(modifier = Modifier.fillMaxSize()) {
            // 手机适配（T9.14）：
            // - 横屏 / 矮屏（<600dp）收紧留白，否则登录按钮会被挤出首屏；
            // - 内容限宽 420dp 并居中，平板上不会拉成一整行。
            val compact = maxHeight < ClTheme.size.compactHeight
            val sectionGap = if (compact) ClTheme.spacing.m else ClTheme.spacing.l
            val edgeGap = if (compact) ClTheme.spacing.l else ClTheme.spacing.xxxl

            Column(
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .widthIn(max = ClTheme.size.contentMaxWidth)
                    .fillMaxHeight()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = ClTheme.spacing.xxl),
                verticalArrangement = Arrangement.spacedBy(sectionGap),
            ) {
                Spacer(modifier = Modifier.height(edgeGap))

                Text(
                    text = "畅撩交友 ✦",
                    style = ClTheme.typography.pageTitle,
                    color = ClTheme.colors.ink,
                )
                Text(
                    text = "遇见同频的人，聊聊今天的心情",
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.muted,
                )

                Spacer(modifier = Modifier.height(ClTheme.spacing.m))

                ClTextField(
                    label = "手机号 / 账号",
                    value = state.account,
                    onValueChange = onAccountChange,
                    placeholder = "请输入手机号或账号",
                    error = state.accountError,
                    maxLength = 32,
                )

                ClPasswordField(
                    label = "密码",
                    value = state.password,
                    onValueChange = onPasswordChange,
                    placeholder = "请输入密码",
                    error = state.passwordError,
                    maxLength = 20,
                )

                ClCheckboxAgreement(
                    checked = state.agreed,
                    onCheckedChange = onAgreedChange,
                    error = state.agreementError,
                    onUserAgreement = onUserAgreement,
                    onPrivacy = onPrivacy,
                )

                ClPrimaryButton(
                    text = "登录",
                    loading = state.submitting,
                    enabled = !state.submitting,
                    onClick = onSubmit,
                )

                Box(
                    modifier = Modifier.fillMaxWidth(),
                    contentAlignment = Alignment.Center,
                ) {
                    ClTextButton(text = "注册新账号", onClick = onRegister)
                }

                Spacer(modifier = Modifier.height(edgeGap))
            }
        }
    }
}
