package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 全屏图片预览（docs/03 第 5.6 节）：个人详情相册、聊天图片点击后用。
 * 单击任意处或左上角关闭。
 */
@Composable
fun ClImagePreview(
    urls: List<String>,
    onDismiss: () -> Unit,
    modifier: Modifier = Modifier,
    initialIndex: Int = 0,
) {
    if (urls.isEmpty()) {
        return
    }

    val pagerState = rememberPagerState(
        initialPage = initialIndex.coerceIn(0, urls.size - 1),
        pageCount = { urls.size },
    )

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(ClTheme.colors.callBgDark)
            .clickable(
                interactionSource = remember { MutableInteractionSource() },
                indication = null,
                onClick = onDismiss,
            ),
    ) {
        HorizontalPager(
            state = pagerState,
            modifier = Modifier.fillMaxSize(),
        ) { page ->
            AsyncImage(
                model = urls[page],
                contentDescription = null,
                contentScale = ContentScale.Fit,
                modifier = Modifier
                    .fillMaxSize()
                    .padding(ClTheme.spacing.l),
            )
        }

        ClIconButton(
            icon = ClIcons.back,
            contentDescription = "关闭",
            modifier = Modifier
                .align(Alignment.TopStart)
                .padding(ClTheme.spacing.l),
            onClick = onDismiss,
        )

        if (urls.size > 1) {
            Text(
                text = "${pagerState.currentPage + 1} / ${urls.size}",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.surface,
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(ClTheme.spacing.xxl),
            )
        }
    }
}
