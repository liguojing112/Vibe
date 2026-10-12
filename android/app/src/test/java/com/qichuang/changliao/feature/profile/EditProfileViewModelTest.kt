package com.qichuang.changliao.feature.profile

import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.AppTag
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.ProfileUpdate
import com.qichuang.changliao.core.data.model.UserPhoto
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ImageUploader
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.every
import io.mockk.mockk
import io.mockk.slot
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.time.LocalDate

/**
 * 编辑资料页（docs/01 P14）：校验、只提交改过的字段、审核状态回填。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class EditProfileViewModelTest {

    private val userRepository = mockk<UserRepository>()
    private val appConfigRepository = mockk<AppConfigRepository>()
    private val imageUploader = mockk<ImageUploader>()

    private val configFlow = MutableStateFlow<AppConfig?>(null)

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { appConfigRepository.config } returns configFlow
        configFlow.value = AppConfig(
            cities = listOf("上海", "杭州"),
            tags = listOf(AppTag(1, "旅行"), AppTag(2, "音乐"), AppTag(3, "美食")),
        )
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun profile(
        nickname: String = "小满",
        auditNickname: String? = null,
        photos: List<UserPhoto> = emptyList(),
    ) = UserProfile(
        id = 7,
        gender = Gender.FEMALE,
        nickname = nickname,
        avatar = "https://cdn/old.png",
        bio = "原来的简介",
        birthday = "2000-05-06",
        city = "上海",
        height = 165,
        job = "设计师",
        education = "本科",
        datingPurpose = "认真交友",
        tags = listOf(1L),
        photos = photos,
        pending = auditNickname?.let { mapOf(UserProfile.FIELD_NICKNAME to it) } ?: emptyMap(),
        auditing = auditNickname?.let { listOf(UserProfile.FIELD_NICKNAME) } ?: emptyList(),
    )

    private fun viewModel(current: UserProfile = profile()): EditProfileViewModel {
        coEvery { userRepository.me() } returns ApiResult.Success(current)
        return EditProfileViewModel(userRepository, appConfigRepository, imageUploader)
    }

    // ---------- 加载 ----------

    @Test
    fun `加载后填进表单 待审内容优先`() {
        val state = viewModel(profile(nickname = "小满", auditNickname = "新昵称")).uiState.value

        assertEquals(false, state.loading)
        // 自己看到的是待审的新昵称
        assertEquals("新昵称", state.nickname)
        assertEquals("上海", state.city)
        assertEquals("165", state.height)
        assertEquals(setOf(UserProfile.FIELD_NICKNAME), state.auditing)
        assertEquals(listOf("上海", "杭州"), state.cities)
        assertEquals(3, state.tagOptions.size)
        assertTrue(state.isFemale)
    }

    @Test
    fun `加载失败给出错误文案`() {
        coEvery { userRepository.me() } returns ApiResult.NetworkError("网络不太好，请稍后重试")

        val state = EditProfileViewModel(userRepository, appConfigRepository, imageUploader).uiState.value

        assertEquals(false, state.loading)
        assertEquals("网络不太好，请稍后重试", state.loadError)
    }

    // ---------- 校验 ----------

    @Test
    fun `昵称太短拦住不发请求`() {
        val vm = viewModel()
        vm.onNicknameChange("我")

        vm.save()

        assertEquals("昵称 2~12 个字", vm.uiState.value.nicknameError)
        coVerify(exactly = 0) { userRepository.updateProfile(any()) }
    }

    @Test
    fun `生日不满 18 岁拦住`() {
        val vm = viewModel()
        val tooYoung = LocalDate.now().minusYears(17).toEpochDay() * MILLIS_PER_DAY
        vm.onBirthdaySelected(tooYoung)

        vm.save()

        assertEquals("年龄必须满 18 岁", vm.uiState.value.birthdayError)
        coVerify(exactly = 0) { userRepository.updateProfile(any()) }
    }

    @Test
    fun `刚满 18 岁放行`() {
        val vm = viewModel()
        coEvery { userRepository.updateProfile(any()) } returns ApiResult.Success(profile())
        val justAdult = LocalDate.now().minusYears(18).toEpochDay() * MILLIS_PER_DAY
        vm.onBirthdaySelected(justAdult)

        vm.save()

        assertNull(vm.uiState.value.birthdayError)
    }

    @Test
    fun `自我介绍超 200 字拦住`() {
        val vm = viewModel()
        vm.onBioChange("字".repeat(201))

        vm.save()

        assertEquals("自我介绍最多 200 字", vm.uiState.value.bioError)
        coVerify(exactly = 0) { userRepository.updateProfile(any()) }
    }

    @Test
    fun `标签最多 5 个`() {
        configFlow.value = AppConfig(tags = (1L..6L).map { AppTag(it, "标签$it") })
        val vm = viewModel()
        vm.onTagNamesChanged((1..6).map { "标签$it" }.toSet())

        vm.save()

        assertEquals("最多选 5 个标签", vm.uiState.value.tagsError)
        coVerify(exactly = 0) { userRepository.updateProfile(any()) }
    }

    @Test
    fun `身高超出范围拦住`() {
        val vm = viewModel()
        vm.onHeightChange("300")

        vm.save()

        assertEquals("身高看起来不太对", vm.uiState.value.heightError)
        coVerify(exactly = 0) { userRepository.updateProfile(any()) }
    }

    // ---------- 保存：只提交改过的字段 ----------

    @Test
    fun `只把改过的昵称发出去 其他字段不带上`() {
        val vm = viewModel()
        val sent = slot<ProfileUpdate>()
        coEvery { userRepository.updateProfile(capture(sent)) } returns ApiResult.Success(profile(nickname = "新名字"))

        vm.onNicknameChange("新名字")
        vm.save()

        assertEquals("新名字", sent.captured.nickname)
        // 没动过的字段必须是 null（服务端 sometimes：没传就不改，也不会平白多一条待审）
        assertNull(sent.captured.avatar)
        assertNull(sent.captured.city)
        assertNull(sent.captured.bio)
        assertNull(sent.captured.height)
        assertNull(sent.captured.tags)
        // 相册没改，不该调相册接口
        coVerify(exactly = 0) { userRepository.updatePhotos(any()) }
    }

    @Test
    fun `改了城市和标签就带上它们`() {
        val vm = viewModel()
        val sent = slot<ProfileUpdate>()
        coEvery { userRepository.updateProfile(capture(sent)) } returns ApiResult.Success(profile())

        vm.onCityChange("杭州")
        vm.onTagNamesChanged(setOf("旅行", "音乐"))
        vm.save()

        assertEquals("杭州", sent.captured.city)
        assertEquals(listOf(1L, 2L), sent.captured.tags)
        assertNull(sent.captured.nickname)
    }

    @Test
    fun `昵称没改就不提交昵称`() {
        val vm = viewModel()
        val sent = slot<ProfileUpdate>()
        coEvery { userRepository.updateProfile(capture(sent)) } returns ApiResult.Success(profile())

        vm.onJobChange("产品经理")
        vm.save()

        assertNull(sent.captured.nickname)
        assertEquals("产品经理", sent.captured.job)
    }

    // ---------- 保存结果 ----------

    @Test
    fun `保存成功刷新审核状态并提示`() {
        val vm = viewModel()
        coEvery { userRepository.updateProfile(any()) } returns ApiResult.Success(
            profile(auditNickname = "新名字"),
        )

        vm.onNicknameChange("新名字")
        vm.save()

        val state = vm.uiState.value
        assertTrue(state.saved)
        assertEquals(false, state.submitting)
        assertEquals(setOf(UserProfile.FIELD_NICKNAME), state.auditing)
        assertEquals("已保存，其中部分内容在审核中", state.toast)
    }

    @Test
    fun `保存成功但没有审核内容时提示更简单`() {
        val vm = viewModel()
        coEvery { userRepository.updateProfile(any()) } returns ApiResult.Success(profile())

        vm.onCityChange("杭州")
        vm.save()

        assertEquals("已保存", vm.uiState.value.toast)
    }

    @Test
    fun `保存失败把服务端文案显示出来`() {
        val vm = viewModel()
        coEvery { userRepository.updateProfile(any()) } returns ApiResult.BizError(40002, "资料审核中，请勿重复提交")

        vm.onNicknameChange("新名字")
        vm.save()

        assertEquals("资料审核中，请勿重复提交", vm.uiState.value.toast)
        assertEquals(false, vm.uiState.value.saved)
    }

    @Test
    fun `相册改了会先提交相册`() {
        val vm = viewModel(profile(photos = listOf(UserPhoto(1, "https://cdn/a.png"))))
        coEvery { userRepository.updateProfile(any()) } returns ApiResult.Success(profile())
        coEvery { userRepository.updatePhotos(any()) } returns ApiResult.Success(emptyList())

        vm.onPhotoRemoved(0)
        vm.save()

        coVerify(exactly = 1) { userRepository.updatePhotos(emptyList()) }
    }

    // ---------- 相册编辑 ----------

    @Test
    fun `拖动排序把第 1 张挪到第 3 张`() {
        val vm = viewModel(
            profile(photos = listOf(
                UserPhoto(1, "a"), UserPhoto(2, "b"), UserPhoto(3, "c"),
            )),
        )

        vm.onPhotoMoved(from = 0, to = 2)

        assertEquals(listOf("b", "c", "a"), vm.uiState.value.photos)
    }

    @Test
    fun `越界的拖动不动`() {
        val vm = viewModel(profile(photos = listOf(UserPhoto(1, "a"))))

        vm.onPhotoMoved(from = 5, to = 0)

        assertEquals(listOf("a"), vm.uiState.value.photos)
    }

    @Test
    fun `删除相册某一张`() {
        val vm = viewModel(profile(photos = listOf(UserPhoto(1, "a"), UserPhoto(2, "b"))))

        vm.onPhotoRemoved(0)

        assertEquals(listOf("b"), vm.uiState.value.photos)
    }

    // ---------- 标签 ----------

    @Test
    fun `标签按后台 id 存`() {
        val vm = viewModel()

        vm.onTagNamesChanged(setOf("旅行", "美食"))

        assertEquals(listOf(1L, 3L), vm.uiState.value.tags)
    }

    @Test
    fun `不认识的名字不会变成标签`() {
        val vm = viewModel()

        vm.onTagNamesChanged(setOf("不存在的标签"))

        assertEquals(emptyList<Long>(), vm.uiState.value.tags)
    }

    private companion object {
        const val MILLIS_PER_DAY = 24L * 60 * 60 * 1000
    }
}
