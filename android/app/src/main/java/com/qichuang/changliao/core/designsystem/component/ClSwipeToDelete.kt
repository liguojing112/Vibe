package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.SwipeToDismissBox
import androidx.compose.material3.SwipeToDismissBoxValue
import androidx.compose.material3.Text
import androidx.compose.material3.rememberSwipeToDismissBoxState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 左滑删除（docs/01 P03 的会话列表）：右滑不动，**只允许从右往左滑**。
 *
 * 删除是**立即生效、无二次确认**的：会话只是「对自己隐藏」，对方再来消息就会重新出现
 * （docs/01 P06），所以没必要拦一道弹窗。
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClSwipeToDelete(
    onDelete: () -> Unit,
    modifier: Modifier = Modifier,
    label: String = "删除",
    enabled: Boolean = true,
    content: @Composable () -> Unit,
) {
    val state = rememberSwipeToDismissBoxState(
        confirmValueChange = { value ->
            if (value == SwipeToDismissBoxValue.EndToStart) {
                onDelete()
            }
            // 一律不留在「已滑走」的状态：删成功的行上层会从列表里移除，
            // 没删掉（比如连点两下）的也该滑回来，不能凭空消失
            false
        },
    )

    SwipeToDismissBox(
        state = state,
        modifier = modifier,
        enableDismissFromStartToEnd = false,
        enableDismissFromEndToStart = enabled,
        backgroundContent = {
            // 只写「删除」两个字，不配图标：docs/03 第 6 节第 6 条规定图标只能用 ClIcons 里有的那些，
            // 里面没有删除图标（原型也没用到），为了一个滑出背景去加图标不值当
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(ClTheme.colors.danger),
                contentAlignment = Alignment.CenterEnd,
            ) {
                Text(
                    text = label,
                    style = ClTheme.typography.bodySmall,
                    color = ClTheme.colors.onPrimary,
                    modifier = Modifier.padding(end = ClTheme.spacing.l),
                )
            }
        },
    ) {
        // 前景必须**不透明**：不然底下那层红色的「删除」会在没滑动的时候就透出来
        // （真机上就是这么发现这个问题的）
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(ClTheme.colors.surface),
        ) {
            content()
        }
    }
}
