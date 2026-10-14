package com.qichuang.changliao.feature.message

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.navigation.Routes

/**
 * 消息页（P03）的路由层：把 ViewModel 和 Screen 接起来，导航动作由调用方注入。
 *
 * @param onOpenChat 进聊天页（P06）
 * @param onOpenRoute 打开非 Tab 页面（关注我的人 P09、访客 P08、系统消息 P10…）
 * @param onOpenDiscoverCity 「附近的人」：切到发现页并落到「同城」Tab
 */
@Composable
fun MessageRoute(
    onOpenChat: (Long) -> Unit,
    onOpenRoute: (String) -> Unit,
    onOpenDiscoverCity: () -> Unit,
    viewModel: MessageViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    MessageScreen(
        state = state,
        onOpenChat = onOpenChat,
        onOpenRoute = onOpenRoute,
        onOpenDiscoverCity = onOpenDiscoverCity,
        onToggleSearch = viewModel::onToggleSearch,
        onQueryChange = viewModel::onQueryChange,
        onToggleUnreadOnly = viewModel::onToggleUnreadOnly,
        onDelete = viewModel::onDelete,
        onRefresh = viewModel::onRefresh,
        onRetry = viewModel::onRetry,
    )
}

/** 页面里用到的路由（都用 [Routes] 的常量，不写裸字符串）。 */
internal object MessageRoutes {
    val discover: String = Routes.TAB_DISCOVER
    val likedMe: String = Routes.LIKED_ME
    val visitors: String = Routes.VISITORS
    val notices: String = Routes.NOTICES
    val noticeInteract: String = Routes.NOTICE_INTERACT
    val callRecords: String = Routes.CALL_RECORDS
}
