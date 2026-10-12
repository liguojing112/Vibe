package com.qichuang.changliao.feature.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClPasswordField
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 注册页（docs/01 P00）。性别注册后不能改，所以下面挂了提示。
 */
@Composable
fun RegisterScreen(
    state: RegisterUiState,
    onPhoneChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onConfirmPasswordChange: (String) -> Unit,
    onGenderChange: (Int) -> Unit,
    onNicknameChange: (String) -> Unit,
    onSubmit: () -> Unit,
    onBack: () -> Unit,
    onToastShown: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        topBar = { ClTopBar(title = "注册", onBack = onBack) },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = ClTheme.spacing.xxl),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
        ) {
            Spacer(modifier = Modifier.height(ClTheme.spacing.l))

            ClTextField(
                label = "手机号",
                value = state.phone,
                onValueChange = onPhoneChange,
                placeholder = "请输入 11 位手机号",
                error = state.phoneError,
                maxLength = 11,
            )

            ClTextField(
                label = "昵称",
                value = state.nickname,
                onValueChange = onNicknameChange,
                placeholder = "2~12 个字",
                error = state.nicknameError,
                maxLength = 12,
            )

            ClPasswordField(
                label = "密码",
                value = state.password,
                onValueChange = onPasswordChange,
                placeholder = "6~20 位，含字母和数字",
                error = state.passwordError,
                maxLength = 20,
            )

            ClPasswordField(
                label = "确认密码",
                value = state.confirmPassword,
                onValueChange = onConfirmPasswordChange,
                placeholder = "再输入一次密码",
                error = state.confirmPasswordError,
                maxLength = 20,
            )

            Column(verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs)) {
                Text(
                    text = "性别",
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.inkSecondary,
                )
                ClChipGroup(
                    options = listOf(Gender.label(Gender.MALE), Gender.label(Gender.FEMALE)),
                    selected = if (state.gender == Gender.UNKNOWN) {
                        emptySet()
                    } else {
                        setOf(Gender.label(state.gender))
                    },
                    onSelectionChange = { selected ->
                        selected.firstOrNull()?.let { onGenderChange(Gender.of(it)) }
                    },
                )
                Text(
                    text = "注册后不可修改",
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.muted,
                )
                if (state.genderError != null) {
                    Text(
                        text = state.genderError,
                        style = ClTheme.typography.caption,
                        color = ClTheme.colors.danger,
                    )
                }
            }

            Spacer(modifier = Modifier.height(ClTheme.spacing.s))

            ClPrimaryButton(
                text = "注册并登录",
                loading = state.submitting,
                enabled = !state.submitting,
                onClick = onSubmit,
            )

            Spacer(modifier = Modifier.height(ClTheme.spacing.xxxl))
        }
    }
}
