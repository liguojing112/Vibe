package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.style.TextOverflow
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 封面照片占位色，与头像统一（docs/03 第 6 节第 7 条）。 */
private val CoverPlaceholder = Color(0xFFE8D8C7)

/**
 * 个人详情的封面（docs/01 P05，设计图 05）：大图铺满，底部压一层渐隐，
 * 白字写「昵称 年龄」和「城市 · 职业 · 学历」。
 *
 * 标签（真人认证 / 在线 / 兴趣）由页面放在它下面，不塞进这里。
 */
@Composable
fun ClProfileHero(
    name: String,
    modifier: Modifier = Modifier,
    photoUrl: String? = null,
    age: Int? = null,
    meta: String = "",
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(ClTheme.size.coverHeight)
            .background(CoverPlaceholder),
    ) {
        AsyncImage(
            model = photoUrl,
            contentDescription = name,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize(),
        )

        // 底部渐隐，保证白字在任何照片上都看得清
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Brush.verticalGradient(listOf(Color.Transparent, ClTheme.colors.mask))),
        )

        Column(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .fillMaxWidth()
                .padding(ClTheme.spacing.l),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
        ) {
            Row(
                verticalAlignment = Alignment.Bottom,
                horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
            ) {
                Text(
                    text = name,
                    style = ClTheme.typography.pageTitle,
                    color = ClTheme.colors.surface,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                if (age != null) {
                    Text(
                        text = age.toString(),
                        style = ClTheme.typography.cardTitle,
                        color = ClTheme.colors.surface,
                    )
                }
            }

            if (meta.isNotEmpty()) {
                Text(
                    text = meta,
                    style = ClTheme.typography.bodySmall,
                    color = ClTheme.colors.surface,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}
