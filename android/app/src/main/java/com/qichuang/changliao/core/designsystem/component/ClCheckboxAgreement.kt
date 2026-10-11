package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 协议勾选（docs/03 第 5.5 节，对应 P00 登录页）：
 * 不勾选不能登录；《用户协议》《隐私政策》可点。
 */
@Composable
fun ClCheckboxAgreement(
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    modifier: Modifier = Modifier,
    prefix: String = "我已阅读并同意",
    userAgreementText: String = "《用户协议》",
    privacyText: String = "《隐私政策》",
    onUserAgreement: () -> Unit = {},
    onPrivacy: () -> Unit = {},
    error: String? = null,
) {
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Checkbox(
                checked = checked,
                onCheckedChange = onCheckedChange,
                colors = CheckboxDefaults.colors(
                    checkedColor = ClTheme.colors.primary,
                    checkmarkColor = ClTheme.colors.onPrimary,
                    uncheckedColor = ClTheme.colors.muted,
                ),
            )

            Text(
                text = prefix,
                style = ClTheme.typography.caption,
                color = ClTheme.colors.inkSecondary,
            )
            AgreementLink(text = userAgreementText, onClick = onUserAgreement)
            Text(
                text = "和",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.inkSecondary,
            )
            AgreementLink(text = privacyText, onClick = onPrivacy)
        }

        if (error != null) {
            Text(
                text = error,
                style = ClTheme.typography.caption,
                color = ClTheme.colors.danger,
                modifier = Modifier.padding(start = ClTheme.spacing.l),
            )
        }
    }
}

@Composable
private fun AgreementLink(text: String, onClick: () -> Unit) {
    Text(
        text = text,
        style = ClTheme.typography.caption,
        color = ClTheme.colors.primaryText,
        modifier = Modifier
            .clip(RoundedCornerShape(4.dp))
            .clickable(onClick = onClick)
            .padding(vertical = ClTheme.spacing.xxs),
    )
}
