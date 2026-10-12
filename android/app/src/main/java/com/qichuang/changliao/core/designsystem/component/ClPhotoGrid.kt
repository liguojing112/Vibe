package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

private const val PHOTO_COLUMNS = 3

/** 相册占位色，与头像统一（docs/03 第 6 节第 7 条）。 */
private val PhotoPlaceholder = Color(0xFFE8D8C7)

/**
 * 相册九宫格（docs/03 第 5.4 节，设计图 05、14）：三列方形。
 *
 * [editable] = true 时每张右上角有删除按钮，且没满 [maxCount] 时末尾多一个「+」添加格（P14 最多 9 张）。
 */
@Composable
fun ClPhotoGrid(
    urls: List<String>,
    modifier: Modifier = Modifier,
    editable: Boolean = false,
    maxCount: Int = 9,
    onAdd: (() -> Unit)? = null,
    onRemove: ((Int) -> Unit)? = null,
    onClick: ((Int) -> Unit)? = null,
) {
    // 元素是照片下标；null 表示末尾的「+」添加格
    val tiles: List<Int?> = buildList {
        urls.indices.forEach { add(it) }
        if (editable && onAdd != null && urls.size < maxCount) {
            add(null)
        }
    }

    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        tiles.chunked(PHOTO_COLUMNS).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
                row.forEach { photoIndex ->
                    if (photoIndex == null) {
                        ClPhotoAddTile(
                            modifier = Modifier.weight(1f),
                            onClick = { onAdd?.invoke() },
                        )
                    } else {
                        ClPhotoTile(
                            url = urls[photoIndex],
                            modifier = Modifier.weight(1f),
                            removable = editable && onRemove != null,
                            onClick = { onClick?.invoke(photoIndex) },
                            onRemove = { onRemove?.invoke(photoIndex) },
                            removeContentDescription = "删除第 ${photoIndex + 1} 张",
                        )
                    }
                }
                repeat(PHOTO_COLUMNS - row.size) {
                    Box(modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun ClPhotoTile(
    url: String,
    modifier: Modifier = Modifier,
    removable: Boolean,
    onClick: () -> Unit,
    onRemove: () -> Unit,
    removeContentDescription: String,
) {
    Box(
        modifier = modifier
            .aspectRatio(1f)
            .clip(ClTheme.shapes.photo)
            .background(PhotoPlaceholder)
            .clickable(onClick = onClick),
    ) {
        AsyncImage(
            model = url,
            contentDescription = null,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize(),
        )

        if (removable) {
            Box(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(ClTheme.spacing.xs)
                    .size(ClTheme.size.iconM)
                    .clip(ClTheme.shapes.circle)
                    .background(ClTheme.colors.mask)
                    .clickable(onClick = onRemove),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    imageVector = ClIcons.close,
                    contentDescription = removeContentDescription,
                    tint = ClTheme.colors.surface,
                    modifier = Modifier.size(ClTheme.size.iconS),
                )
            }
        }
    }
}

@Composable
private fun ClPhotoAddTile(
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Box(
        modifier = modifier
            .aspectRatio(1f)
            .clip(ClTheme.shapes.photo)
            .background(ClTheme.colors.bgCream)
            .border(1.dp, ClTheme.colors.line, ClTheme.shapes.photo)
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(
            imageVector = ClIcons.add,
            contentDescription = "添加照片",
            tint = ClTheme.colors.muted,
            modifier = Modifier.size(ClTheme.size.iconM),
        )
    }
}
