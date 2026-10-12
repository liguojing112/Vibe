package com.qichuang.changliao.feature.profile

import com.qichuang.changliao.core.data.model.AppTag
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.UserProfile

/**
 * 编辑资料页状态（docs/01 P14）。
 *
 * [auditing] 是服务端告诉我们的「哪些字段在审核中」——头像、昵称、自我介绍、相册会进审核，
 * 审核通过前别人看到的还是旧资料，所以页面上给这些字段打「审核中」角标。
 */
data class EditProfileUiState(
    val loading: Boolean = true,
    val loadError: String? = null,
    val gender: Int = Gender.UNKNOWN,
    val avatarUrl: String = "",
    val photos: List<String> = emptyList(),
    val nickname: String = "",
    val birthday: String = "",
    val city: String = "",
    val height: String = "",
    val job: String = "",
    val education: String = "",
    val datingPurpose: String = "",
    val bio: String = "",
    val tags: List<Long> = emptyList(),
    val auditing: Set<String> = emptySet(),
    val cities: List<String> = emptyList(),
    val tagOptions: List<AppTag> = emptyList(),
    val nicknameError: String? = null,
    val birthdayError: String? = null,
    val heightError: String? = null,
    val bioError: String? = null,
    val tagsError: String? = null,
    val uploading: Boolean = false,
    val submitting: Boolean = false,
    val toast: String? = null,
    val saved: Boolean = false,
) {
    val isFemale: Boolean get() = gender == Gender.FEMALE

    val canSave: Boolean get() = !loading && !submitting && !uploading

    fun isAuditing(field: String): Boolean = field in auditing
}

/** 弹选择器用的目标（城市 / 学历 / 交友期待走底部弹窗，生日走日期弹窗）。 */
enum class ProfilePicker {
    City,
    Education,
    DatingPurpose,
}

/** 交友期待（docs/01 P14，与服务端 `in:` 校验一致）。 */
val DATING_PURPOSE_OPTIONS = listOf("认真交友", "兴趣搭子", "长期关系")

/** 学历（docs/01 P14 只说「选择」，没给清单，先定这一套，见 PROGRESS Q0-18）。 */
val EDUCATION_OPTIONS = listOf("高中及以下", "大专", "本科", "硕士", "博士")

/** 页面加载出来的原始资料，保存时用它算出「哪些字段真的改了」。 */
internal fun UserProfile.toFormState() = EditProfileUiState(
    loading = false,
    gender = gender,
    avatarUrl = shownAvatar(),
    photos = photos.map { it.url },
    nickname = shownNickname(),
    birthday = birthday.orEmpty(),
    city = city,
    height = height?.toString().orEmpty(),
    job = job,
    education = education,
    datingPurpose = datingPurpose,
    bio = shownBio(),
    tags = tags,
    auditing = auditing.toSet(),
)
