package com.qichuang.changliao.feature.chat

import com.qichuang.changliao.core.data.model.ChatEntry
import com.qichuang.changliao.core.data.model.ChatFee
import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.ChatPeer
import com.qichuang.changliao.core.data.model.CoinNotEnough
import com.qichuang.changliao.core.data.model.ChatSendResult
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.MessageStatus
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ChatRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import com.qichuang.changliao.core.network.clJson
import io.mockk.coEvery
import io.mockk.coJustRun
import io.mockk.coVerify
import io.mockk.every
import io.mockk.mockk
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 聊天页（P06）状态管理。
 *
 * `09` 第 1.3 节点名要测的四条，前三条在这里，第四条（`client_msg_id` 重发不变）在
 * `ChatRepositoryTest`（那是仓库层的保证，页面只是把同一个 `localId` 交回给仓库）。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ChatViewModelTest {

    private val chatRepository = mockk<ChatRepository>()
    private val userRepository = mockk<UserRepository>()
    private val appConfigRepository = mockk<AppConfigRepository>()

    private val messages = MutableStateFlow<List<ChatMessage>>(emptyList())

    private val currentUser = MutableStateFlow<UserProfile?>(
        UserProfile(id = 7, gender = Gender.MALE, nickname = "我", avatar = "me.png"),
    )

    private val peerId = 102L

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())

        every { chatRepository.messages(peerId) } returns messages
        every { userRepository.currentUser } returns currentUser
        every { appConfigRepository.config } returns MutableStateFlow(null)

        coEvery { chatRepository.enterChat(peerId) } returns ApiResult.Success(
            ChatEntry(
                conversationId = 32,
                peer = ChatPeer(id = peerId, nickname = "小满", avatar = "peer.png"),
                unread = 1,
                fee = fee(nextPrice = 0, stripText = "还可免费发送 2 条 · 之后 5 币/条 · 余额 100 币"),
            ),
        )
        coEvery { chatRepository.loadInitialMessages(peerId, any()) } returns ApiResult.Success(true)
        coJustRun { chatRepository.markRead(peerId) }
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = ChatViewModel(chatRepository, userRepository, appConfigRepository).also {
        it.enter(peerId)
    }

    // ---------- 进入页面 ----------

    @Test
    fun `进页面拿对方资料和收费信息，并补历史、标已读`() {
        val vm = viewModel()

        val state = vm.uiState.value
        assertEquals("小满", state.peer.nickname)
        assertTrue(state.fee!!.stripText.startsWith("还可免费发送 2 条"))
        assertFalse(state.loading)

        coVerify { chatRepository.loadInitialMessages(peerId, any()) }
        coVerify { chatRepository.markRead(peerId) }
    }

    @Test
    fun `进页面失败时整页报错`() {
        coEvery { chatRepository.enterChat(peerId) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        val vm = viewModel()

        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)
    }

    // ---------- ① 发送成功后收费条刷新 ----------

    @Test
    fun `发送成功后收费条按服务端返回刷新，输入清空`() {
        val vm = viewModel()
        vm.onInputChange("你好呀")
        coEvery { chatRepository.send(any(), any(), any(), any()) } returns ApiResult.Success(
            ChatSendResult(
                message = message(clientMsgId = "c1", status = MessageStatus.SENT),
                fee = fee(nextPrice = 5, stripText = "私信 5 币/条 · 余额 95 币"),
                balance = 95,
            ),
        )

        vm.onSend()

        assertEquals("私信 5 币/条 · 余额 95 币", vm.uiState.value.fee!!.stripText)
        assertEquals(5, vm.uiState.value.fee!!.nextPrice)
        assertEquals("", vm.uiState.value.input)
    }

    // ---------- ② 余额不足弹 P30 且输入保留 ----------

    @Test
    fun `余额不足弹 P30，输入内容保留`() {
        val vm = viewModel()
        vm.onInputChange("这条发不出去")
        coEvery { chatRepository.send(any(), any(), any(), any()) } returns ApiResult.BizError(
            code = ChatRepository.CODE_COIN_NOT_ENOUGH,
            message = "金币余额不足",
            data = clJson.parseToJsonElement("""{"balance":4,"needed":5}"""),
        )

        vm.onSend()

        val info = vm.uiState.value.coinNotEnough
        assertEquals(4L, info!!.balance)
        assertEquals(5L, info.needed)
        assertEquals(1L, info.short)
        assertEquals("输入内容要留着，充值回来能直接发", "这条发不出去", vm.uiState.value.input)
    }

    @Test
    fun `关掉 P30 之后输入还在`() {
        val vm = viewModel()
        vm.onInputChange("保留我")
        coEvery { chatRepository.send(any(), any(), any(), any()) } returns ApiResult.BizError(
            code = ChatRepository.CODE_COIN_NOT_ENOUGH,
            message = "金币余额不足",
            data = clJson.parseToJsonElement("""{"balance":1,"needed":5}"""),
        )
        vm.onSend()

        vm.onDismissCoinNotEnough()

        assertNull(vm.uiState.value.coinNotEnough)
        assertEquals("保留我", vm.uiState.value.input)
    }

    // ---------- ③ 发送失败可重发 ----------

    @Test
    fun `发送失败提示，本地气泡是可重发的状态`() {
        val vm = viewModel()
        vm.onInputChange("发不出去")
        coEvery { chatRepository.send(any(), any(), any(), any()) } returns
            ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        vm.onSend()

        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
        // 气泡状态来自本地库，仓库那边会把它改成 failed（ChatRepositoryTest 里断言过）
        messages.value = listOf(message(clientMsgId = "c1", status = MessageStatus.FAILED))
        assertTrue(vm.uiState.value.messages.single().canResend)
    }

    @Test
    fun `重发把同一个 localId 交回仓库——client_msg_id 由仓库保持不变`() {
        messages.value = listOf(message(localId = 42, clientMsgId = "c-keep", status = MessageStatus.FAILED))
        val vm = viewModel()
        coEvery { chatRepository.resend(42) } returns ApiResult.Success(
            ChatSendResult(message = message(localId = 42, clientMsgId = "c-keep", status = MessageStatus.SENT)),
        )

        vm.onResend(42)

        coVerify { chatRepository.resend(42) }
    }

    // ---------- 收费说明 / 图片 ----------

    @Test
    fun `收费说明的条目来自服务端`() {
        val vm = viewModel()
        coEvery { chatRepository.feeRules() } returns ApiResult.Success(listOf("每对用户之间，前 3 条私信免费"))

        vm.onOpenFeeRules()
        assertEquals(listOf("每对用户之间，前 3 条私信免费"), vm.uiState.value.feeRules)

        vm.onDismissFeeRules()
        assertNull(vm.uiState.value.feeRules)
    }

    @Test
    fun `选图之后走发图片的流程`() {
        val vm = viewModel()
        val uri = mockk<android.net.Uri>()
        coEvery { chatRepository.sendImage(peerId, uri) } returns ApiResult.Success(
            ChatSendResult(message = message(clientMsgId = "img", type = ChatMessage.TYPE_IMAGE)),
        )

        vm.onImagePicked(uri)

        coVerify { chatRepository.sendImage(peerId, uri) }
    }

    // ---------- 文案 ----------

    @Test
    fun `男用户输入框显示单价，女用户只说「说点什么…」`() {
        val vm = viewModel()
        coEvery { chatRepository.send(any(), any(), any(), any()) } returns ApiResult.Success(
            ChatSendResult(
                message = message(clientMsgId = "c1"),
                fee = fee(nextPrice = 5, stripText = "私信 5 币/条 · 余额 95 币"),
            ),
        )
        vm.onInputChange("x")
        vm.onSend()

        assertEquals("发一句问候 · 5 币/条", vm.uiState.value.placeholder)

        currentUser.value = UserProfile(id = 7, gender = Gender.FEMALE, nickname = "我")
        assertEquals("说点什么…", vm.uiState.value.placeholder)
    }

    @Test
    fun `女用户没有收费条（fee 为 null）`() {
        coEvery { chatRepository.enterChat(peerId) } returns ApiResult.Success(
            ChatEntry(conversationId = 32, peer = ChatPeer(id = peerId, nickname = "阿宁"), fee = null),
        )
        currentUser.value = UserProfile(id = 7, gender = Gender.FEMALE, nickname = "我")

        val vm = viewModel()

        assertNull(vm.uiState.value.fee)
        assertFalse(vm.uiState.value.isMale)
    }

    // ---------- 工具 ----------

    private fun fee(nextPrice: Int, stripText: String) = ChatFee(
        nextPrice = nextPrice,
        freeReason = null,
        pairFreeLeft = 0,
        vipFreeLeft = 0,
        balance = 95,
        stripText = stripText,
    )

    private fun message(
        localId: Long = 1,
        clientMsgId: String = "c1",
        status: MessageStatus = MessageStatus.SENT,
        type: Int = ChatMessage.TYPE_TEXT,
    ) = ChatMessage(
        localId = localId,
        clientMsgId = clientMsgId,
        peerId = peerId,
        outgoing = true,
        type = type,
        content = "你好",
        status = status,
        createdAt = 1_791_888_877_000,
    )
}
