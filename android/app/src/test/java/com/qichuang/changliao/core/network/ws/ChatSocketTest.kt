package com.qichuang.changliao.core.network.ws

import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * 连接的两条时间约定和地址拼装（T4.5 / docs/02 第 4.4 节）。
 *
 * **验收项**：重连退避时间序列。这里直接断言那串时间，不依赖真实网络和真实时钟。
 */
class ChatSocketTest {

    @Test
    fun `重连退避是 1s 2s 4s 一路翻倍，封顶 30s`() {
        val sequence = (0..7).map { ChatSocket.backoffMillis(it) }

        assertEquals(
            listOf(1_000L, 2_000L, 4_000L, 8_000L, 16_000L, 30_000L, 30_000L, 30_000L),
            sequence,
        )
    }

    @Test
    fun `退避不会因为次数太大而溢出或变成 0`() {
        assertEquals(30_000L, ChatSocket.backoffMillis(Int.MAX_VALUE))
        assertEquals(30_000L, ChatSocket.backoffMillis(1000))
    }

    @Test
    fun `第一次重连也是 1 秒（不跳过第一次退避）`() {
        assertEquals(1_000L, ChatSocket.backoffMillis(0))
        assertEquals(1_000L, ChatSocket.backoffMillis(-3))
    }

    @Test
    fun `token 拼在 query 上，已有参数时用 and`() {
        assertEquals("ws://h:9502/ws?token=abc", ChatSocket.appendToken("ws://h:9502/ws", "abc"))
        assertEquals("ws://h:9502/ws?v=1&token=abc", ChatSocket.appendToken("ws://h:9502/ws?v=1", "abc"))
    }

    @Test
    fun `心跳帧就是 docs 第 5 节约定的那一个`() {
        assertEquals("""{"event":"ping"}""", ChatSocket.PING_FRAME)
        assertEquals(30L, ChatSocket.HEARTBEAT_SECONDS)
        assertEquals(30L, ChatSocket.MAX_BACKOFF_SECONDS)
    }
}
