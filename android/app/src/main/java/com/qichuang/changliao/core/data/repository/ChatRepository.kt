package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.local.UnreadStore
import com.qichuang.changliao.core.data.local.db.dao.ConversationDao
import com.qichuang.changliao.core.data.local.db.dao.MessageDao
import com.qichuang.changliao.core.data.local.db.dao.SyncStateDao
import com.qichuang.changliao.core.data.local.db.entity.ConversationEntity
import com.qichuang.changliao.core.data.local.db.entity.MessageEntity
import com.qichuang.changliao.core.data.local.db.entity.SyncStateEntity
import com.qichuang.changliao.core.data.model.ChatConversation
import com.qichuang.changliao.core.data.model.ChatEntry
import com.qichuang.changliao.core.data.model.ChatFee
import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.ChatPeer
import com.qichuang.changliao.core.data.model.ChatSendResult
import com.qichuang.changliao.core.data.model.ChatUnread
import com.qichuang.changliao.core.data.model.MessageStatus
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.ChatApi
import com.qichuang.changliao.core.network.dto.ChatEntryDto
import com.qichuang.changliao.core.network.dto.ChatFeeDto
import com.qichuang.changliao.core.network.dto.ConversationDto
import com.qichuang.changliao.core.network.dto.ConversationPageDto
import com.qichuang.changliao.core.network.dto.FeeRulesDto
import com.qichuang.changliao.core.network.dto.MessageDto
import com.qichuang.changliao.core.network.dto.MessagePageDto
import com.qichuang.changliao.core.network.dto.ReadRequestDto
import com.qichuang.changliao.core.network.dto.SendMessageDataDto
import com.qichuang.changliao.core.network.dto.SendMessageRequestDto
import com.qichuang.changliao.core.network.dto.SyncDataDto
import com.qichuang.changliao.core.network.dto.UnreadDto
import android.net.Uri
import com.qichuang.changliao.core.network.safeApiCall
import com.qichuang.changliao.core.network.safeApiCallUnit
import com.qichuang.changliao.core.network.ws.ChatFrame
import com.qichuang.changliao.core.network.ws.ChatSocket
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 聊天数据层（docs/10 第 1 节）。
 *
 * **本地优先**：界面全部从 Room 读（`Flow`），网络只负责「把数据补进本地库」。
 * 服务端不是展示数据源，它只做转发、计费与监管（客户 2026-10-11 确认）。
 *
 * 发送是**乐观 UI**（docs/10 第 2 节）：
 * ```
 * 先写本地一条 sending → 界面立刻出气泡 → 成功就地替换成服务端那条 / 失败改判 failed
 * ```
 * 重发时 **`client_msg_id` 保持不变** —— 服务端靠它幂等，换了会重复扣费。
 *
 * 余额和价格一律用服务端返回的数（铁律 6），本地只存不算。
 */
