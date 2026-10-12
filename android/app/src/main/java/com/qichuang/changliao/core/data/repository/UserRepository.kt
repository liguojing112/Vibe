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

    suspend fun me(): ApiResult<UserProfile> = safeApiCall { userApi.me() }

    suspend fun updateProfile(update: ProfileUpdate): ApiResult<UserProfile> =
        safeApiCall {
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
