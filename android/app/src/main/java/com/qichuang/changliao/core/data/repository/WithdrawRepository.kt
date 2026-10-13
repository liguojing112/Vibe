package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.WithdrawAccount
import com.qichuang.changliao.core.data.model.WithdrawAccountType
import com.qichuang.changliao.core.data.model.WithdrawApplyResult
import com.qichuang.changliao.core.data.model.WithdrawInfo
import com.qichuang.changliao.core.data.model.WithdrawRecord
import com.qichuang.changliao.core.data.model.WithdrawTier
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.WithdrawApi
import com.qichuang.changliao.core.network.dto.WithdrawAccountDto
import com.qichuang.changliao.core.network.dto.WithdrawApplyDataDto
import com.qichuang.changliao.core.network.dto.WithdrawApplyRequestDto
import com.qichuang.changliao.core.network.dto.WithdrawInfoDto
import com.qichuang.changliao.core.network.dto.WithdrawRecordDto
import com.qichuang.changliao.core.network.dto.WithdrawRecordPageDto
import com.qichuang.changliao.core.network.dto.WithdrawTierDto
import com.qichuang.changliao.core.network.safeApiCall
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 提现（docs/05 第 4.8 节，P28）。**不做本地缓存**：可提现金额和今日次数都是实时数。
 *
 * 提现记录分页返回 [WithdrawRecord] 列表，翻页由调用方（ViewModel）管。
 */
@Singleton
class WithdrawRepository @Inject constructor(
    private val withdrawApi: WithdrawApi,
) {

    suspend fun info(): ApiResult<WithdrawInfo> =
        when (val result = safeApiCall<WithdrawInfoDto> { withdrawApi.info() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun apply(
        amountYuan: Int,
        accountType: WithdrawAccountType,
        accountNo: String,
        bankName: String?,
    ): ApiResult<WithdrawApplyResult> =
        when (
            val result = safeApiCall<WithdrawApplyDataDto> {
                withdrawApi.apply(
                    WithdrawApplyRequestDto(
                        amountYuan = amountYuan,
                        accountType = accountType.apiValue,
                        accountNo = accountNo,
                        bankName = bankName?.takeIf { it.isNotBlank() },
                    ),
                )
            }
        ) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun records(page: Int): ApiResult<List<WithdrawRecord>> =
        when (val result = safeApiCall<WithdrawRecordPageDto> { withdrawApi.records(page) }) {
            is ApiResult.Success -> ApiResult.Success(result.data.list.map { it.toModel() })
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
}

private fun WithdrawInfoDto.toModel() = WithdrawInfo(
    amountYuan = amountYuan,
    points = points,
    frozenPoints = frozenPoints,
    tiers = tiers.map { it.toModel() },
    feeRate = feeRate,
    notice = notice,
    realNamePassed = realNamePassed,
    realName = realName,
    needRealName = needRealName,
    account = account?.toModel(),
    dailyLimit = dailyLimit,
    todayCount = todayCount,
    remainingToday = remainingToday,
)

private fun WithdrawTierDto.toModel() = WithdrawTier(
    yuan = yuan,
    points = points,
    feeFen = feeFen,
    actualFen = actualFen,
    enabled = enabled,
)

private fun WithdrawAccountDto.toModel() = WithdrawAccount(
    accountType = WithdrawAccountType.fromApi(accountType),
    accountNo = accountNo,
    bankName = bankName,
)

private fun WithdrawApplyDataDto.toModel() = WithdrawApplyResult(
    orderNo = orderNo,
    amountFen = amountFen,
    points = points,
    feeFen = feeFen,
    actualFen = actualFen,
    statusText = statusText,
    balance = balance,
    frozen = frozen,
)

private fun WithdrawRecordDto.toModel() = WithdrawRecord(
    id = id,
    orderNo = orderNo,
    amountFen = amountFen,
    actualFen = actualFen,
    status = status,
    statusText = statusText,
    rejectReason = rejectReason,
    createdAt = ChatRepository.serverMillis(createdAt, 0L),
    paidAt = ChatRepository.serverMillis(paidAt, 0L),
)
