package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.Gift
import com.qichuang.changliao.core.data.model.GiftSendResult
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.GiftApi
import com.qichuang.changliao.core.network.dto.GiftDto
import com.qichuang.changliao.core.network.dto.GiftListDto
import com.qichuang.changliao.core.network.dto.GiftSendDataDto
import com.qichuang.changliao.core.network.dto.GiftSendRequestDto
import com.qichuang.changliao.core.network.safeApiCall
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 礼物（docs/05 第 4.6 节）。P23（整页面板）和 P31（聊天页弹窗）共用这一个仓库。
 *
 * 礼物列表**不做本地缓存**：它是后台随时可调的运营数据（上下架、改价），
 * 缓存住反而会让人看到已经不卖的礼物，打开面板拉一次就够。
 */
@Singleton
class GiftRepository @Inject constructor(
    private val giftApi: GiftApi,
    private val chatRepository: ChatRepository,
) {

    suspend fun list(): ApiResult<List<Gift>> =
        when (val result = safeApiCall<GiftListDto> { giftApi.list() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.list.map { it.toModel() })
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /**
     * 送礼。
     *
     * 订单号由这里生成（32 位十六进制，和服务端 id 一个形状）—— **同一个订单号重发不会被重复扣钱**，
     * 所以调用方重试时可以复用同一个 [clientOrderId]；每次新送要生成新的。
     */
    suspend fun send(receiverId: Long, giftId: Long, clientOrderId: String = newOrderId()): ApiResult<GiftSendResult> =
        when (
            val result = safeApiCall<GiftSendDataDto> {
                giftApi.send(GiftSendRequestDto(receiverId, giftId, clientOrderId))
            }
        ) {
            is ApiResult.Success -> {
                // 礼物消息要落本地库：推送只发给收礼的人，不补这一条的话
                // 送礼的人回到聊天页看不到自己送出去的礼物气泡
                chatRepository.storeSent(result.data.message)

                ApiResult.Success(
                    GiftSendResult(
                        message = result.data.message.toModel(
                            peerId = receiverId,
                            outgoing = true,
                            fallbackMillis = System.currentTimeMillis(),
                        ),
                        balance = result.data.balance,
                    ),
                )
            }

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    companion object {
        fun newOrderId(): String = UUID.randomUUID().toString().replace("-", "")
    }
}

private fun GiftDto.toModel() = Gift(
    id = id,
    name = name,
    icon = icon,
    priceCoins = priceCoins,
)
