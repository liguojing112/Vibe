package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.VipBenefit
import com.qichuang.changliao.core.data.model.VipBuyResult
import com.qichuang.changliao.core.data.model.VipInfo
import com.qichuang.changliao.core.data.model.VipPlan
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.VipApi
import com.qichuang.changliao.core.network.dto.VipBenefitDto
import com.qichuang.changliao.core.network.dto.VipBuyDataDto
import com.qichuang.changliao.core.network.dto.VipBuyRequestDto
import com.qichuang.changliao.core.network.dto.VipInfoDto
import com.qichuang.changliao.core.network.dto.VipPlanDto
import com.qichuang.changliao.core.network.safeApiCall
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 会员（docs/05 第 4.9 节，P12）。
 *
 * **不做本地缓存**：套餐是后台随时可调的运营数据，到期时间和余额是钱 —— 打开页面拉一次实时的才对
 * （和礼物面板、金币钱包一个道理）。
 */
@Singleton
class VipRepository @Inject constructor(
    private val vipApi: VipApi,
) {

    suspend fun info(): ApiResult<VipInfo> =
        when (val result = safeApiCall<VipInfoDto> { vipApi.info() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /**
     * 买套餐。
     *
     * 订单号由这里生成（32 位十六进制，和服务端 id 一个形状）—— **同一个订单号重发不会重复扣钱**，
     * 所以调用方重试时可以复用同一个 [clientOrderId]；每次新买要生成新的。
     */
    suspend fun buy(planId: Long, clientOrderId: String = newOrderId()): ApiResult<VipBuyResult> =
        when (val result = safeApiCall<VipBuyDataDto> { vipApi.buy(VipBuyRequestDto(planId, clientOrderId)) }) {
            is ApiResult.Success -> ApiResult.Success(
                VipBuyResult(
                    expireAt = result.data.expireAt.orEmpty(),
                    days = result.data.days,
                    priceCoins = result.data.priceCoins,
                    balance = result.data.balance,
                ),
            )

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    companion object {
        fun newOrderId(): String = UUID.randomUUID().toString().replace("-", "")
    }
}

private fun VipInfoDto.toModel() = VipInfo(
    isVip = isVip,
    expireAt = expireAt.orEmpty(),
    plans = plans.map { it.toModel() },
    benefits = benefits.map { it.toModel() },
    balance = balance,
    notice = notice,
)

private fun VipPlanDto.toModel() = VipPlan(
    id = id,
    name = name,
    days = days,
    priceCoins = priceCoins,
    priceYuan = priceYuan,
)

private fun VipBenefitDto.toModel() = VipBenefit(
    icon = icon,
    title = title,
    desc = desc,
)
