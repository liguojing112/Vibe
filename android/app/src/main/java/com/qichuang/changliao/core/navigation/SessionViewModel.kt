package com.qichuang.changliao.core.navigation

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.local.LocalCache
import com.qichuang.changliao.core.data.local.UnreadStore
import com.qichuang.changliao.core.data.repository.ChatRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.TokenStore
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 登录态（docs/10 第 3 节规则 4、5）—— **Q0-20 就落在这里**。
 *
 * 以前只有「启动时按 token 决定起始页」：登录态中途没了（退出登录、注销、
 * 401 之后刷新也失败）就停在报错的页面上，用户只能杀进程重开。
 * 现在导航层盯着这个流，token 一空就清本地状态 + 回登录页。
 */
@HiltViewModel
class SessionViewModel @Inject constructor(
    private val tokenStore: TokenStore,
    private val userRepository: UserRepository,
    private val unreadStore: UnreadStore,
    private val localCache: LocalCache,
    private val chatRepository: ChatRepository,
) : ViewModel() {

    /** 有没有登录（有 access token 就算）。 */
    val loggedIn: StateFlow<Boolean> = tokenStore.accessTokenFlow()
        .map { it != null }
        .stateIn(
            scope = viewModelScope,
            // Eagerly：导航层一挂上就要拿到当前值，不能等有订阅者才算
            started = SharingStarted.Eagerly,
            initialValue = tokenStore.accessToken() != null,
        )

    init {
        // 被封号：服务端推 `account.banned`（docs/08 T7.5），这里把 token 清掉 ——
        // 导航层盯着 `loggedIn`，一空就清本地状态并回登录页（和 401 / 退出登录走同一条路）。
        // 封禁原因不用在这里显示：回到登录页再登录时，服务端会用 10004 把原因和到期时间一次说清。
        viewModelScope.launch {
            chatRepository.banned.collect { banned ->
                if (banned != null) {
                    tokenStore.clear()
                }
            }
        }
    }

    /**
     * 退出登录 / 注销 / 401 之后清本地状态。
     *
     * 四件事缺一不可（Q0-20 里列的）：
     * - 断开 WebSocket（不然还挂着上一个账号的连接）；
     * - 清内存里的当前用户（不然「我」页面还是上一个人的资料）；
     * - 清未读角标（未读是跟着账号走的）；
     * - 清内存缓存（`LocalCache` 按用户分键，切号时会串）。
     *
     * 磁盘上的东西**不清**：聊天记录按 `ownerId` 分键存在 Room 里（docs/10 第 3 节规则 5），
     * 换回这个账号还能看到自己的历史。
     */
    fun clearSession() {
        chatRepository.stop()
        userRepository.clearCurrentUser()
        unreadStore.clear()
        localCache.clearMemory()
    }
}