@Singleton
class ChatRepository @Inject constructor(
    private val chatApi: ChatApi,
    private val messageDao: MessageDao,
    private val conversationDao: ConversationDao,
    private val syncStateDao: SyncStateDao,
    private val socket: ChatSocket,
    private val imageUploader: ImageUploader,
    private val unreadStore: UnreadStore,
    private val userRepository: UserRepository,
) {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    /** 同一时间只跑一轮同步（docs/10 第 1.4 节）。 */
    private val syncMutex = Mutex()

    private var started = false

    private val _banned = MutableStateFlow<ChatFrame.AccountBanned?>(null)

    /**
     * 账号被封的信号（docs/08 T7.5）。
     *
     * 服务端封号时会推 `account.banned`；这一层只负责**收到并记下来**，
     * 「清登录态、回登录页」是上层的事（`SessionViewModel`）——
     * 数据层不该知道 token 存在哪。
     */
    val banned: StateFlow<ChatFrame.AccountBanned?> = _banned.asStateFlow()

    /**
     * 开始实时同步（幂等）。登录后在主界面调一次。
     *
     * 干四件事：把上次卡住的消息改判失败、把推送写进本地库、**连上就补拉一次**、
     * 把本地未读总数喂给底部角标。
     */
    fun start() {
        if (started) {
            return
        }
        started = true

        scope.launch { markStaleSending() }
        scope.launch { socket.frames.collect(::onFrame) }
        scope.launch {
            // 首次连上和断线重连要做的事一样：补拉断线期间收到的消息（docs/10 第 1.4 节）
            socket.connected.collect { connected -> if (connected) sync() }
        }
        scope.launch { unreadStream().collect { unreadStore.set(it) } }

        socket.start()
    }

    /** 停止（退出登录）。聊天记录留在本地库（docs/10 第 3 节规则 5），只是不再收推送。 */
    fun stop() {
        socket.stop()
        started = false
    }

    // ---------- 读（全部走本地库） ----------

    /**
     * 某个联系人的消息（升序）。页面直接渲染这个 Flow。
     *
     * 注意用的是 [ownerStream] 而不是 `ownerId()`：**当前用户可能在 Flow 被创建之后才加载好**
     * （主界面一出现就 `start()`，而 `/user/me` 还没回来），写死 ownerId 会查到一个空库。
     * 这个坑是在模拟器上真跑出来的：推送写进了库（ownerId=101），底部角标却一直是 0。
     */
    fun messages(peerId: Long): Flow<List<ChatMessage>> =
        ownerStream().flatMapLatest { owner ->
            messageDao.byPeer(owner, peerId).map { rows -> rows.map { it.toModel() } }
        }

    /** 会话列表（按最后消息时间倒序）。 */
    fun conversations(): Flow<List<ChatConversation>> =
        ownerStream().flatMapLatest { owner ->
            conversationDao.all(owner).map { rows -> rows.map { it.toModel() } }
        }

    /**
     * 底部角标的未读数：**跟着当前登录用户走**。
     *
     * 换账号 / 退出登录时 currentUser 一变就自动换数据源，不会把 A 的未读挂到 B 头上
     * （docs/10 第 3 节规则 4、5）。`internal` 是为了能直接单测这条链路。
     */
    @OptIn(ExperimentalCoroutinesApi::class)
    internal fun unreadStream(): Flow<Int> =
        ownerStream().flatMapLatest { owner -> conversationDao.unreadTotal(owner) }

    /** 当前登录用户 id 的变化流（没登录时是 0）。 */
    internal fun ownerStream(): Flow<Long> =
        userRepository.currentUser.map { it?.id ?: 0L }.distinctUntilChanged()

    // ---------- 发送（乐观 UI） ----------

    /**
     * 发一条消息：**先落本地再发网络**，所以界面立刻就有气泡。
     *
     * @param localMediaPath 图片消息的本地文件路径（重发时直接用这份，不再下载，docs/10 第 2.5 节）
     */
    suspend fun send(
        peerId: Long,
        content: String,
        type: Int = ChatMessage.TYPE_TEXT,
        localMediaPath: String? = null,
    ): ApiResult<ChatSendResult> {
        val owner = ownerId()
        val now = System.currentTimeMillis()
        val clientMsgId = newClientMsgId()

        val localId = messageDao.insert(
            MessageEntity(
                ownerId = owner,
                clientMsgId = clientMsgId,
                peerId = peerId,
                senderId = owner,
                outgoing = true,
                type = type,
                content = content,
                localMediaPath = localMediaPath,
                status = MessageStatus.SENDING.code,
                createdAt = now,
                updatedAt = now,
            ),
        )

        return deliver(localId, peerId, type, content, clientMsgId, now)
    }

    /**
     * 发一张图片：**先落本地再上传再发送**（docs/10 第 2.5 节）。
     *
     * 顺序是刻意的：
     * 1. 压缩 + 存一份到本地 → 立刻插一条 `sending` 的本地记录，**气泡马上就能看到图**（不等上传）；
     * 2. 传上去拿到地址；
     * 3. 再走正常的发送流程（同样的 `client_msg_id`、同样的重发逻辑）。
     *
     * 第 2 步失败时本地那条会变成 `failed`，**重发时用的是本地那份文件**，不用用户重新选图。
     */
    suspend fun sendImage(peerId: Long, uri: Uri): ApiResult<ChatSendResult> {
        val owner = ownerId()
        val now = System.currentTimeMillis()

        val localPath = when (val prepared = imageUploader.prepareChatImage(uri)) {
            is ApiResult.Success -> prepared.data
            is ApiResult.BizError -> return prepared
            is ApiResult.NetworkError -> return prepared
        }

        val clientMsgId = newClientMsgId()
        val localId = messageDao.insert(
            MessageEntity(
                ownerId = owner,
                clientMsgId = clientMsgId,
                peerId = peerId,
                senderId = owner,
                outgoing = true,
                type = ChatMessage.TYPE_IMAGE,
                // 内容先留空：等上传拿到地址再填，本地显示用 localMediaPath
                content = "",
                localMediaPath = localPath,
                status = MessageStatus.SENDING.code,
                createdAt = now,
                updatedAt = now,
            ),
        )

        val url = when (val uploaded = imageUploader.uploadChatImage(localPath)) {
            is ApiResult.Success -> uploaded.data
            is ApiResult.BizError -> {
                messageDao.updateStatus(localId, MessageStatus.FAILED.code, System.currentTimeMillis())
                return uploaded
            }

            is ApiResult.NetworkError -> {
                messageDao.updateStatus(localId, MessageStatus.FAILED.code, System.currentTimeMillis())
                return uploaded
            }
        }

        return deliver(localId, peerId, ChatMessage.TYPE_IMAGE, url, clientMsgId, now)
    }

    /**
     * 重发一条失败的消息。**`client_msg_id` 用的是本地那条的，不重新生成**
     * —— 服务端靠它做幂等，换了就会重复扣费（docs/10 第 2.4 节）。
     */
    suspend fun resend(localId: Long): ApiResult<ChatSendResult> {
        val row = messageDao.byLocalId(localId)
            ?: return ApiResult.BizError(0, "消息不存在")

        val now = System.currentTimeMillis()
        // 先回到「发送中」，界面立刻有反馈
        messageDao.updateStatus(localId, MessageStatus.SENDING.code, now)

        return deliver(row.localId, row.peerId, row.type, row.content, row.clientMsgId, now)
    }

    /**
     * 真正把消息发出去，并按结果改本地那条记录。
     *
     * 三种收场：
     * - 成功 → 就地替换（保住 localId，界面不闪）；
     * - **余额不足（20001）→ 把本地那条删掉**，页面把文字放回输入框（P06 要求）——
     *   气泡留着的话用户再点一次发送就重复发了；
     * - 其他失败 → 改判 `failed`，气泡上出重发入口。
     */
    private suspend fun deliver(
        localId: Long,
        peerId: Long,
        type: Int,
        content: String,
        clientMsgId: String,
        now: Long,
    ): ApiResult<ChatSendResult> {
        val result = safeApiCall<SendMessageDataDto> {
            chatApi.send(SendMessageRequestDto(receiverId = peerId, type = type, content = content, clientMsgId = clientMsgId))
        }

        return when (result) {
            is ApiResult.Success -> {
                val dto = result.data.message
                messageDao.markSent(
                    localId = localId,
                    serverId = dto.id,
                    conversationId = dto.conversationId,
                    content = dto.content,
                    status = MessageStatus.SENT.code,
                    priceCoins = dto.priceCoins,
                    createdAt = serverMillis(dto.createdAt, now),
                    updatedAt = System.currentTimeMillis(),
                )
                touchConversation(ownerId(), dto.toEntity(ownerId()))

                ApiResult.Success(
                    ChatSendResult(
                        message = dto.toModel(peerId = peerId, outgoing = true, fallbackMillis = now),
                        fee = result.data.fee?.toModel(),
                        balance = result.data.balance,
                    ),
                )
            }

            is ApiResult.BizError -> {
                if (result.code == CODE_COIN_NOT_ENOUGH) {
                    messageDao.delete(localId)
                } else {
                    messageDao.updateStatus(localId, MessageStatus.FAILED.code, System.currentTimeMillis())
                }
                result
            }

            is ApiResult.NetworkError -> {
                messageDao.updateStatus(localId, MessageStatus.FAILED.code, System.currentTimeMillis())
                result
            }
        }
    }

    // ---------- 补拉 ----------

    /**
     * 增量补拉（`/chat/sync`）：把断线期间**我收到的**消息补进本地库。
     *
     * 服务端只回「我收到的」，自己发出去的本地已经有了（乐观 UI 时就落了库），所以不用补。
     * 游标存在 `sync_state` 的全局那一行里，按登录用户分开。
     */
    suspend fun sync(): ApiResult<ChatUnread> = syncMutex.withLock {
        val owner = ownerId()
        var cursor = syncStateDao.lastSyncedMessageId(owner, SyncStateEntity.GLOBAL_CURSOR) ?: 0L
        var round = 0

        while (round++ < MAX_SYNC_ROUNDS) {
            when (val result = safeApiCall<SyncDataDto> { chatApi.sync(cursor) }) {
                is ApiResult.Success -> {
                    store(owner, result.data.list)
                    cursor = maxOf(cursor, result.data.maxId)
                    syncStateDao.upsert(
                        SyncStateEntity(
                            ownerId = owner,
                            conversationId = SyncStateEntity.GLOBAL_CURSOR,
                            lastSyncedMessageId = cursor,
                        ),
                    )
                    if (!result.data.hasMore) {
                        return@withLock ApiResult.Success(result.data.unread.toModel())
                    }
                }

                is ApiResult.BizError -> return@withLock result
                is ApiResult.NetworkError -> return@withLock result
            }
        }

        ApiResult.Success(ChatUnread())
    }

    /**
     * 首次进入某个会话时把服务端的历史消息拉下来（本地一条都没有的时候才拉）。
     *
     * 新装的 App 本地库是空的，而 `/chat/sync` 只给「我收到的」，自己发过的那半截要靠
     * `/chat/messages` 补（docs/05 第 4.5 节）。
     */
    suspend fun loadInitialMessages(peerId: Long, force: Boolean = false): ApiResult<Boolean> {
        val owner = ownerId()
        if (!force && messageDao.countByPeer(owner, peerId) > 0) {
            return ApiResult.Success(true)
        }
        val conversationId = conversationDao.byPeerId(owner, peerId)?.serverConversationId
            ?: return ApiResult.Success(false)

        return when (val result = safeApiCall<MessagePageDto> { chatApi.messages(conversationId) }) {
            is ApiResult.Success -> {
                store(owner, result.data.list)
                ApiResult.Success(result.data.hasMore)
            }

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
    }

    /**
     * 上拉加载更早的消息（`before_id` = 本地最老一条有服务端 id 的消息）。
     *
     * @return 还有没有更早的（false 时聊天页就不必再往上拉了）
     */
    suspend fun loadMoreMessages(peerId: Long): ApiResult<Boolean> {
        val owner = ownerId()
        val conversationId = conversationDao.byPeerId(owner, peerId)?.serverConversationId
            ?: return ApiResult.Success(false)
        val beforeId = messageDao.oldestServerId(owner, peerId) ?: return ApiResult.Success(false)

        return when (val result = safeApiCall<MessagePageDto> { chatApi.messages(conversationId, beforeId) }) {
            is ApiResult.Success -> {
                store(owner, result.data.list)
                ApiResult.Success(result.data.hasMore)
            }

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
    }

    /** 拉一遍会话列表（分页拉到底，最多 [MAX_CONVERSATION_PAGES] 页）。 */
    suspend fun refreshConversations(): ApiResult<Unit> {
        val owner = ownerId()
        var page = 1

        while (page <= MAX_CONVERSATION_PAGES) {
            when (val result = safeApiCall<ConversationPageDto> { chatApi.conversations(page) }) {
                is ApiResult.Success -> {
                    val rows = result.data.list.mapNotNull { it.toEntity(owner) }
                    if (rows.isNotEmpty()) {
                        conversationDao.upsertAll(rows)
                    }
                    if (!result.data.hasMore) {
                        return ApiResult.Success(Unit)
                    }
                    page++
                }

                is ApiResult.BizError -> return result
                is ApiResult.NetworkError -> return result
            }
        }

        return ApiResult.Success(Unit)
    }

    // ---------- 会话动作 ----------

    /** 进聊天页：拿会话 id（可能还没建）+ 收费信息 `fee`。 */
    suspend fun enterChat(peerId: Long): ApiResult<ChatEntry> =
        when (val result = safeApiCall<ChatEntryDto> { chatApi.with(peerId) }) {
            is ApiResult.Success -> {
                // 会话已经存在的话，把服务端会话 id 回填到本地（第一次发消息之前本地没有这个 id）
                val owner = ownerId()
                val existing = conversationDao.byPeerId(owner, peerId)
                if (existing != null && result.data.conversationId != null) {
                    conversationDao.upsert(existing.copy(serverConversationId = result.data.conversationId))
                }
                ApiResult.Success(
                    ChatEntry(
                        conversationId = result.data.conversationId,
                        peer = result.data.peer.toModel(),
                        unread = result.data.unread,
                        fee = result.data.fee?.toModel(),
                    ),
                )
            }

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /** 标记已读：本地先清（界面立刻不显示红点），再告诉服务端。 */
    suspend fun markRead(peerId: Long) {
        val owner = ownerId()
        conversationDao.clearUnread(owner, peerId)
        val conversationId = conversationDao.byPeerId(owner, peerId)?.serverConversationId ?: return

        safeApiCallUnit { chatApi.read(ReadRequestDto(conversationId)) }
    }

    /** 删除会话：本地先隐藏，再让服务端「只对我隐藏」。对方来新消息时会话会重新出现。 */
    suspend fun deleteConversation(peerId: Long) {
        val owner = ownerId()
        val conversationId = conversationDao.byPeerId(owner, peerId)?.serverConversationId
        conversationDao.hide(owner, peerId)

        if (conversationId != null) {
            safeApiCallUnit { chatApi.deleteConversation(conversationId) }
        }
    }

    /**
     * 未读总览（会话 / 访客 / 通知）。**会话未读以本地库为准**，这里用于校准和服务端对账。
     *
     * 会话未读**不**拿服务端这份覆盖（本地库才是权威），但通知未读只有服务端有，
     * 所以顺手把它校准进 [UnreadStore]。
     */
    suspend fun unread(): ApiResult<ChatUnread> =
        when (val result = safeApiCall<UnreadDto> { chatApi.unread() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel().also { unreadStore.setNotice(it.notice) })
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /** 校准 P03「系统消息」的红点（进消息页 / 下拉刷新时调）。 */
    suspend fun refreshNoticeUnread() {
        unread()
    }

    /** 收费说明弹窗的条目（服务端按当前配置生成，客户端不拼文案）。 */
    suspend fun feeRules(): ApiResult<List<String>> =
        when (val result = safeApiCall<FeeRulesDto> { chatApi.feeRules() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.items)
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    // ---------- 内部 ----------

    /**
     * 推送来了：写本地库 + 刷会话摘要。推送的钱是服务端算好的，本地只存不算。
     *
     * `internal` 是为了能直接单测这条链路（推送收集跑在 IO 线程上，测试里跑不稳）。
     */
    internal suspend fun onFrame(frame: ChatFrame) {
        when (frame) {
            is ChatFrame.MessageNew -> store(ownerId(), listOf(frame.message))
            // 未读数以本地库为准（服务端那份和本地是同一份数据的两种视角），
            // 所以这里不直接用推送里的数覆盖 LocalUnreadStore，只把它当作「该刷新了」的信号。
            is ChatFrame.UnreadUpdate -> Unit
            // 通知未读只有服务端有，本地没有对应数据，所以这里先 +1 顶上，
            // 打开消息页时再由 `/chat/unread` 校准（服务端发通知时不会顺带发 unread.update，
            // 见 `NoticeService::send`，所以不接这个事件的话红点永远不动）。
            ChatFrame.NoticeNew -> unreadStore.bumpNotice()
            // 封号：把这件事交给上层（`SessionViewModel` 收到就清登录态），
            // 顺便断开连接 —— 号都封了，挂着也没意义
            is ChatFrame.AccountBanned -> {
                _banned.value = frame
                socket.stop()
            }
            ChatFrame.Pong -> Unit
        }
    }

    /**
     * 把「我自己发出去的一条消息」补写进本地库。
     *
     * 送礼走这条路：服务端的推送**只发给收礼那一边**（`PushService::messageReceived`），
     * 而送礼用的 `/gift/send` 不走 [send] 的乐观落库，所以不在这里补一条的话，
     * 送礼的人回到聊天页永远看不到自己送出去的礼物气泡。
     */
    internal suspend fun storeSent(dto: MessageDto) {
        store(ownerId(), listOf(dto))
    }

    /**
     * 把服务端来的消息写进本地库，并顺带更新会话摘要与未读。
     *
     * 靠 `clientMsgId` 的唯一索引去重：**真正插进去的那几条才会计未读**，
     * 重复推来的（推送 + 补拉可能给同一条）不会把未读数越加越高。
     */
    private suspend fun store(owner: Long, messages: List<MessageDto>) {
        val inserted = mutableListOf<MessageEntity>()
        for (dto in messages) {
            if (dto.id <= 0 || dto.clientMsgId.isEmpty()) {
                continue
            }
            val entity = dto.toEntity(owner)
            if (messageDao.insert(entity) != -1L) {
                inserted += entity
            }
        }

        for (entity in inserted) {
            touchConversation(owner, entity)
        }
    }

    /** 更新/新建这条会话的摘要与未读（对方发来的才 +1）。 */
    private suspend fun touchConversation(owner: Long, message: MessageEntity) {
        val preview = previewOf(message)
        val existing = conversationDao.byPeerId(owner, message.peerId)

        if (existing == null) {
            conversationDao.upsert(
                ConversationEntity(
                    ownerId = owner,
                    peerId = message.peerId,
                    serverConversationId = message.conversationId,
                    lastMessagePreview = preview,
                    lastMessageAt = message.createdAt,
                    unread = if (message.outgoing) 0 else 1,
                    deleted = false,
                ),
            )
            return
        }

        conversationDao.upsert(
            existing.copy(
                serverConversationId = message.conversationId ?: existing.serverConversationId,
                lastMessagePreview = preview,
                lastMessageAt = maxOf(existing.lastMessageAt, message.createdAt),
                unread = if (message.outgoing) existing.unread else existing.unread + 1,
                // 新消息让「删过的」会话重新出现（docs/01 P06）
                deleted = false,
            ),
        )
    }

    /**
     * 启动时把上次卡在 `sending` 超过 [STALE_SENDING_MILLIS] 的消息改判失败（docs/10 第 1.3 节）。
     *
     * `internal` 而不是 private：单元测试要能直接调它断言这条规则
     * （`start()` 是发射即走，测不了确定性）。
     */
    internal suspend fun markStaleSending() {
        val now = System.currentTimeMillis()
        messageDao.markStale(
            from = MessageStatus.SENDING.code,
            to = MessageStatus.FAILED.code,
            before = now - STALE_SENDING_MILLIS,
            now = now,
        )
    }

    /**
     * 当前登录用户。聊天记录按用户隔离（库里有 `ownerId`）。
     * 没登录时是 0，此时不会有人调用聊天相关的东西。
     */
    private fun ownerId(): Long = userRepository.currentUser.value?.id ?: 0L

    companion object {
        /** 余额不足（docs/05 第 3 节），聊天页要按 P06 弹 P30 并保留输入。 */
        const val CODE_COIN_NOT_ENOUGH = 20001

        /** App 被杀时卡在「发送中」超过这么久就改判失败。 */
        const val STALE_SENDING_MILLIS = 60_000L

        private const val MAX_SYNC_ROUNDS = 10

        private const val MAX_CONVERSATION_PAGES = 5

        /**
         * 本地生成的 `client_msg_id`：**32 位十六进制**，和服务端 id 一个形状
         * （docs/10 第 8 节第 1 条：格式对不上会被存储层静默挡掉）。
         */
        fun newClientMsgId(): String = UUID.randomUUID().toString().replace("-", "")

        /** 服务端 `yyyy-MM-dd HH:mm:ss` → 毫秒；解析不了就用 [fallback]（本地时间）。 */
        fun serverMillis(text: String?, fallback: Long): Long =
            TimeFormat.parse(text.orEmpty())?.let { it * 1000 } ?: fallback

        /** 会话列表里显示的摘要。 */
        fun previewOf(message: MessageEntity): String = when (message.type) {
            ChatMessage.TYPE_IMAGE -> "[图片]"
            ChatMessage.TYPE_GIFT -> "[礼物]"
            else -> message.content
        }
    }
}
