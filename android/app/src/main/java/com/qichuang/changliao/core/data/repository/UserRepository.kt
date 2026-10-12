package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.PhotoListResult
import com.qichuang.changliao.core.data.model.ProfileUpdate
import com.qichuang.changliao.core.data.model.UserPhoto
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.UserApi
import com.qichuang.changliao.core.network.dto.UpdatePhotosRequestDto
import com.qichuang.changliao.core.network.dto.UpdateProfileRequestDto
import com.qichuang.changliao.core.network.dto.UpdateSettingsRequestDto
import com.qichuang.changliao.core.network.safeApiCall
import com.qichuang.changliao.core.network.safeApiCallUnit
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 用户资料（docs/05 第 4.2 节）。
 *
 * [ProfileUpdate] 里为 null 的字段不会发给服务端（`clJson` 的 `explicitNulls = false`），
 * 所以「用户没动过的字段」不会被误送去审核。
 */
@Singleton
class UserRepository @Inject constructor(
    private val userApi: UserApi,
) {

    private val _currentUser = MutableStateFlow<UserProfile?>(null)

    /**
     * 「我」的资料缓存：好几个页面都要用（P01 顶部性别和余额、P07 的筛选按用户存、P04 我的）。
     * 别让每个页面各请求一次 `/user/me`。
     */
    val currentUser: StateFlow<UserProfile?> = _currentUser.asStateFlow()

    suspend fun me(): ApiResult<UserProfile> =
        safeApiCall<UserProfile> { userApi.me() }.also { result ->
            if (result is ApiResult.Success) {
                _currentUser.value = result.data
            }
        }

    /** 退出登录 / 切号时清掉（在 T6 的退出流程里调，别让上一个账号的资料留在内存里）。 */
    fun clearCurrentUser() {
        _currentUser.value = null
    }

    suspend fun updateProfile(update: ProfileUpdate): ApiResult<UserProfile> =
        safeApiCall<UserProfile> {
            userApi.updateProfile(
                UpdateProfileRequestDto(
                    nickname = update.nickname,
                    avatar = update.avatar,
                    bio = update.bio,
                    birthday = update.birthday,
                    city = update.city,
                    height = update.height,
                    job = update.job,
                    education = update.education,
                    datingPurpose = update.datingPurpose,
                    tags = update.tags,
                ),
            )
        }.also { result ->
            // 改完资料顺手把「我」的缓存刷新，别让别的页面还拿着旧的
            if (result is ApiResult.Success) {
                _currentUser.value = result.data
            }
        }

    /** 相册整体提交：顺序即展示顺序，传空列表就是清空相册。 */
    suspend fun updatePhotos(urls: List<String>): ApiResult<List<UserPhoto>> =
        when (val result = safeApiCall<PhotoListResult> {
            userApi.updatePhotos(UpdatePhotosRequestDto(urls))
        }) {
            is ApiResult.Success -> ApiResult.Success(result.data.photos)
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /** 展示开关（P15 设置页用，这里先备着）。 */
    suspend fun updateSettings(
        showInNearby: Int? = null,
        showInRecommend: Int? = null,
    ): ApiResult<Unit> = safeApiCallUnit {
        userApi.updateSettings(UpdateSettingsRequestDto(showInNearby, showInRecommend))
    }
}
