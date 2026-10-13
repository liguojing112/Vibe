package com.qichuang.changliao.feature.message

import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.ChatConversation
import com.qichuang.changliao.core.data.model.ProfileStats
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ChatRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
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
 * 消息页（P03）：本地优先、本地搜索/筛选、四种状态、删除会话。
 *
 * 会话列表是本地库的 `Flow`，所以这里也用 `MutableStateFlow` 模拟：
 * 推一条进去，状态就该跟着变（WebSocket 来了新消息也是这条路）。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class MessageViewModelTest {

    private val chatRepository = mockk<ChatRepository>()
    private val userRepository = mockk<UserRepository>()
    private val appConfigRepository = mockk<AppConfigRepository>()

    private val conversations = MutableStateFlow<List<ChatConversation>>(emptyList())

    private val currentUser = MutableStateFlow<UserProfile?>(
        UserProfile(id = 7, nickname = "我"),
    )

    private val config = MutableStateFlow<AppConfig?>(AppConfig())

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { chatRepository.conversations() } returns conversations
        every { userRepository.currentUser } returns currentUser
        every { appConfigRepository.config } returns config
        coEvery { chatRepository.refreshConversations() } returns ApiResult.Success(Unit)
        coEvery { userRepository.me() } returns ApiResult.Success(currentUser.value!!)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = MessageViewModel(chatRepository, userRepository, appConfigRepository)

    // ---------- 列表 ----------

    @Test
    fun `本地有会话时进页面就有内容，不等网络`() {
        conversations.value = listOf(conversation(peerId = 1, name = "小满", unread = 2))

        val state = viewModel().uiState.value

        assertFalse(state.loading)
        assertEquals(listOf("小满"), state.visible.map { it.peerNickname })
    }

    @Test
    fun `推一条新会话进来，列表自己就更新了`() {
        val vm = viewModel()
        assertEquals(0, vm.uiState.value.visible.size)

        conversations.value = listOf(conversation(peerId = 5, name = "晚晴", unread = 1))

        assertEquals(listOf("晚晴"), vm.uiState.value.visible.map { it.peerNickname })
    }

    @Test
    fun `顶部横条最多放 5 个人`() {
        conversations.value = (1..7).map { conversation(peerId = it.toLong(), name = "用户$it") }

        val state = viewModel().uiState.value

        assertEquals(5, state.recentPeers.size)
        assertEquals(MessageUiState.RECENT_PEER_COUNT, state.recentPeers.size)
    }

    // ---------- 搜索 / 筛选 ----------

    @Test
    fun `按昵称搜索会话`() {
        conversations.value = listOf(
            conversation(peerId = 1, name = "小满"),
            conversation(peerId = 2, name = "晚晴"),
            conversation(peerId = 3, name = "小满月"),
        )
        val vm = viewModel()

        vm.onQueryChange("小满")

        assertEquals(listOf("小满", "小满月"), vm.uiState.value.visible.map { it.peerNickname })
        assertTrue(vm.uiState.value.filtering)
    }

    @Test
    fun `只看未读`() {
        conversations.value = listOf(
            conversation(peerId = 1, name = "小满", unread = 0),
            conversation(peerId = 2, name = "晚晴", unread = 3),
        )
        val vm = viewModel()

        vm.onToggleUnreadOnly()

        assertEquals(listOf("晚晴"), vm.uiState.value.visible.map { it.peerNickname })
    }

    @Test
    fun `收起搜索框时把关键词清掉`() {
        val vm = viewModel()
        vm.onToggleSearch()
        vm.onQueryChange("小满")
        assertTrue(vm.uiState.value.searching)

        vm.onToggleSearch()

        assertFalse(vm.uiState.value.searching)
        assertEquals("", vm.uiState.value.query)
    }

    // ---------- 四种状态 ----------

    @Test
    fun `没聊过和筛没了是两种说法`() {
        val empty = viewModel().uiState.value
        assertTrue(empty.isEmpty)
        assertEquals("还没有聊天", empty.emptyTitle)

        conversations.value = listOf(conversation(peerId = 1, name = "小满", unread = 0))
        val vm = viewModel()
        vm.onQueryChange("查不到的人")

        assertTrue(vm.uiState.value.isEmpty)
        assertEquals("没有符合条件的会话", vm.uiState.value.emptyTitle)
    }

    @Test
    fun `刷新失败但本地有数据时，不整页报错`() {
        conversations.value = listOf(conversation(peerId = 1, name = "小满"))
        coEvery { chatRepository.refreshConversations() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        val state = viewModel().uiState.value

        assertNull(state.error)
        assertEquals(1, state.visible.size)
    }

    @Test
    fun `本地一条都没有时刷新失败才整页报错`() {
        coEvery { chatRepository.refreshConversations() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        val state = viewModel().uiState.value

        assertEquals(NETWORK_ERROR_MESSAGE, state.error)
    }

    // ---------- 入口行的真数据 ----------

    @Test
    fun `粉丝和访客数据来自我的资料，没有就不摆假文案`() {
        currentUser.value = UserProfile(
            id = 7,
            nickname = "我",
            stats = ProfileStats(fans = 12, visitors = 30, unreadVisitors = 2),
        )

        val state = viewModel().uiState.value

        assertEquals("有 12 人喜欢了你", state.fanDesc)
        assertEquals("有 30 人看过你的主页", state.visitorDesc)
        assertEquals(2, state.unreadVisitors)
    }

    @Test
    fun `没有粉丝时不显示说明文案`() {
        currentUser.value = UserProfile(id = 7, nickname = "我")

        val state = viewModel().uiState.value

        assertNull(state.fanDesc)
        assertNull(state.visitorDesc)
    }

    @Test
    fun `语音视频开关关着时连线记录入口不显示`() {
        config.value = AppConfig(callEnabled = false)
        assertFalse(viewModel().uiState.value.callEnabled)

        config.value = AppConfig(callEnabled = true)
        assertTrue(viewModel().uiState.value.callEnabled)
    }

    // ---------- 删除 ----------

    @Test
    fun `删除会话调仓库`() {
        val vm = viewModel()

        vm.onDelete(peerId = 9)

        coVerify { chatRepository.deleteConversation(9) }
    }

    // ---------- 工具 ----------

    private fun conversation(
        peerId: Long,
        name: String,
        unread: Int = 0,
        lastMessageAt: Long = 1_791_888_877_000,
    ) = ChatConversation(
        peerId = peerId,
        peerNickname = name,
        peerAvatar = "",
        lastMessagePreview = "你好",
        lastMessageAt = lastMessageAt,
        unread = unread,
    )
}
