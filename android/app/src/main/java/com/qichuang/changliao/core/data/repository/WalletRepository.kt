package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.CoinLogEntry
import com.qichuang.changliao.core.data.model.CoinLogPage
import com.qichuang.changliao.core.data.model.CoinLogTab
import com.qichuang.changliao.core.data.model.RechargeInfo
import com.qichuang.changliao.core.data.model.RechargeOrder
import com.qichuang.changliao.core.data.model.RechargePack
import com.qichuang.changliao.core.data.model.RechargeService
import com.qichuang.changliao.core.data.model.WalletCoin
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.WalletApi
import com.qichuang.changliao.core.network.dto.CoinLogDto
import com.qichuang.changliao.core.network.dto.CoinLogPageDto
import com.qichuang.changliao.core.network.dto.CreateRechargeOrderBody
import com.qichuang.changliao.core.network.dto.RechargeInfoDto
import com.qichuang.changliao.core.network.dto.RechargeOrderDto
import com.qichuang.changliao.core.network.dto.WalletCoinDto
import com.qichuang.changliao.core.network.safeApiCall
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 金币钱包（docs/05 第 4.7 节，P13/P22/P24）。**只读** —— App 里没有任何改余额的入口：
 * 充值由后台客服做（docs/06 第 8 节），消费由发消息/送礼/买会员各自的接口做。
 *
 * 金币页不是「静默刷新 + 持久化」那一类（docs/10 第 3 节列的是会话/发现/访客等列表）：
 * 余额是钱，**必须每次拿服务端的实时值**，缓存一份旧余额反而危险。
 */
@Singleton
class WalletRepository @Inject constructor(
    private val walletApi: WalletApi,
) {

    suspend fun coin(): ApiResult<WalletCoin> =
        when (val result = safeApiCall<WalletCoinDto> { walletApi.coin() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun coinLogs(tab: CoinLogTab, page: Int): ApiResult<CoinLogPage> =
        when (val result = safeApiCall<CoinLogPageDto> { walletApi.coinLogs(tab.apiValue, page) }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    suspend fun rechargeInfo(): ApiResult<RechargeInfo> =
        when (val result = safeApiCall<RechargeInfoDto> { walletApi.rechargeInfo() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /** 下单并取支付链接（金额和金币都由服务端按档位定）。 */
    suspend fun createRechargeOrder(packIndex: Int): ApiResult<RechargeOrder> =
        when (val result = safeApiCall<RechargeOrderDto> {
            walletApi.createRechargeOrder(CreateRechargeOrderBody(packIndex))
        }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /** 轮询支付结果（只查本地订单，回调负责改状态）。 */
    suspend fun rechargeOrderStatus(orderNo: String): ApiResult<RechargeOrder> =
        when (val result = safeApiCall<RechargeOrderDto> { walletApi.rechargeOrderStatus(orderNo) }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
}

private fun WalletCoinDto.toModel() = WalletCoin(
    balance = balance,
    messagePriceMin = messagePriceMin,
    messagePriceMax = messagePriceMax,
    callEnabled = callEnabled,
    voicePriceMin = voicePriceMin,
    voicePriceMax = voicePriceMax,
    videoPriceMin = videoPriceMin,
    videoPriceMax = videoPriceMax,
)

private fun CoinLogPageDto.toModel() = CoinLogPage(
    list = list.map { it.toModel() },
    total = total,
    page = page,
    hasMore = hasMore,
)

private fun CoinLogDto.toModel() = CoinLogEntry(
    id = id,
    type = type,
    title = title,
    change = change,
    balanceAfter = balanceAfter,
    // 服务端给的是 `yyyy-MM-dd HH:mm:ss`（秒级），这里统一成毫秒给页面格式化
    createdAt = (TimeFormat.parse(createdAt.orEmpty()) ?: 0L) * 1000,
)

private fun RechargeInfoDto.toModel() = RechargeInfo(
    balance = balance,
    packs = packs.mapIndexed { index, pack ->
        RechargePack(coins = pack.coins, yuan = pack.yuan, index = index)
    },
    payEnabled = payEnabled,
    service = RechargeService(
        wechat = service.wechat,
        qrcodeUrl = service.qrcodeUrl,
        phone = service.phone,
    ),
    complianceDisclosure = complianceDisclosure,
    notice = notice,
)

private fun RechargeOrderDto.toModel() = RechargeOrder(
    orderNo = orderNo,
    coins = coins,
    amountFen = amountFen,
    amountText = amountText,
    payUrl = payUrl,
    status = status,
    expireAt = expireAt.orEmpty(),
    balance = balance,
)
