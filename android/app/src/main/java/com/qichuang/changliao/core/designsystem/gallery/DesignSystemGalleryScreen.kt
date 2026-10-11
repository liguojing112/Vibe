package com.qichuang.changliao.core.designsystem.gallery

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.Alignment
import com.qichuang.changliao.core.designsystem.component.ClBottomBar
import com.qichuang.changliao.core.designsystem.component.ClBottomBarItem
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClIconButton
import com.qichuang.changliao.core.designsystem.component.ClOutlineSmallButton
import com.qichuang.changliao.core.designsystem.component.ClPageHeader
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSoftButton
import com.qichuang.changliao.core.designsystem.component.ClSoftButtonKind
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 组件预览页（docs/03 第 7 节）。
 *
 * **每新增一个公共组件，必须同时加到这一页**（scripts/check-ui.sh 会检查）。
 * 正式入口：设置页连续点击版本号 5 次（仅 debug，T6.6 接）；开发期在 debug 包里直接进。
 */
@Composable
fun DesignSystemGalleryScreen(onBack: (() -> Unit)? = null) {
    val toast = rememberClToast()
    var selectedTab by remember { mutableStateOf("home") }

    ClScaffold(
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "组件预览", onBack = onBack) },
        bottomBar = {
            ClBottomBar(
                items = listOf(
                    ClBottomBarItem("home", "遇见", ClIcons.home),
                    ClBottomBarItem("message", "消息", ClIcons.message),
                    ClBottomBarItem("mine", "我", ClIcons.user),
                ),
                selected = selectedTab,
                badges = mapOf("message" to 3),
                onSelect = { selectedTab = it },
            )
        },
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            ClPageHeader(
                title = "设计系统",
                subtitle = "所有页面只能用这里的组件和 token",
                trailing = {
                    ClIconButton(
                        icon = ClIcons.search,
                        contentDescription = "搜索",
                        onClick = {},
                    )
                },
            )

            // ---- 结构类 ----
            ClSectionHeader(title = "结构类", actionText = "查看全部", onAction = {})
            Text(
                text = "ClScaffold / ClTopBar / ClPageHeader / ClBottomBar / ClSectionHeader / ClFixedBottomArea",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.muted,
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
            )
            ClFixedBottomArea {
                ClPrimaryButton(text = "固定底部按钮区", onClick = {}, fillMaxWidth = false, modifier = Modifier.weight(1f))
            }

            // ---- 按钮类 ----
            ClSectionHeader(title = "按钮类")

            Column(
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                ClPrimaryButton(text = "主按钮", onClick = {})
                ClPrimaryButton(text = "主按钮", subText = "5 币/条", onClick = {})
                ClPrimaryButton(text = "主按钮", enabled = false, onClick = {})
                ClPrimaryButton(text = "主按钮", loading = true, onClick = {})

                ClSecondaryButton(text = "次按钮", onClick = {})
                ClSecondaryButton(text = "次按钮", enabled = false, onClick = {})

                ClOutlineSmallButton(text = "打招呼", onClick = {})

                Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s)) {
                    ClSoftButton(text = "私信", kind = ClSoftButtonKind.Yellow, leadingIcon = ClIcons.message, onClick = {})
                    ClSoftButton(text = "语音", kind = ClSoftButtonKind.Peach, leadingIcon = ClIcons.mic, onClick = {})
                    ClSoftButton(text = "视频", kind = ClSoftButtonKind.Gray, leadingIcon = ClIcons.video, onClick = {})
                }

                ClTextButton(text = "文字链接按钮", onClick = {})

                Row(
                    horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    ClIconButton(icon = ClIcons.search, contentDescription = "搜索", onClick = {})
                    ClOutlineSmallButton(text = "回关", onClick = {})
                }
            }

            // ---- 反馈类：Toast ----
            ClSectionHeader(title = "反馈类")
            Column(modifier = Modifier.padding(horizontal = ClTheme.spacing.l)) {
                ClOutlineSmallButton(
                    text = "点我看 Toast",
                    onClick = { toast.show("这是一条 Toast") },
                )
            }

            Column(modifier = Modifier.padding(ClTheme.spacing.xxxl)) {}
        }
    }
}
