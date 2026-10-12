package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 通话页未选中圆形按钮的底色透明度（设计图是半透明白圆）。 */
private const val INACTIVE_CONTROL_ALPHA = 0.16f

/** 通话页的一个圆形按钮（静音、扬声器、礼物…）。 */
data class ClCallControl(
    val key: String,
    val label: String,
    val icon: ImageVector,
    val active: Boolean = false,
)

/**
 * 通话控制区（docs/03 第 5.4 节，设计图 19、20）：一排圆形按钮（选中态是黄底）+ 红色「结束通话」。
 *
 * P19 语音、P20 视频共用；页面背景是深色，所以这里的文字用白色。
 */
@Composable
fun ClCallControls(
    controls: List<ClCallControl>,
    onSelect: (String) -> Unit,
    onHangup: () -> Unit,
    modifier: Modifier = Modifier,
    hangupText: String = "结束通话",
) {
    Column(
        modifier = modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xl),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            controls.forEach { control ->
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                ) {
                    Box(
                        modifier = Modifier
                            .size(ClTheme.size.buttonHeight + ClTheme.spacing.m)
                            .clip(ClTheme.shapes.circle)
                            .background(
                                if (control.active) {
                                    ClTheme.colors.primary
                                } else {
                                    ClTheme.colors.surface.copy(alpha = INACTIVE_CONTROL_ALPHA)
                                },
                            )
                            .clickable { onSelect(control.key) },
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(
                            imageVector = control.icon,
                            contentDescription = control.label,
                            tint = if (control.active) ClTheme.colors.onPrimary else ClTheme.colors.surface,
                            modifier = Modifier.size(ClTheme.size.iconM),
                        )
                    }
                    Text(
                        text = control.label,
                        style = ClTheme.typography.tiny,
                        color = ClTheme.colors.surface,
                    )
                }
            }
        }

        Row(
            modifier = Modifier
                .height(ClTheme.size.buttonHeight + ClTheme.spacing.xs)
                .clip(ClTheme.shapes.pill)
                .background(ClTheme.colors.hangup)
                .clickable(onClick = onHangup)
                .padding(horizontal = ClTheme.spacing.xxl),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.Center,
        ) {
            Icon(
                imageVector = ClIcons.hangup,
                contentDescription = null,
                tint = ClTheme.colors.surface,
                modifier = Modifier.size(ClTheme.size.iconM),
            )
            Text(
                text = hangupText,
                style = ClTheme.typography.button,
                color = ClTheme.colors.surface,
                modifier = Modifier.padding(start = ClTheme.spacing.s),
            )
        }
    }
}
