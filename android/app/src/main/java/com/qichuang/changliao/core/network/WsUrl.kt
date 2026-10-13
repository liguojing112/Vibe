package com.qichuang.changliao.core.network

import javax.inject.Qualifier

/**
 * WebSocket 地址（来自 BuildConfig，docs/02 第 4.4 节）：`ws://{host}:9502/ws`。
 *
 * 和 [BaseUrl] 一样可以构建时覆盖（`-PwsBaseUrl=...`），因为模拟器和真机都连不到 `10.0.2.2`
 * 时，HTTP 走了 `adb reverse`，WS 也得跟着走同一条路（见 PROGRESS 的「模拟器连后端」）。
 */
@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class WsUrl
