package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.material3.BottomSheetDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.unit.dp
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
    /** 默认只有上面两个圆角（贴底）；传 `ClTheme.shapes.card` 可以做成四角圆角的悬浮卡片。 */
    shape: Shape = ClTheme.shapes.sheet,
    /**
     * 内容区高度占屏幕的比例（0~1）。null = 按内容自适应。
     *
     * 放在设计系统里是因为 feature 层不允许直接写 dp，只能传比例。
     */
    heightFraction: Float? = null,
    /** 标题字号，默认 `sectionTitle`；想更强调可以传 `pageTitle`。 */
    titleStyle: TextStyle = ClTheme.typography.sectionTitle,
    /** 内容自带了底部间距时置 true，去掉组件自带的 24dp，避免按钮下面多垫一层空白。 */
    compactBottom: Boolean = false,
    content: @Composable ColumnScope.() -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val screenHeight = LocalConfiguration.current.screenHeightDp

    ModalBottomSheet(
        onDismissRequest = onClose,
        sheetState = sheetState,
        shape = shape,
        containerColor = ClTheme.colors.surface,
        scrimColor = ClTheme.colors.mask,
        // compactBottom 时连系统手势条的安全区一起去掉：否则按钮下面会有一道 48dp 的白边，
        // 看起来像多出来的空白（间距改由调用方自己在 modifier 里控制）
        contentWindowInsets = {
            if (compactBottom) WindowInsets(0, 0, 0, 0) else BottomSheetDefaults.windowInsets
        },
        modifier = modifier,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .then(
                    if (heightFraction != null) {
                        Modifier.height((screenHeight * heightFraction).dp)
                    } else {
                        Modifier
                    },
                )
                .padding(bottom = if (compactBottom) 0.dp else ClTheme.spacing.xl),
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
                        style = titleStyle,
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
