package com.qichuang.changliao.feature.chat

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.ChatFee
import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.ChatPeer
import com.qichuang.changliao.core.data.model.CoinNotEnough
import com.qichuang.changliao.core.data.model.ChatSendResult
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ChatRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.clJson
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.decodeFromJsonElement
import javax.inject.Inject

/**
 * 私信聊天页（P06）状态管理。
 *
 * 三件事要按 `09` 第 1.3 节的要求做对：
 * 1. **发送成功后收费条要刷新**（用服务端返回的 `fee`，余额和下一条价格都以它为准，铁律 6）；
 * 2. **余额不足（20001）弹 P30，并且输入内容保留**；
 * 3. **发送失败可以重发，重发时 `client_msg_id` 不变**（服务端靠它幂等，换了会重复扣费）。
 */
@OptIn(ExperimentalCoroutinesApi::class)
@HiltViewModel
class ChatViewModel @Inject constructor(
    private val chatRepository: ChatRepository,
    private val userRepository: UserRepository,
    private val appConfigRepository: AppConfigRepository,
) : ViewModel() {

    private val peerId = MutableStateFlow(0L)

    private val input = MutableStateFlow("")

    private val loading = MutableStateFlow(true)

    private val error = MutableStateFlow<String?>(null)

    private val sending = MutableStateFlow(false)

    private val loadingMore = MutableStateFlow(false)

    private val hasMore = MutableStateFlow(true)

    private val coinNotEnough = MutableStateFlow<CoinNotEnough?>(null)

    private val feeRules = MutableStateFlow<List<String>?>(null)

    private val toast = MutableStateFlow<String?>(null)

    /** 进聊天页时 `/chat/conversations/with/{userId}` 拿到的对方资料与收费信息。 */
    private val entry = MutableStateFlow<Entry?>(null)

    /** 消息列表直接来自本地库：乐观气泡、推送、补拉都走这一条路进界面。 */
    private val messages: StateFlow<List<ChatMessage>> = peerId
        .flatMapLatest { id -> if (id <= 0) flowOf(emptyList()) else chatRepository.messages(id) }
        .stateIn(viewModelScope, SharingStarted.Eagerly, emptyList())

    private val extras = combine(
        userRepository.currentUser,
        appConfigRepository.config,
    ) { me, config ->
        Extras(
            isMale = me?.gender == Gender.MALE,
            myAvatar = me?.shownAvatar().orEmpty(),
            callEnabled = config?.callEnabled == true,
            maxLength = config?.chatMaxLength?.takeIf { it > 0 } ?: ChatUiState.DEFAULT_MAX_LENGTH,
        )
    }

    /**
     * `combine` 只有 2~5 个流的重载，超过 5 个会掉进 vararg 版本（参数变成一个数组，类型也推断不出来），
     * 所以分组拼：页面基本状态 / 发送状态 / 弹窗状态，最后再和 `entry` 合成。
     */
    private val pageState = combine(peerId, input, loading, error) { peerId, input, loading, error ->
        PageState(peerId, input, loading, error)
    }

    private val sendState = combine(sending, loadingMore, hasMore) { sending, loadingMore, hasMore ->
        SendState(sending, loadingMore, hasMore)
    }

    private val dialogs = combine(coinNotEnough, feeRules, toast) { coinNotEnough, feeRules, toast ->
        Dialogs(coinNotEnough, feeRules, toast)
    }

    private val flags = combine(pageState, sendState, dialogs, entry) { page, send, dialogs, entry ->
        Flags(
            peerId = page.peerId,
            input = page.input,
            loading = page.loading,
            error = page.error,
            sending = send.sending,
            loadingMore = send.loadingMore,
            hasMore = send.hasMore,
            coinNotEnough = dialogs.coinNotEnough,
            feeRules = dialogs.feeRules,
            toast = dialogs.toast,
            entry = entry,
        )
    }

    val uiState: StateFlow<ChatUiState> =
        combine(messages, flags, extras) { messages, flags, extras ->
            ChatUiState(
                peerId = flags.peerId,
                peer = flags.entry?.peer ?: ChatPeer(),
                messages = messages,
                fee = flags.entry?.fee,
                input = flags.input,
                maxLength = extras.maxLength,
                isMale = extras.isMale,
                myAvatar = extras.myAvatar,
                callEnabled = extras.callEnabled,
                loading = flags.loading,
                error = flags.error,
                sending = flags.sending,
                loadingMore = flags.loadingMore,
                hasMore = flags.hasMore,
                coinNotEnough = flags.coinNotEnough,
                feeRules = flags.feeRules,
                toast = flags.toast,
            )
        }.stateIn(
            scope = viewModelScope,
            // Eagerly：上游是本地库的 Flow，很便宜；WhileSubscribed 会让 uiState.value 停在初始值
            started = SharingStarted.Eagerly,
            initialValue = ChatUiState(),
        )

    /**
     * 进入聊天页：拿会话 id 与收费信息 → 补历史 → 标已读。
     *
     * 顺序有讲究：**先标已读**的话，如果拉历史失败，未读已经清了但消息没显示出来；
     * 所以先补历史（本地没有才拉）再标已读。
     */
    /**
     * 回到前台 / 回到这个页面时补拉一次最新消息。
     *
     * 推送只保证「在线那一刻」送达，切后台、锁屏、网络切换期间的推送会丢；
     * 不补拉的话对方（包括 AI 代聊）的回复就一直不出现。
     */
    fun refreshMessages() {
        val id = peerId.value
        if (id <= 0L) {
            return
        }
        viewModelScope.launch {
            chatRepository.loadInitialMessages(id, force = true)
        }
    }

    fun enter(peerId: Long) {
        if (this.peerId.value == peerId && entry.value != null) {
            return
        }
        this.peerId.value = peerId
        loading.value = true
        error.value = null

        viewModelScope.launch {
            when (val result = chatRepository.enterChat(peerId)) {
                is ApiResult.Success -> {
                    entry.value = Entry(peer = result.data.peer, fee = result.data.fee)
                    loading.value = false
                }

                is ApiResult.BizError -> {
                    error.value = result.message
                    loading.value = false
                    return@launch
                }

                is ApiResult.NetworkError -> {
                    error.value = result.message
                    loading.value = false
                    return@launch
                }
            }

            // force = true：每次进聊天页都从服务端补拉一次最新。
            // 只靠 WebSocket 推送不够 —— 断线 / 切后台 / 网络抖动那一瞬间的推送会丢，
            // 而本地已经有历史时不补拉的话，新消息（包括 AI 代聊的回复）就永远进不来。
            // 已有的消息会撞唯一键被跳过，不会重复。
            chatRepository.loadInitialMessages(peerId, force = true).onSuccessBoolean { hasMore.value = it }
            chatRepository.markRead(peerId)
        }
    }

    /**
     * 送完礼物之后刷新收费条。
     *
     * 礼物是另一条计费链路（`GiftBillingService`），它的返回里只有余额、没有「下一条私信报价」，
     * 而收费条的文案是服务端按余额现拼的 —— 所以这里重新问一次 `with/{userId}`。
     */
    fun refreshFee() {
        val id = peerId.value
        if (id <= 0) {
            return
        }
        viewModelScope.launch {
            when (val result = chatRepository.enterChat(id)) {
                is ApiResult.Success -> entry.value = Entry(peer = result.data.peer, fee = result.data.fee)
                is ApiResult.BizError -> Unit
                is ApiResult.NetworkError -> Unit
            }
        }
    }

    fun onInputChange(value: String) {
        input.value = value
    }

    /** 发送文字。**成功才清空输入**；余额不足时保留（P30 弹窗 + 输入还在，P06 的要求）。 */
    fun onSend() {
        val text = input.value.trim()
        if (text.isEmpty() || sending.value) {
            return
        }

        viewModelScope.launch {
            sending.value = true
            handleSendResult(chatRepository.send(peerId.value, text)) { input.value = "" }
            sending.value = false
        }
    }

    /** 选完图之后调：先落本地再上传再发送（docs/10 第 2.5 节）。 */
    fun onImagePicked(uri: Uri) {
        if (sending.value) {
            return
        }

        viewModelScope.launch {
            sending.value = true
            handleSendResult(chatRepository.sendImage(peerId.value, uri))
            sending.value = false
        }
    }

    /** 点失败气泡上的感叹号重发（`client_msg_id` 不变，服务端靠它幂等）。 */
    fun onResend(localId: Long) {
        if (sending.value) {
            return
        }

        viewModelScope.launch {
            sending.value = true
            handleSendResult(chatRepository.resend(localId))
            sending.value = false
        }
    }

    /** 往上拉：加载更早的历史。 */
    fun onLoadMore() {
        if (loadingMore.value || !hasMore.value) {
            return
        }

        viewModelScope.launch {
            loadingMore.value = true
            chatRepository.loadMoreMessages(peerId.value).onSuccessBoolean { hasMore.value = it }
            loadingMore.value = false
        }
    }

    fun onRetry() {
        entry.value = null
        enter(peerId.value)
    }

    /** 点收费条右侧的「收费说明」：条目由服务端按当前配置生成，客户端不拼文案。 */
    fun onOpenFeeRules() {
        viewModelScope.launch {
            when (val result = chatRepository.feeRules()) {
                is ApiResult.Success -> feeRules.value = result.data
                is ApiResult.BizError -> toast.value = result.message
                is ApiResult.NetworkError -> toast.value = result.message
            }
        }
    }

    fun onDismissFeeRules() {
        feeRules.value = null
    }

    /** P30 关掉：输入内容**不动**（用户充值回来还能直接发）。 */
    fun onDismissCoinNotEnough() {
        coinNotEnough.value = null
    }

    fun onToastShown() {
        toast.value = null
    }

    /**
     * 发送类操作的统一收尾。
     *
     * - 成功 → 刷新收费条（余额、下一条价格、收费条文案全用服务端返回的），再执行 [onSuccess]（比如清空输入）
     * - 20001 → 弹 P30（余额/需要的金币从 `data` 里取，服务端给的）
     * - 其他失败 → 提示；气泡那边已经是 `failed`，点感叹号可以重发
     */
    private suspend fun handleSendResult(
        result: ApiResult<ChatSendResult>,
        onSuccess: () -> Unit = {},
    ) {
        when (result) {
            is ApiResult.Success -> {
                result.data.fee?.let { refreshFee(it) }
                onSuccess()
            }

            is ApiResult.BizError -> {
                if (result.code == ChatRepository.CODE_COIN_NOT_ENOUGH) {
                    coinNotEnough.value = parseCoinNotEnough(result.data)
                    // 收费条也要跟着刷新：弹窗说「余额 2 金币，还差 3」，条上还写着「余额 145 金币」的话，
                    // 同一件事在同一个屏幕上两个说法（T9.1 走查发现的）。
                    // 余额和收费条文案都是服务端拼的（铁律 6），所以这里重问一次 `with/{userId}`。
                    refreshFee()
                } else {
                    toast.value = result.message
                }
            }

            is ApiResult.NetworkError -> toast.value = result.message
        }
    }

    /** 收费条只认服务端返回的 `fee`（铁律 6）。 */
    private fun refreshFee(fee: ChatFee) {
        val current = entry.value ?: return
        entry.value = current.copy(fee = fee)
    }

    /**
     * 20001 的 `data` 是 `{"balance": 4, "needed": 5}`（服务端 `CoinWalletService` 抛的）。
     *
     * 解析不出来也**必须把弹窗弹出来**（用当前收费条里的余额兜底）：静默什么都不做的话，
     * 用户会以为发出去了。所以这里返回非空值而不是 null。
     */
    private fun parseCoinNotEnough(data: JsonElement?): CoinNotEnough {
        val parsed = data?.let {
            runCatching { clJson.decodeFromJsonElement<CoinNotEnough>(it) }.getOrNull()
        }
        if (parsed != null) {
            return parsed
        }

        return CoinNotEnough(
            balance = entry.value?.fee?.balance ?: 0,
            needed = (entry.value?.fee?.nextPrice ?: 0).toLong(),
        )
    }

    private fun ApiResult<Boolean>.onSuccessBoolean(action: (Boolean) -> Unit) {
        if (this is ApiResult.Success) {
            action(data)
        }
    }

    private data class Entry(
        val peer: ChatPeer,
        val fee: ChatFee?,
    )

    private data class PageState(
        val peerId: Long,
        val input: String,
        val loading: Boolean,
        val error: String?,
    )

    private data class SendState(
        val sending: Boolean,
        val loadingMore: Boolean,
        val hasMore: Boolean,
    )

    private data class Dialogs(
        val coinNotEnough: CoinNotEnough?,
        val feeRules: List<String>?,
        val toast: String?,
    )

    private data class Extras(
        val isMale: Boolean,
        val myAvatar: String,
        val callEnabled: Boolean,
        val maxLength: Int,
    )

    private data class Flags(
        val peerId: Long,
        val input: String,
        val loading: Boolean,
        val error: String?,
        val sending: Boolean,
        val loadingMore: Boolean,
        val hasMore: Boolean,
        val coinNotEnough: CoinNotEnough?,
        val feeRules: List<String>?,
        val toast: String?,
        val entry: Entry?,
    )
}
