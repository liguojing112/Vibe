package com.qichuang.changliao.core.network.ws

import com.qichuang.changliao.core.network.TokenStore
import com.qichuang.changliao.core.network.WsUrl
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import javax.inject.Inject
import javax.inject.Singleton

/**
 * WebSocket 连接（docs/02 第 4.4 节、docs/05 第 5 节）：`ws://{host}:9502/ws?token={access_token}`。
 *
 * **只收不发**：App 端只发心跳 `{"event":"ping"}`，发消息一律走 `POST /chat/send`。
 *
 * 职责边界：它只管「连接 / 心跳 / 重连 / 把收到的帧解析成 [ChatFrame]」，
 * **不认识数据库也不认识业务接口** —— 收到消息之后干什么由 [com.qichuang.changliao.core.data.repository.ChatRepository] 决定。
 *
 * 两条时间约定（docs/02 第 4.4 节）：
 * - 心跳每 [HEARTBEAT_SECONDS] 秒一次，服务端回 `pong`；
 * - 断线后按 1s、2s、4s… 重连，**封顶 [MAX_BACKOFF_SECONDS] 秒**，连上后退避重算。
 */
@Singleton
class ChatSocket @Inject constructor(
    private val client: OkHttpClient,
    private val tokenStore: TokenStore,
    @WsUrl private val url: String,
) {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    private val _frames = MutableSharedFlow<ChatFrame>(extraBufferCapacity = FRAME_BUFFER)

    /** 服务端推下来的帧。解析不了的帧不会出现在这里（[ChatFrame.parse] 已经丢掉了）。 */
    val frames: SharedFlow<ChatFrame> = _frames.asSharedFlow()

    private val _connected = MutableStateFlow(false)

    /**
     * 连接状态。上层监听它：**变成 true 就补拉一次**（首次连上补离线消息、重连补断线期间的消息，
     * 两种情况要做的事一样，所以不需要再分「第一次」和「重连」）。
     */
    val connected: StateFlow<Boolean> = _connected.asStateFlow()

    private var loop: Job? = null

    private var webSocket: WebSocket? = null

    private var heartbeat: Job? = null

    /** 连续失败次数，决定下一次退避多久；连上就清零。 */
    private var attempt = 0

    /** 开始连接（幂等）。没登录（没 token）时不连，等下次调用。 */
    fun start() {
        if (loop?.isActive == true) {
            return
        }
        loop = scope.launch {
            while (isActive) {
                if (tokenStore.accessToken().isNullOrBlank()) {
                    // 没登录就别空转，等一下再看
                    delay(backoffMillis(attempt++))
                    continue
                }

                val closed = CompletableDeferred<Unit>()
                if (!open(closed)) {
                    delay(backoffMillis(attempt++))
                    continue
                }
                closed.await()
                _connected.value = false
                delay(backoffMillis(attempt++))
            }
        }
    }

    /** 主动断开（退出登录 / 页面不再需要实时消息）。 */
    fun stop() {
        loop?.cancel()
        loop = null
        heartbeat?.cancel()
        heartbeat = null
        webSocket?.close(NORMAL_CLOSURE, null)
        webSocket = null
        _connected.value = false
        attempt = 0
    }

    /** 只是把心跳帧发出去（测试和日志用得到）。 */
    fun sendHeartbeat() {
        webSocket?.send(PING_FRAME)
    }

    /**
     * 建一条连接。
     *
     * @param closed 连接结束（正常关 / 失败）时完成，外层据此决定什么时候重连
     * @return 有没有真的建起来（建起来了才需要等 [closed]）
     */
    private fun open(closed: CompletableDeferred<Unit>): Boolean {
        val token = tokenStore.accessToken()
        if (token.isNullOrBlank()) {
            return false
        }

        val request = Request.Builder().url(appendToken(url, token)).build()

        val socket = client.newWebSocket(request, object : WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                attempt = 0
                _connected.value = true
                startHeartbeat()
            }

            override fun onMessage(webSocket: WebSocket, text: String) {
                ChatFrame.parse(text)?.let { _frames.tryEmit(it) }
            }

            override fun onClosing(webSocket: WebSocket, code: Int, reason: String) {
                webSocket.close(NORMAL_CLOSURE, null)
            }

            override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                finish(closed)
            }

            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                finish(closed)
            }
        })

        webSocket = socket
        return true
    }

    private fun finish(closed: CompletableDeferred<Unit>) {
        heartbeat?.cancel()
        heartbeat = null
        webSocket = null
        _connected.value = false
        closed.complete(Unit)
    }

    /** 心跳：每 30 秒发一次 ping；服务端会回 `pong`（docs/05 第 5 节）。 */
    private fun startHeartbeat() {
        heartbeat?.cancel()
        heartbeat = scope.launch {
            while (isActive) {
                delay(HEARTBEAT_SECONDS * 1000)
                webSocket?.send(PING_FRAME)
            }
        }
    }

    companion object {
        /** 心跳间隔（docs/02 第 4.4 节）。 */
        const val HEARTBEAT_SECONDS = 30L

        /** 重连退避上限（docs/02 第 4.4 节）。 */
        const val MAX_BACKOFF_SECONDS = 30L

        /** 收到的帧先攒着，等上层来收（避免回调线程阻塞）。 */
        private const val FRAME_BUFFER = 64

        private const val BASE_BACKOFF_MILLIS = 1000L

        private const val NORMAL_CLOSURE = 1000

        /** 心跳帧。 */
        const val PING_FRAME = """{"event":"ping"}"""

        /**
         * 第 [attempt] 次重连前要等多久（毫秒，attempt 从 0 起）：1000、2000、4000…**最大 30000**。
         *
         * 抽成纯函数，好直接断言这串时间（T4.5 的验收项之一）。用移位不用 `pow`：全程整数，不会有浮点误差。
         */
        fun backoffMillis(attempt: Int): Long {
            if (attempt <= 0) {
                return BASE_BACKOFF_MILLIS
            }
            // 先封顶再移位：1s << 5 = 32s 已经超过上限，再大就没意义了（也不会溢出）
            val shifted = BASE_BACKOFF_MILLIS shl attempt.coerceAtMost(5)

            return shifted.coerceAtMost(MAX_BACKOFF_SECONDS * 1000)
        }

        /** `ws://host:9502/ws` + `?token=xxx`（地址里已经有参数时用 `&`）。 */
        fun appendToken(url: String, token: String): String =
            if (url.contains('?')) "$url&token=$token" else "$url?token=$token"
    }
}
