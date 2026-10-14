package com.qichuang.changliao.core.data.local

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 未读数的应用级落点（docs/01 第 3 节「消息」Tab、P03）。
 *
 * - [count]：底部「消息」Tab 的角标，来源是**会话列表**（T4.6 的消息层从本地会话表算出总数后调 [set]，
 *   WebSocket 收到新消息时也调 [set]）。只算会话，不含通知 —— 通知的红点是 [notice]。
 * - [notice]：P03「系统消息」入口的红点。来源是 `/chat/unread` 的 `notice`，
 *   推送 `notice.new` 到达时先 +1（服务端发通知时不会顺带发 `unread.update`，
 *   见 `NoticeService::send`），下次 [setNotice] 再校准。
 */
@Singleton
class UnreadStore @Inject constructor() {

    private val _count = MutableStateFlow(0)

    val count: StateFlow<Int> = _count.asStateFlow()

    private val _notice = MutableStateFlow(0)

    val notice: StateFlow<Int> = _notice.asStateFlow()

    fun set(value: Int) {
        _count.value = if (value > 0) value else 0
    }

    fun setNotice(value: Int) {
        _notice.value = if (value > 0) value else 0
    }

    /** 收到一条新通知（推送）：本地先加，别等下一次校准。 */
    fun bumpNotice() {
        setNotice(_notice.value + 1)
    }

    /** 退出登录时清掉（未读是跟着账号走的）。 */
    fun clear() {
        _count.value = 0
        _notice.value = 0
    }
}
