package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 底部弹窗（docs/03 第 5.6 节，对应原型 `.sheet`）：
 * **P30 余额不足、P31 送礼、收费说明、举报原因**都用它。
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClBottomSheet(
    onClose: () -> Unit,
    modifier: Modifier = Modifier,
    title: String? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    ModalBottomSheet(
        onDismissRequest = onClose,
        sheetState = sheetState,
        shape = ClTheme.shapes.sheet,
        containerColor = ClTheme.colors.surface,
        scrimColor = ClTheme.colors.mask,
        modifier = modifier,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = ClTheme.spacing.xl),
        ) {
            if (title != null) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(
                            start = ClTheme.spacing.xl,
                            end = ClTheme.spacing.xl,
                            bottom = ClTheme.spacing.m,
                        ),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text(
                        text = title,
                        style = ClTheme.typography.sectionTitle,
                        color = ClTheme.colors.ink,
                        modifier = Modifier.weight(1f),
                    )
                    Box(
                        modifier = Modifier
                            .size(ClTheme.size.iconM)
                            .clickable(onClick = onClose),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(
                            imageVector = ClIcons.more,
                            contentDescription = "关闭",
                            tint = ClTheme.colors.muted,
                            modifier = Modifier.size(ClTheme.size.iconS),
                        )
                    }
                }
            }

            content()
        }
    }
}
