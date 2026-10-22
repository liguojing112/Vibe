package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.snapping.rememberSnapFlingBehavior
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import java.time.LocalDate
import java.time.YearMonth

/**
 * 日期选择弹窗（docs/03 第 5.5 节 `ClSelectField` 的「生日」用它弹出来）。
 *
 * **滚轮样式**（客户 2026-10-22 要求对齐目标产品）：三列 年 / 月 / 日，中间一行高亮，
 * 顶部「取消 · 标题 · 确定」，底部一句「* 对外仅显示年龄」。
 * 之前用的是 Material3 的日历弹窗，客户觉得和目标产品不一致。
 *
 * 时间轴仍然是 **UTC 零点毫秒**，转换走 `core/common/TimeFormat.utcMillisToDate()` /
 * `dateToUtcMillis()`，页面别自己算。
 *
 * @param yearRange 可选年份范围。生日这种要卡「必须满 18 岁」的场景传进来。
 */
@Composable
fun ClDatePickerDialog(
    title: String,
    onDismiss: () -> Unit,
    onConfirm: (utcMillis: Long) -> Unit,
    modifier: Modifier = Modifier,
    initialUtcMillis: Long? = null,
    yearRange: IntRange? = null,
) {
    val range = yearRange ?: DEFAULT_YEAR_RANGE
    val years = remember(range) { range.toList() }
    val months = remember { (1..12).toList() }

    val initialDate = remember(initialUtcMillis) {
        initialUtcMillis
            ?.let { TimeFormat.utcMillisToDate(it) }
            ?.let { runCatching { LocalDate.parse(it) }.getOrNull() }
            ?: LocalDate.now().minusYears(DEFAULT_AGE)
    }

    var year by remember { mutableIntStateOf(initialDate.year.coerceIn(range.first, range.last)) }
    var month by remember { mutableIntStateOf(initialDate.monthValue) }
    var day by remember { mutableIntStateOf(initialDate.dayOfMonth) }

    val daysInMonth = YearMonth.of(year, month).lengthOfMonth()
    // 从 1 月 31 日切到 2 月时，天数要收回来
    LaunchedEffect(daysInMonth) {
        if (day > daysInMonth) {
            day = daysInMonth
        }
    }
    val days = remember(daysInMonth) { (1..daysInMonth).toList() }

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false),
    ) {
        Column(
            modifier = modifier
                .fillMaxWidth(0.86f)
                .clip(ClTheme.shapes.sheet)
                .background(ClTheme.colors.surface),
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = "取消",
                    style = ClTheme.typography.body,
                    color = ClTheme.colors.muted,
                    modifier = Modifier
                        .clip(ClTheme.shapes.pill)
                        .clickable(onClick = onDismiss)
                        .padding(horizontal = ClTheme.spacing.s, vertical = ClTheme.spacing.xs),
                )
                Text(
                    text = title,
                    style = ClTheme.typography.cardTitle,
                    color = ClTheme.colors.ink,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    text = "确定",
                    style = ClTheme.typography.body,
                    color = ClTheme.colors.primaryText,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier
                        .clip(ClTheme.shapes.pill)
                        .clickable {
                            val date = LocalDate.of(year, month, day)
                            TimeFormat.dateToUtcMillis(date.toString())?.let(onConfirm)
                        }
                        .padding(horizontal = ClTheme.spacing.s, vertical = ClTheme.spacing.xs),
                )
            }

            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(WHEEL_HEIGHT),
            ) {
                Row(modifier = Modifier.fillMaxSize()) {
                    Wheel(
                        items = years.map { "${it}年" },
                        selectedIndex = years.indexOf(year).coerceAtLeast(0),
                        onSelected = { year = years[it] },
                        modifier = Modifier.weight(3f),
                    )
                    Wheel(
                        items = months.map { "${it}月" },
                        selectedIndex = month - 1,
                        onSelected = { month = months[it] },
                        modifier = Modifier.weight(2f),
                    )
                    Wheel(
                        items = days.map { "${it}日" },
                        selectedIndex = (day - 1).coerceIn(0, days.lastIndex),
                        onSelected = { day = days[it] },
                        modifier = Modifier.weight(2f),
                    )
                }

                // 中间选中行上下各一条细线
                Column(
                    modifier = Modifier
                        .align(Alignment.Center)
                        .fillMaxWidth()
                        .height(WHEEL_ITEM_HEIGHT),
                    verticalArrangement = Arrangement.SpaceBetween,
                ) {
                    WheelDivider()
                    WheelDivider()
                }
            }

            Text(
                text = "* 对外仅显示年龄",
                style = ClTheme.typography.tiny,
                color = ClTheme.colors.muted,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = ClTheme.spacing.l),
            )
        }
    }
}

@Composable
private fun WheelDivider() {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(1.dp)
            .background(ClTheme.colors.line),
    )
}

/**
 * 单列滚轮：滚动停下后把**中间那一项**作为选中值。
 *
 * `contentPadding` 上下各留半个容器高度，这样第一项和最后一项也能滚到正中间。
 */
@Composable
private fun Wheel(
    items: List<String>,
    selectedIndex: Int,
    onSelected: (Int) -> Unit,
    modifier: Modifier = Modifier,
) {
    val state = rememberLazyListState(initialFirstVisibleItemIndex = selectedIndex)
    val fling = rememberSnapFlingBehavior(lazyListState = state)
    val itemPx = with(LocalDensity.current) { WHEEL_ITEM_HEIGHT.toPx() }

    // 滚动停下 → 取中间项
    LaunchedEffect(state, items.size) {
        snapshotFlow {
            Triple(state.isScrollInProgress, state.firstVisibleItemIndex, state.firstVisibleItemScrollOffset)
        }.collect { (scrolling, index, offset) ->
            if (!scrolling) {
                val center = index + if (offset > itemPx / 2) 1 else 0
                if (center in items.indices && center != selectedIndex) {
                    onSelected(center)
                }
            }
        }
    }

    // 外部改了选中值（比如换月后 31 日没了）→ 滚回新位置
    LaunchedEffect(selectedIndex, items.size) {
        if (!state.isScrollInProgress && state.firstVisibleItemIndex != selectedIndex) {
            state.scrollToItem(selectedIndex.coerceIn(0, items.lastIndex.coerceAtLeast(0)))
        }
    }

    LazyColumn(
        state = state,
        flingBehavior = fling,
        contentPadding = PaddingValues(vertical = (WHEEL_HEIGHT - WHEEL_ITEM_HEIGHT) / 2),
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = modifier.fillMaxHeight(),
    ) {
        itemsIndexed(items) { index, label ->
            val selected = index == selectedIndex
            Box(
                modifier = Modifier
                    .height(WHEEL_ITEM_HEIGHT)
                    .fillMaxWidth(),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    text = label,
                    style = if (selected) ClTheme.typography.cardTitle else ClTheme.typography.body,
                    color = if (selected) ClTheme.colors.ink else ClTheme.colors.muted,
                )
            }
        }
    }
}

/** 滚轮可视高度（5 行）。 */
private val WHEEL_HEIGHT = 220.dp

/** 滚轮单行高度。 */
private val WHEEL_ITEM_HEIGHT = 44.dp

private val DEFAULT_YEAR_RANGE = 1900..2100

/** 没传初始生日时默认停在 20 岁。 */
private const val DEFAULT_AGE = 20L
