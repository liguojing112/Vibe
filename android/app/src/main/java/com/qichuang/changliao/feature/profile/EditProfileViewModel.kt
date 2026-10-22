package com.qichuang.changliao.feature.profile

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.common.Validators
import com.qichuang.changliao.core.data.model.ProfileUpdate
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.ImageUploader
import com.qichuang.changliao.core.data.repository.UploadScene
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.time.LocalDate
import javax.inject.Inject

/**
 * 编辑资料页（docs/01 P14）。
 *
 * 两条关键规则：
 * 1. **只提交用户真的改过的字段** —— 服务端的 `sometimes` 是「没传就不改」，
 *    把没动过的头像/昵称也一起发过去，会平白多出一条待审记录、还得等审核。
 * 2. 头像、昵称、自我介绍提交后**进入审核**（服务端返回新的 `auditing`），页面据此打角标。
 */
@HiltViewModel
class EditProfileViewModel @Inject constructor(
    private val userRepository: UserRepository,
    private val appConfigRepository: AppConfigRepository,
    private val imageUploader: ImageUploader,
) : ViewModel() {

    private val _uiState = MutableStateFlow(EditProfileUiState())
    val uiState: StateFlow<EditProfileUiState> = _uiState.asStateFlow()

    /** 加载出来的原始资料，保存时用来算「哪些字段改了」。 */
    private var original: UserProfile? = null

    init {
        load()
        observeOptions()
    }

    fun load() {
        viewModelScope.launch {
            _uiState.update { it.copy(loading = true, loadError = null) }
            when (val result = userRepository.me()) {
                is ApiResult.Success -> {
                    original = result.data
                    applyProfile(result.data)
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(loading = false, loadError = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(loading = false, loadError = result.message)
                }
            }
        }
    }

    /** 自动保存：改完字段停 [AUTO_SAVE_DELAY_MS] 毫秒就提交，避免每敲一个字都发请求。 */
    private var autoSaveJob: Job? = null

    private fun scheduleAutoSave() {
        autoSaveJob?.cancel()
        autoSaveJob = viewModelScope.launch {
            delay(AUTO_SAVE_DELAY_MS)
            save(silent = true)
        }
    }

    // ---------- 表单字段 ----------

    fun onNicknameChange(value: String) {
        _uiState.update { it.copy(nickname = value, nicknameError = null, saved = false) }
        scheduleAutoSave()
    }

    fun onCityChange(value: String) {
        _uiState.update { it.copy(city = value, saved = false) }
        scheduleAutoSave()
    }

    fun onHeightChange(value: String) {
        // 只收数字，最多 3 位
        val digits = value.filter { it.isDigit() }.take(3)
        _uiState.update { it.copy(height = digits, heightError = null, saved = false) }
        scheduleAutoSave()
    }

    fun onJobChange(value: String) {
        _uiState.update { it.copy(job = value, saved = false) }
        scheduleAutoSave()
    }

    fun onEducationChange(value: String) {
        _uiState.update { it.copy(education = value, saved = false) }
        scheduleAutoSave()
    }

    fun onDatingPurposeChange(value: String) {
        _uiState.update { it.copy(datingPurpose = value, saved = false) }
        scheduleAutoSave()
    }

    fun onBioChange(value: String) {
        _uiState.update { it.copy(bio = value, bioError = null, saved = false) }
        scheduleAutoSave()
    }

    /** 兴趣标签（`ClChipGroup` 多选，回来的是名字集合，这里换回后台的标签 id）。 */
    fun onTagNamesChanged(names: Set<String>) {
        val ids = _uiState.value.tagOptions.filter { it.name in names }.map { it.id }
        _uiState.update {
            it.copy(
                tags = ids,
                tagsError = tagCountError(ids.size),
                saved = false,
            )
        }
        scheduleAutoSave()
    }

    /** 生日选择器选的日期（UTC 零点毫秒）。 */
    fun onBirthdaySelected(utcMillis: Long) {
        val date = TimeFormat.utcMillisToDate(utcMillis)
        _uiState.update { it.copy(birthday = date, birthdayError = null, saved = false) }
        scheduleAutoSave()
    }

    // ---------- 头像与相册 ----------

    fun onAvatarPicked(uri: Uri) = upload(uri, UploadScene.AVATAR) { url ->
        _uiState.update { it.copy(avatarUrl = url, saved = false) }
        scheduleAutoSave()
    }

    fun onPhotoPicked(uri: Uri) = upload(uri, UploadScene.PHOTO) { url ->
        _uiState.update { state ->
            if (state.photos.size >= MAX_PHOTOS) {
                state.copy(toast = "相册最多 $MAX_PHOTOS 张")
            } else {
                state.copy(photos = state.photos + url, saved = false)
            }
        }
        scheduleAutoSave()
    }

    fun onPhotoRemoved(index: Int) {
        _uiState.update { state ->
            if (index !in state.photos.indices) {
                state
            } else {
                state.copy(photos = state.photos.filterIndexed { i, _ -> i != index }, saved = false)
            }
        }
        scheduleAutoSave()
    }

    /** 长按拖动排序（`ClPhotoGrid` 的 `onMove`）。 */
    fun onPhotoMoved(from: Int, to: Int) {
        _uiState.update { state ->
            if (from !in state.photos.indices || to !in state.photos.indices || from == to) {
                state
            } else {
                val moved = state.photos.toMutableList()
                moved.add(to, moved.removeAt(from))
                state.copy(photos = moved, saved = false)
            }
        }
        scheduleAutoSave()
    }

    // ---------- 保存 ----------

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    fun save(silent: Boolean = false) {
        val state = _uiState.value
        val current = original ?: return
        if (state.submitting || state.uploading) {
            return
        }
        if (! validate(state)) {
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(submitting = true) }

            // 相册单独一个接口（整体提交）。改过才发，否则会平白多一条待审记录。
            var toast: String? = null
            val photosChanged = state.photos != current.photos.map { it.url }
            if (photosChanged) {
                when (val photoResult = userRepository.updatePhotos(state.photos)) {
                    is ApiResult.Success -> Unit
                    is ApiResult.BizError -> toast = photoResult.message
                    is ApiResult.NetworkError -> toast = photoResult.message
                }
            }

            val update = buildUpdate(state, current)
            val result = if (toast != null) null else userRepository.updateProfile(update)

            when (result) {
                null -> _uiState.update { it.copy(submitting = false, toast = toast) }

                is ApiResult.Success -> {
                    original = result.data
                    applyProfile(result.data, keepEditing = true)
                    _uiState.update {
                        it.copy(
                            submitting = false,
                            saved = true,
                            // 自动保存时不弹 toast（每改一个字段都弹会很吵），只更新顶栏状态
                            toast = if (silent) null else "已保存" + if (result.data.auditing.isEmpty()) "" else "，其中部分内容在审核中",
                        )
                    }
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(submitting = false, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(submitting = false, toast = result.message)
                }
            }
        }
    }

    // ---------- 内部 ----------

    private fun observeOptions() {
        viewModelScope.launch {
            appConfigRepository.config.collect { config ->
                if (config != null) {
                    _uiState.update { it.copy(cities = config.cities, tagOptions = config.tags) }
                }
            }
        }
    }

    private fun applyProfile(profile: UserProfile, keepEditing: Boolean = false) {
        val form = profile.toFormState()
        _uiState.update { state ->
            if (keepEditing) {
                // 保存成功后只刷新「审核中」等来自服务端的信息，别把用户正在编辑的内容覆盖掉
                state.copy(
                    auditing = form.auditing,
                    loading = false,
                    loadError = null,
                )
            } else {
                form.copy(cities = state.cities, tagOptions = state.tagOptions)
            }
        }
    }

    private fun upload(uri: Uri, scene: String, onSuccess: (String) -> Unit) {
        viewModelScope.launch {
            _uiState.update { it.copy(uploading = true) }
            when (val result = imageUploader.upload(uri, scene)) {
                is ApiResult.Success -> {
                    onSuccess(result.data)
                    _uiState.update { it.copy(uploading = false) }
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(uploading = false, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(uploading = false, toast = result.message)
                }
            }
        }
    }

    private fun validate(state: EditProfileUiState): Boolean {
        val nicknameError = if (Validators.isNickname(state.nickname)) null else Validators.NICKNAME_INVALID
        val birthdayError = when {
            state.birthday.isEmpty() -> null
            ! isAdult(state.birthday) -> "年龄必须满 $MIN_AGE 岁"
            else -> null
        }
        val heightError = when {
            state.height.isEmpty() -> null
            state.height.toIntOrNull()?.let { it in MIN_HEIGHT..MAX_HEIGHT } == true -> null
            else -> "身高看起来不太对"
        }
        val bioError = if (state.bio.length > MAX_BIO_LENGTH) "自我介绍最多 $MAX_BIO_LENGTH 字" else null
        val tagsError = tagCountError(state.tags.size)
        val jobError = if (state.job.length > MAX_JOB_LENGTH) "职业最多 $MAX_JOB_LENGTH 个字" else null

        _uiState.update {
            it.copy(
                nicknameError = nicknameError,
                birthdayError = birthdayError,
                heightError = heightError,
                bioError = bioError,
                tagsError = tagsError,
                toast = jobError ?: it.toast,
            )
        }

        return jobError == null && nicknameError == null && birthdayError == null &&
            heightError == null && bioError == null && tagsError == null
    }

    private fun tagCountError(count: Int): String? =
        if (count > MAX_TAGS) "最多选 $MAX_TAGS 个标签" else null

    /**
     * 只把「真的改了」的字段放进 [ProfileUpdate]，没改的留 null（服务端就不会动它）。
     */
    private fun buildUpdate(state: EditProfileUiState, current: UserProfile): ProfileUpdate = ProfileUpdate(
        nickname = state.nickname.takeIf { it != current.shownNickname() },
        avatar = state.avatarUrl.takeIf { it != current.shownAvatar() },
        bio = state.bio.takeIf { it != current.shownBio() },
        birthday = state.birthday.takeIf { it.isNotEmpty() && it != current.birthday },
        city = state.city.takeIf { it != current.city },
        height = state.height.toIntOrNull().takeIf { it != null && it != current.height },
        job = state.job.takeIf { it != current.job },
        education = state.education.takeIf { it != current.education },
        datingPurpose = state.datingPurpose.takeIf { it != current.datingPurpose },
        tags = state.tags.takeIf { it != current.tags },
    )

    private fun isAdult(date: String): Boolean {
        val birthday = runCatching { LocalDate.parse(date) }.getOrNull() ?: return false

        return ! birthday.isAfter(LocalDate.now().minusYears(MIN_AGE.toLong()))
    }

    companion object {
        /** 与服务端 `ProfileService` 的常量一致。 */
        const val MAX_PHOTOS = 9
        const val MAX_TAGS = 5
        const val MAX_BIO_LENGTH = 200
        const val MIN_AGE = 18

        private const val MAX_JOB_LENGTH = 32
        private const val MIN_HEIGHT = 120
        private const val MAX_HEIGHT = 250

        /** 自动保存的防抖间隔：输入停下来再存，避免每个字一次请求。 */
        const val AUTO_SAVE_DELAY_MS = 900L
    }
}
