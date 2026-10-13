package com.qichuang.changliao.feature.settings

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.RelationPerson
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClMiniProfileRow
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 黑名单（P15 里的一个入口）。
 *
 * 拉黑之后两个人互相看不见，所以这里列出的是「我看不见、也看不见我的」那些人；
 * 每行右边一个「移除」把人放出来（服务端 `unblock`）。
 */
@Composable
fun BlockListScreen(
    state: BlockListUiState,
    onBack: () -> Unit,
    onRemove: (Long) -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "黑名单", onBack = onBack) },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            state.isEmpty -> ClEmpty(
                title = "黑名单是空的",
                desc = "在别人的资料页右上角「…」里可以拉黑",
            )

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        top = ClTheme.spacing.l,
                        bottom = ClTheme.spacing.xl,
                    ),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClCard(contentPadding = PaddingValues()) {
                    state.people.forEachIndexed { index, person ->
                        BlockRow(
                            person = person,
                            removing = state.removingId == person.id,
                            showDivider = index != state.people.lastIndex,
                            onRemove = { onRemove(person.id) },
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun BlockRow(
    person: RelationPerson,
    removing: Boolean,
    showDivider: Boolean,
    onRemove: () -> Unit,
) {
    ClMiniProfileRow(
        name = person.nickname,
        avatarUrl = person.avatar,
        desc = descOf(person),
        buttonText = if (removing) "移除中…" else "移除",
        showDivider = showDivider,
        // 黑名单里的人不该能点进主页（在 P05 那边也是互相不可见的）
        onClick = null,
        onAction = onRemove,
    )
}

/** 「上海 · 26 岁」；都没有就写「已拉黑」。 */
private fun descOf(person: RelationPerson): String {
    val parts = listOfNotNull(
        person.city.takeIf { it.isNotBlank() },
        person.age?.let { "$it 岁" },
    )

    return parts.joinToString(" · ").ifEmpty { "已拉黑" }
}
