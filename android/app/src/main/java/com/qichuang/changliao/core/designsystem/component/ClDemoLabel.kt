package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 「演示界面」小标签（docs/03 第 5.3 节，对应原型 `.demo-label`）：
 * P18~P21 这些只做页面的语音/视频界面顶部用它。
 */
@Composable
fun ClDemoLabel(
    modifier: Modifier = Modifier,
    text: String = "演示界面",
) {
    Text(
        text = text,
        style = ClTheme.typography.micro,
        color = ClTheme.colors.muted,
        maxLines = 1,
        modifier = modifier
            .clip(RoundedCornerShape(7.dp))
            .background(ClTheme.colors.bgInput)
            .padding(horizontal = ClTheme.spacing.s, vertical = ClTheme.spacing.xs),
    )
}
