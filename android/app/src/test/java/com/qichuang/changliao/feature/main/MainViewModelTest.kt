package com.qichuang.changliao.feature.main

import com.qichuang.changliao.core.data.local.UnreadStore
import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ChatRepository
import com.qichuang.changliao.core.navigation.DiscoverTabIntent
import com.qichuang.changliao.core.navigation.Routes
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
 * 底部导航：`feature.call_enabled` 打开显示 4 个 Tab、关闭只显示 3 个（docs/01 第 3、4 节）。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class MainViewModelTest {

    private val configFlow = MutableStateFlow<AppConfig?>(null)
    private val repository = mockk<AppConfigRepository>()
    private val unreadStore = UnreadStore()
    private val chatRepository = mockk<ChatRepository>(relaxed = true)
    private val discoverTabIntent = DiscoverTabIntent()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { repository.config } returns configFlow
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun config(callEnabled: Boolean) = AppConfig(callEnabled = callEnabled)

    @Test
    fun `开关打开时有四个 Tab 且顺序固定`() {
        configFlow.value = config(callEnabled = true)

        val state = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent).uiState.value

        assertEquals(
            listOf(MainTab.DISCOVER, MainTab.MATCH, MainTab.MESSAGE, MainTab.MINE),
            state.tabs,
        )
    }

    @Test
    fun `开关关闭时只有三个 Tab 且没有速配`() {
        configFlow.value = config(callEnabled = false)

        val state = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent).uiState.value

        assertEquals(
            listOf(MainTab.DISCOVER, MainTab.MESSAGE, MainTab.MINE),
            state.tabs,
        )
        assertFalse(MainTab.MATCH in state.tabs)
    }

    @Test
    fun `配置还没拿到时按关闭处理`() {
        assertNull(configFlow.value)

        val state = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent).uiState.value

        assertEquals(3, state.tabs.size)
        assertEquals(MainTab.DISCOVER, state.selected)
    }

    @Test
    fun `能切到消息 Tab`() {
        configFlow.value = config(callEnabled = true)
        val viewModel = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent)

        viewModel.onTabSelected(Routes.TAB_MESSAGE)

        assertEquals(MainTab.MESSAGE, viewModel.uiState.value.selected)
    }

    @Test
    fun `配置刷新后多出速配 Tab 但不打断当前 Tab`() {
        configFlow.value = config(callEnabled = false)
        val viewModel = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent)
        viewModel.onTabSelected(Routes.TAB_MESSAGE)

        configFlow.value = config(callEnabled = true)

        assertEquals(4, viewModel.uiState.value.tabs.size)
        assertEquals(MainTab.MESSAGE, viewModel.uiState.value.selected)
    }

    @Test
    fun `开关关闭时点速配不会切过去`() {
        configFlow.value = config(callEnabled = false)
        val viewModel = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent)

        viewModel.onTabSelected(Routes.TAB_MATCH)

        assertEquals(MainTab.DISCOVER, viewModel.uiState.value.selected)
    }

    @Test
    fun `开关打开时点速配能切过去`() {
        configFlow.value = config(callEnabled = true)
        val viewModel = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent)

        viewModel.onTabSelected(Routes.TAB_MATCH)

        assertEquals(MainTab.MATCH, viewModel.uiState.value.selected)
        assertTrue(MainTab.MATCH in viewModel.uiState.value.tabs)
    }

    @Test
    fun `点不认识的路由不改变当前 Tab`() {
        configFlow.value = config(callEnabled = true)
        val viewModel = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent)

        viewModel.onTabSelected("user/12")

        assertEquals(MainTab.DISCOVER, viewModel.uiState.value.selected)
    }

    @Test
    fun `没有未读时不显示角标`() {
        val state = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent).uiState.value

        assertTrue(state.badges.isEmpty())
    }

    @Test
    fun `未读数挂在消息 Tab 上`() {
        unreadStore.set(7)

        val state = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent).uiState.value

        assertEquals(mapOf(MainTab.MESSAGE.route to 7), state.badges)
    }

    @Test
    fun `未读数变化角标跟着变`() {
        unreadStore.set(3)
        val viewModel = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent)

        unreadStore.set(12)

        assertEquals(12, viewModel.uiState.value.badges[MainTab.MESSAGE.route])
    }

    @Test
    fun `未读数清零后角标消失`() {
        unreadStore.set(5)
        val viewModel = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent)

        unreadStore.clear()

        assertTrue(viewModel.uiState.value.badges.isEmpty())
    }

    @Test
    fun `未读数超过 99 原样交给组件`() {
        unreadStore.set(120)

        val state = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent).uiState.value

        // 显示成「99+」是 ClBadgeDot 的事，ViewModel 不做截断
        assertEquals(120, state.badges[MainTab.MESSAGE.route])
    }

    @Test
    fun `负数未读当 0`() {
        unreadStore.set(-3)

        val state = MainViewModel(repository, unreadStore, chatRepository, discoverTabIntent).uiState.value

        assertTrue(state.badges.isEmpty())
    }
}
