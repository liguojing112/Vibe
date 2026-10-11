package com.qichuang.changliao.core.data.local

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 底部「消息」Tab 的未读总数（docs/01 第 3 节）。
 *
 * 未读数的**来源**是会话列表：T4.6 的消息层从本地会话表算出总数后调 [set]，
 * WebSocket 收到新消息时也调 [set]。这里只做一个应用级的落点，让底部导航不用等消息模块做完。
 */
@Singleton
class UnreadStore @Inject constructor() {

    private val _count = MutableStateFlow(0)

    val count: StateFlow<Int> = _count.asStateFlow()

    fun set(value: Int) {
        _count.value = if (value > 0) value else 0
    }

    /** 退出登录时清掉（未读是跟着账号走的）。 */
    fun clear() {
        _count.value = 0
    }
}
