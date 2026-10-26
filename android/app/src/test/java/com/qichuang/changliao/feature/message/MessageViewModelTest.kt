package com.qichuang.changliao.feature.message

import com.qichuang.changliao.core.data.local.UnreadStore
import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.ChatConversation
import com.qichuang.changliao.core.data.model.DiscoverUser
import com.qichuang.changliao.core.data.model.ProfileStats
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ChatRepository
import com.qichuang.changliao.core.data.repository.DiscoverRepository
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
    private val discoverRepository = mockk<DiscoverRepository>()
    private val appConfigRepository = mockk<AppConfigRepository>()
    private val unreadStore = UnreadStore()

    private val conversations = MutableStateFlow<List<ChatConversation>>(emptyList())

    private val testDispatcher = UnconfinedTestDispatcher()

    private val currentUser = MutableStateFlow<UserProfile?>(
        UserProfile(id = 7, nickname = "我"),
    )

    private val config = MutableStateFlow<AppConfig?>(AppConfig())

    @Before
    fun setUp() {
        Dispatchers.setMain(testDispatcher)
        every { chatRepository.conversations() } returns conversations
        every { userRepository.currentUser } returns currentUser
        every { appConfigRepository.config } returns config
        coEvery { chatRepository.refreshConversations() } returns ApiResult.Success(Unit)
        coEvery { chatRepository.refreshNoticeUnread() } returns Unit
        coEvery { userRepository.me() } returns ApiResult.Success(currentUser.value!!)
        coEvery { discoverRepository.search(any()) } returns emptyList()
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = MessageViewModel(
        chatRepository,
        userRepository,
        discoverRepository,
        appConfigRepository,
        unreadStore,
    )

    /** 消息页搜索不只过滤会话，也要能搜到还没聊过的人（走 `/discover/search`）。 */
    @Test
    fun `搜索能命中还没有会话的用户`() {
        coEvery { discoverRepository.search("宽宽") } returns listOf(
            DiscoverUser(id = 1304, nickname = "宽宽", city = "杭州", age = 26),
        )
        val vm = viewModel()

        vm.onQueryChange("宽宽")
        testDispatcher.scheduler.advanceUntilIdle()

        assertEquals(listOf("宽宽"), vm.uiState.value.userResults.map { it.nickname })
        assertFalse(vm.uiState.value.isEmpty)
    }

    /** 「系统消息」入口的红点跟着未读通知走（docs/01 P03）。 */
    @Test
    fun `系统消息红点跟着通知未读走`() {
        val vm = viewModel()
        assertEquals(0, vm.uiState.value.unreadNotices)

        unreadStore.setNotice(3)

        assertEquals(3, vm.uiState.value.unreadNotices)
    }

    @Test
    fun `进页面会校准一次通知未读`() {
        viewModel()

        coVerify(exactly = 1) { chatRepository.refreshNoticeUnread() }
    }

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

    /**
     * 从最近访客页返回时：服务端已经把未读清零，但消息页拿的是 `/user/me` 的旧 stats。
     * 回到消息页必须重新拉一次资料，角标才能消失。
     */
    @Test
    fun `回到消息页刷新资料后访客未读角标消失`() {
        currentUser.value = UserProfile(
            id = 7,
            nickname = "我",
            stats = ProfileStats(visitors = 30, unreadVisitors = 2),
        )
        coEvery { userRepository.me() } answers {
            val fresh = UserProfile(
                id = 7,
                nickname = "我",
                stats = ProfileStats(visitors = 30, unreadVisitors = 0),
            )
            currentUser.value = fresh
            ApiResult.Success(fresh)
        }
        val vm = viewModel()
        assertEquals(2, vm.uiState.value.unreadVisitors)

        vm.onResume()

        assertEquals(0, vm.uiState.value.unreadVisitors)
        coVerify { userRepository.me() }
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
