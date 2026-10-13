package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.RealNameInfo
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.RealNameApi
import com.qichuang.changliao.core.network.dto.RealNameDto
import com.qichuang.changliao.core.network.dto.RealNameSubmitRequestDto
import com.qichuang.changliao.core.network.safeApiCall
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 实名认证（docs/05 第 4.8 节，P29）。**不做本地缓存** ——
 * 状态（审核中/已通过）是服务端说了算的，缓存住会让人以为已经通过了。
 */
@Singleton
class RealNameRepository @Inject constructor(
    private val realNameApi: RealNameApi,
) {

    suspend fun info(): ApiResult<RealNameInfo> =
        when (val result = safeApiCall<RealNameDto> { realNameApi.show() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /** 提交实名。格式/审核中这些由服务端判（40003 / 40002），客户端只把话转出来。 */
    suspend fun submit(realName: String, idCard: String): ApiResult<RealNameInfo> =
        when (
            val result = safeApiCall<RealNameDto> {
                realNameApi.submit(RealNameSubmitRequestDto(realName = realName, idCard = idCard))
            }
        ) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
}

private fun RealNameDto.toModel() = RealNameInfo(
    status = status,
    statusText = statusText,
    realName = realName,
    idCardMask = idCardMask,
    rejectReason = rejectReason,
    submittedAt = submittedAt,
    auditedAt = auditedAt,
    canSubmit = canSubmit,
)
