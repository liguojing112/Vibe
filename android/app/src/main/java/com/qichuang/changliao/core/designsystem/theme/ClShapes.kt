package com.qichuang.changliao.core.designsystem.theme

import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Immutable
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.unit.dp

/**
 * 圆角 token（docs/03 第 4 节）。
 *
 * 气泡的四个角顺序同 CSS border-radius：左上 / 右上 / 右下 / 左下。
 */
@Immutable
data class ClShapes(
    val pill: Shape,
    val card: Shape,
    val cardSmall: Shape,
    val input: Shape,
    val sheet: Shape,
    val bubbleMine: Shape,
    val bubbleOther: Shape,
    val photo: Shape,
    val circle: Shape,
)

val ClShapesDefault = ClShapes(
    // 50% —— 按钮、Chip、胶囊
    pill = RoundedCornerShape(percent = 50),
    card = RoundedCornerShape(15.dp),
    cardSmall = RoundedCornerShape(12.dp),
    input = RoundedCornerShape(10.dp),
    // 底部弹窗只圆上两角
    sheet = RoundedCornerShape(topStart = 23.dp, topEnd = 23.dp),
    // 13/13/4/13 —— 右下角是「尾巴」
    bubbleMine = RoundedCornerShape(topStart = 13.dp, topEnd = 13.dp, bottomEnd = 4.dp, bottomStart = 13.dp),
    // 13/13/13/4 —— 左下角是「尾巴」
    bubbleOther = RoundedCornerShape(topStart = 13.dp, topEnd = 13.dp, bottomEnd = 13.dp, bottomStart = 4.dp),
    photo = RoundedCornerShape(8.dp),
    circle = CircleShape,
)
