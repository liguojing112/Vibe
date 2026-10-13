package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.IncomeLevel
import com.qichuang.changliao.core.data.model.IncomeLevelRow
import com.qichuang.changliao.core.data.model.IncomeLogEntry
import com.qichuang.changliao.core.data.model.IncomeSummary
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.IncomeApi
import com.qichuang.changliao.core.network.dto.IncomeLevelDto
import com.qichuang.changliao.core.network.dto.IncomeLevelRowDto
import com.qichuang.changliao.core.network.dto.IncomeLogDto
import com.qichuang.changliao.core.network.dto.IncomeLogPageDto
import com.qichuang.changliao.core.network.dto.IncomeSummaryDto
import com.qichuang.changliao.core.network.safeApiCall
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 女用户的收益与等级（docs/05 第 4.8 节，P26/P27）。三个都是只读的，**不做本地缓存** ——
 * 积分和等级随时在变（来一条私信收益就变），缓存住反而显示旧数。
 */
@Singleton
class IncomeRepository @Inject constructor(
    private val incomeApi: IncomeApi,
) {

    suspend fun summary(): ApiResult<IncomeSummary> =
        when (val result = safeApiCall<IncomeSummaryDto> { incomeApi.summary() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun logs(page: Int): ApiResult<List<IncomeLogEntry>> =
        when (val result = safeApiCall<IncomeLogPageDto> { incomeApi.logs(page) }) {
            is ApiResult.Success -> ApiResult.Success(result.data.list.map { it.toModel() })
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun level(): ApiResult<IncomeLevel> =
        when (val result = safeApiCall<IncomeLevelDto> { incomeApi.level() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
}

private fun IncomeSummaryDto.toModel() = IncomeSummary(
    balance = balance,
    frozen = frozen,
    yuanFen = yuanFen,
    todayPoints = todayPoints,
    todayMessages = todayMessages,
    todayGifts = todayGifts,
    notice = notice,
)

private fun IncomeLogDto.toModel() = IncomeLogEntry(
    id = id,
    type = type,
    title = title,
    change = change,
    frozenChange = frozenChange,
    balanceAfter = balanceAfter,
    createdAt = ChatRepository.serverMillis(createdAt, 0L),
)

private fun IncomeLevelDto.toModel() = IncomeLevel(
    level = level,
    name = name,
    totalEarnedPoints = totalEarnedPoints,
    levelLocked = levelLocked,
    nextLevel = nextLevel,
    nextThreshold = nextThreshold,
    pointsToNext = pointsToNext,
    progressPercent = progressPercent,
    messagePrice = messagePrice,
    shareRate = shareRate,
    actualShareRate = actualShareRate,
    giftShareRate = giftShareRate,
    realNamePassed = realNamePassed,
    callEnabled = callEnabled,
    voicePrice = voicePrice,
    videoPrice = videoPrice,
    levels = levels.map { it.toModel() },
)

private fun IncomeLevelRowDto.toModel() = IncomeLevelRow(
    level = level,
    name = name,
    thresholdPoints = thresholdPoints,
    messagePrice = messagePrice,
    messageShareRate = messageShareRate,
)
