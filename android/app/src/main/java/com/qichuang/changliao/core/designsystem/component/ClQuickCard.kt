package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 快捷卡的配色（语音偏黄、视频偏粉，和设计图 01 一致）。
 *
 * 颜色**不能写在这里**：`ClTheme.colors` 是 @Composable 读的，枚举常量在编译期就要定值，
 * 写在枚举里编译器会直接报「@Composable invocations can only happen from ...」。
 */
enum class ClQuickCardKind {
    Voice,
    Video,
}

/** 底色 → 前景色（都是主题 token，深色主题自动跟着变）。 */
@Composable
private fun colorsOf(kind: ClQuickCardKind): Pair<Color, Color> = when (kind) {
    ClQuickCardKind.Voice -> ClTheme.colors.primarySoft to ClTheme.colors.primaryText
    ClQuickCardKind.Video -> ClTheme.colors.tagPinkBg to ClTheme.colors.tagPinkText
}

/** [ClQuickCard] 要展示的一栏（docs/01 P01 的两张快捷卡）。 */
data class ClQuickCardData(
    val title: String,
    val desc: String,
    val icon: ImageVector,
    val kind: ClQuickCardKind = ClQuickCardKind.Voice,
)

/**
 * 发现页顶部的快捷卡（docs/03 第 5.4 节，设计图 01）：
 * 左边标题 + 说明，右边一颗大图标，底色按 [ClQuickCardData.kind] 分黄/粉。
 *
 * 只在 `feature.call_enabled` 打开时出现（docs/01 第 4 节）。
 */
@Composable
fun ClQuickCard(
    card: ClQuickCardData,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val (background, content) = colorsOf(card.kind)

    Row(
        modifier = modifier
            .fillMaxWidth()
            .clip(ClTheme.shapes.card)
            .background(background)
            .clickable(onClick = onClick)
            .padding(horizontal = ClTheme.spacing.m, vertical = ClTheme.spacing.m),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
        ) {
            Text(
                text = card.title,
                style = ClTheme.typography.cardTitle,
                color = ClTheme.colors.ink,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = card.desc,
                style = ClTheme.typography.micro,
                color = content,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
        }

        Icon(
            imageVector = card.icon,
            contentDescription = null,
            tint = content,
            modifier = Modifier.size(ClTheme.size.iconL),
        )
    }
}

/** 快捷卡里「语音」那一张（P01）。[priceText] 传空串就不显示价格那一截。 */
fun voiceQuickCard(priceText: String): ClQuickCardData = ClQuickCardData(
    title = "听见心动",
    desc = listOfNotNull("1 对 1 语音", priceText.ifBlank { null }).joinToString(" · "),
    icon = ClIcons.mic,
    kind = ClQuickCardKind.Voice,
)

/** 快捷卡里「视频」那一张（P01）。[priceText] 传空串就不显示价格那一截。 */
fun videoQuickCard(priceText: String): ClQuickCardData = ClQuickCardData(
    title = "视频见面",
    desc = listOfNotNull("面对面聊天", priceText.ifBlank { null }).joinToString(" · "),
    icon = ClIcons.video,
    kind = ClQuickCardKind.Video,
)
