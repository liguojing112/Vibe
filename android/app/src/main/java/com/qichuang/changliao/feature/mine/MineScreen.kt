package com.qichuang.changliao.feature.mine

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLevelBadge
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClMenuGroup
import com.qichuang.changliao.core.designsystem.component.ClMenuRow
import com.qichuang.changliao.core.designsystem.component.ClOutlineSmallButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClStatCell
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.ClVipBadge
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.navigation.Routes

/**
 * 我的（P04，设计图 04）。
 *
 * 结构：头部（头像 + 昵称 + VIP 标识 + ID + 资料完善度 + 等级徽章 + 编辑）
 * → 关系统计四格 → 钱包卡 → 菜单。
 *
 * **男女两套菜单**（`docs/01` P04）：男的是金币收支/会员，女的是我的收益/等级/实名（带状态）；
 * 通话记录那一项受 `feature.call_enabled` 控制。菜单在 [MineUiState.menus] 里拼好，页面只照着画。
 */
@Composable
fun MineScreen(
    state: MineUiState,
    onOpenRoute: (String) -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "我") },
    ) {
        when {
            state.loading -> ClLoading()

            state.isEmpty || state.error != null -> ClErrorRetry(
                message = state.error ?: "资料加载失败，请重试",
                onRetry = onRetry,
            )

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        bottom = ClTheme.spacing.xl,
                    ),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ProfileHeader(state = state, onEdit = { onOpenRoute(Routes.EDIT_PROFILE) })

                StatsRow(state = state, onOpenRoute = onOpenRoute)

                ClCard(
                    contentPadding = PaddingValues(),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    ClMenuGroup {
                        ClMenuRow(
                            icon = ClIcons.wallet,
                            label = "${state.walletText} · ${state.walletDesc}",
                            extra = state.walletAction,
                            showDivider = false,
                            onClick = { onOpenRoute(state.walletRoute) },
                        )
                    }
                }

                ClCard(contentPadding = PaddingValues(), modifier = Modifier.fillMaxWidth()) {
                    ClMenuGroup {
                        state.menus.forEachIndexed { index, menu ->
                            ClMenuRow(
                                icon = menu.icon,
                                label = menu.label,
                                extra = menu.extra,
                                showDivider = index != state.menus.lastIndex,
                                onClick = { onOpenRoute(menu.route) },
                            )
                        }
                    }
                }
            }
        }
    }
}

/** 头部：头像 + 昵称 + VIP 标识 + ID + 资料完善度 + 等级徽章。 */
@Composable
private fun ProfileHeader(state: MineUiState, onEdit: () -> Unit) {
    ClCard(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            ClAvatar(
                url = state.avatar,
                size = ClTheme.size.avatarL,
                vip = state.isVip,
                contentDescription = state.nickname,
            )

            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                ) {
                    Text(
                        text = state.nickname,
                        style = ClTheme.typography.cardTitle,
                        color = ClTheme.colors.ink,
                    )
                    if (state.isVip) {
                        ClVipBadge(active = true)
                    }
                }

                Text(
                    text = "${state.idText} · ${state.completenessText}",
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                )
            }

            Column(
                horizontalAlignment = Alignment.End,
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
            ) {
                ClOutlineSmallButton(text = "编辑", onClick = onEdit)
                ClLevelBadge(level = state.user?.level ?: 1)
                Text(
                    text = state.levelText,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                )
            }
        }
    }
}

/** 关系统计四格：我关注的 / 好友 / 关注我的 → P09 / 访客 → P08。 */
@Composable
private fun StatsRow(state: MineUiState, onOpenRoute: (String) -> Unit) {
    val stats = state.user?.stats ?: return

    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        // 「我关注的」和「好友」没有独立页面（`docs/01` P04 只给了「关注我的」和「访客」的去处）
        ClStatCell(number = stats.following.toString(), label = "我关注的", modifier = Modifier.weight(1f))
        ClStatCell(number = stats.friends.toString(), label = "好友", modifier = Modifier.weight(1f))
        ClStatCell(
            number = stats.fans.toString(),
            label = "关注我的",
            modifier = Modifier.weight(1f),
            onClick = { onOpenRoute(Routes.LIKED_ME) },
        )
        ClStatCell(
            number = stats.visitors.toString(),
            label = "访客",
            modifier = Modifier.weight(1f),
            onClick = { onOpenRoute(Routes.VISITORS) },
        )
    }
}
