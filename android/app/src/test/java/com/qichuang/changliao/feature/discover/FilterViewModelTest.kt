package com.qichuang.changliao.feature.discover

import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.DiscoverRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.every
import io.mockk.mockk
import io.mockk.slot
import io.mockk.verify
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 筛选条件页（docs/01 P07）：城市单选、年龄、交友期待、认证状态、只看在线、重置与保存。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class FilterViewModelTest {

    private val discoverRepository = mockk<DiscoverRepository>(relaxUnitFun = true)
    private val userRepository = mockk<UserRepository>()
    private val appConfigRepository = mockk<AppConfigRepository>()

    private val currentUser = MutableStateFlow<UserProfile?>(null)
    private val config = MutableStateFlow<AppConfig?>(null)

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { userRepository.currentUser } returns currentUser
        every { appConfigRepository.config } returns config
        coEvery { discoverRepository.filter(any()) } returns DiscoverFilter()
        currentUser.value = UserProfile(id = 7, gender = Gender.MALE, nickname = "我")
        config.value = AppConfig(cities = listOf("上海", "杭州", "北京"))
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = FilterViewModel(discoverRepository, userRepository, appConfigRepository)

    private fun loadedViewModel(): FilterViewModel {
        val viewModel = viewModel()
        viewModel.load()

        return viewModel
    }

    @Test
    fun `加载城市选项和已保存的筛选`() {
        val saved = DiscoverFilter(city = "杭州", ageMin = 25, ageMax = 35, onlineOnly = true)
        coEvery { discoverRepository.filter(7) } returns saved

        val state = loadedViewModel().uiState.value

        assertFalse(state.loading)
        assertEquals(listOf("上海", "杭州", "北京"), state.cities)
        assertEquals(saved, state.draft)
        assertEquals(setOf("杭州"), state.selectedCities)
    }

    @Test
    fun `选城市`() {
        val viewModel = loadedViewModel()

        viewModel.onCitySelected("上海")

        assertEquals("上海", viewModel.uiState.value.draft.city)
    }

    @Test
    fun `选全部城市就是清掉城市条件`() {
        val viewModel = loadedViewModel()
        viewModel.onCitySelected("上海")

        viewModel.onCitySelected(FilterUiState.ALL_CITIES)

        assertEquals("", viewModel.uiState.value.draft.city)
        assertEquals(setOf(FilterUiState.ALL_CITIES), viewModel.uiState.value.selectedCities)
    }

    @Test
    fun `年龄滑块改的是最小最大`() {
        val viewModel = loadedViewModel()

        viewModel.onAgeRangeChange(22f..35f)

        assertEquals(22, viewModel.uiState.value.draft.ageMin)
        assertEquals(35, viewModel.uiState.value.draft.ageMax)
    }

    @Test
    fun `交友期待多选`() {
        val viewModel = loadedViewModel()

        viewModel.onPurposesChange(setOf("认真交友", "长期关系"))

        assertEquals(listOf("认真交友", "长期关系"), viewModel.uiState.value.draft.purposes)
        assertEquals(setOf("认真交友", "长期关系"), viewModel.uiState.value.selectedPurposes)
    }

    @Test
    fun `认证状态的两个选项各自对应一个开关`() {
        val viewModel = loadedViewModel()

        viewModel.onVerificationsChange(setOf(FilterUiState.VERIFIED_REAL_NAME))

        assertEquals(true, viewModel.uiState.value.draft.verified)
        assertEquals(false, viewModel.uiState.value.draft.complete)

        viewModel.onVerificationsChange(setOf(FilterUiState.VERIFIED_COMPLETE))

        assertEquals(false, viewModel.uiState.value.draft.verified)
        assertEquals(true, viewModel.uiState.value.draft.complete)
    }

    @Test
    fun `只看在线开关`() {
        val viewModel = loadedViewModel()

        viewModel.onOnlineOnlyChange(true)

        assertTrue(viewModel.uiState.value.draft.onlineOnly)
    }

    @Test
    fun `重置回到默认`() {
        val viewModel = loadedViewModel()
        viewModel.onCitySelected("上海")
        viewModel.onAgeRangeChange(25f..30f)
        viewModel.onOnlineOnlyChange(true)

        viewModel.onReset()

        assertEquals(DiscoverFilter(), viewModel.uiState.value.draft)
        assertFalse(viewModel.uiState.value.draft.isActive)
    }

    @Test
    fun `保存按当前用户存下来并可以返回`() {
        val viewModel = loadedViewModel()
        val saved = slot<DiscoverFilter>()
        viewModel.onCitySelected("杭州")
        viewModel.onOnlineOnlyChange(true)

        viewModel.onApply()

        verify { discoverRepository.saveFilter(7, capture(saved)) }
        assertEquals("杭州", saved.captured.city)
        assertTrue(saved.captured.onlineOnly)
        assertTrue(viewModel.uiState.value.applied)
    }

    @Test
    fun `拿不到我的资料时给出错误可重试`() {
        currentUser.value = null
        coEvery { userRepository.me() } returns ApiResult.NetworkError("网络不太好，请稍后重试")

        val viewModel = viewModel()
        viewModel.load()

        assertEquals("资料没加载出来，请重试", viewModel.uiState.value.loadError)
    }
}
