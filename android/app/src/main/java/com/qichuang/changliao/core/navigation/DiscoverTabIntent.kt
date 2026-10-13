package com.qichuang.changliao.core.navigation

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 「切到发现页的某个 Tab」的一次性请求。
 *
 * 为什么要这么绕：P03 的「附近的人」入口要求**点进 P01 并切到「同城」Tab**（docs/01 P03），
 * 但发现页是底部导航里的一个 Tab，它的 Tab 状态在自己 ViewModel 里，
 * 从别处没法直接调。所以走一个应用级的单例：P03 写请求 → 切 Tab → 发现页读到请求切自己的 Tab。
 *
 * 传的是 `DiscoverTab.apiValue`（字符串）而不是枚举，是因为 `core` 不能依赖 `feature`；
 * 取值由 `feature/discover` 那边自己映射。
 */
@Singleton
class DiscoverTabIntent @Inject constructor() {

    private val _pending = MutableStateFlow<String?>(null)

    /** 待处理的请求；消费掉之后变回 null。 */
    val pending: StateFlow<String?> = _pending.asStateFlow()

    fun request(apiValue: String) {
        _pending.value = apiValue
    }

    fun consume() {
        _pending.value = null
    }
}
