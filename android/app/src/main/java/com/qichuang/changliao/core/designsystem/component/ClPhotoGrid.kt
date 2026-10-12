package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectDragGesturesAfterLongPress
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.dp
import androidx.compose.ui.zIndex
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import kotlin.math.roundToInt

private const val PHOTO_COLUMNS = 3

/** 拖动中的格子放大一点，看得出"拿起来了"。 */
private const val DRAG_SCALE = 1.06f

/**
 * 长按拖动结束后，按「移动了几个格子」算落点下标。
 *
 * 三列网格：往右一格 +1，往下一格 +3。超出范围就贴到两端，不会越界。
 */
internal fun photoDragTarget(from: Int, columnDelta: Int, rowDelta: Int, lastIndex: Int): Int {
    if (lastIndex < 0) {
        return 0
    }

    return (from + columnDelta + rowDelta * PHOTO_COLUMNS).coerceIn(0, lastIndex)
}

/** 相册占位色，与头像统一（docs/03 第 6 节第 7 条）。 */
private val PhotoPlaceholder = Color(0xFFE8D8C7)

/**
 * 相册九宫格（docs/03 第 5.4 节，设计图 05、14）：三列方形。
 *
 * [editable] = true 时每张右上角有删除按钮，且没满 [maxCount] 时末尾多一个「+」添加格（P14 最多 9 张）。
 * 传了 [onMove] 则支持**长按拖动排序**（用长按触发，避免和页面的上下滚动打架）。
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
    onMove: ((from: Int, to: Int) -> Unit)? = null,
) {
    // 元素是照片下标；null 表示末尾的「+」添加格
    val tiles: List<Int?> = buildList {
        urls.indices.forEach { add(it) }
        if (editable && onAdd != null && urls.size < maxCount) {
            add(null)
        }
    }
    val draggable = editable && onMove != null && urls.size > 1

    BoxWithConstraints(modifier = modifier.fillMaxWidth()) {
        val gap = ClTheme.spacing.s
        // 单格边长由列数反推（三列 + 两条缝）
        val step = (maxWidth - gap * (PHOTO_COLUMNS - 1)) / PHOTO_COLUMNS + gap
        val stepPx = with(LocalDensity.current) { step.toPx() }

        var draggingIndex by remember { mutableStateOf<Int?>(null) }
        var dragOffset by remember { mutableStateOf(Offset.Zero) }

        fun finishDrag(apply: Boolean) {
            val from = draggingIndex ?: return
            draggingIndex = null
            if (! apply) {
                dragOffset = Offset.Zero
                return
            }
            val columnDelta = (dragOffset.x / stepPx).roundToInt()
            val rowDelta = (dragOffset.y / stepPx).roundToInt()
            dragOffset = Offset.Zero

            val to = photoDragTarget(from, columnDelta, rowDelta, urls.lastIndex)
            if (to != from) {
                onMove?.invoke(from, to)
            }
        }

        Column(verticalArrangement = Arrangement.spacedBy(gap)) {
            tiles.chunked(PHOTO_COLUMNS).forEachIndexed { rowIndex, row ->
                Row(horizontalArrangement = Arrangement.spacedBy(gap)) {
                    row.forEachIndexed { columnIndex, photoIndex ->
                        val absoluteIndex = rowIndex * PHOTO_COLUMNS + columnIndex
                        val dragging = draggingIndex == absoluteIndex

                        val tileModifier = Modifier
                            .weight(1f)
                            .zIndex(if (dragging) 1f else 0f)
                            .graphicsLayer {
                                if (dragging) {
                                    translationX = dragOffset.x
                                    translationY = dragOffset.y
                                    scaleX = DRAG_SCALE
                                    scaleY = DRAG_SCALE
                                }
                            }
                            .then(
                                if (draggable && photoIndex != null) {
                                    Modifier.pointerInput(absoluteIndex) {
                                        detectDragGesturesAfterLongPress(
                                            onDragStart = {
                                                draggingIndex = absoluteIndex
                                                dragOffset = Offset.Zero
                                            },
                                            onDrag = { change, amount ->
                                                change.consume()
                                                dragOffset += amount
                                            },
                                            onDragEnd = { finishDrag(apply = true) },
                                            onDragCancel = { finishDrag(apply = false) },
                                        )
                                    }
                                } else {
                                    Modifier
                                },
                            )

                        if (photoIndex == null) {
                            ClPhotoAddTile(
                                modifier = tileModifier,
                                onClick = { onAdd?.invoke() },
                            )
                        } else {
                            ClPhotoTile(
                                url = urls[photoIndex],
                                modifier = tileModifier,
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
