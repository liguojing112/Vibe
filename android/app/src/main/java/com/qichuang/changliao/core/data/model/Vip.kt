package com.qichuang.changliao.core.data.model

/**
 * 会员中心的数据（P12，docs/05 第 4.9 节）。
 *
 * 套餐、权益、提示卡文案**全部来自服务端**：价格由后台配（金币数 + 折合人民币），
 * 权益第 4 项里的「每日 N 条」也是配置里现取的，客户端不写死（铁律 6）。
 */
data class VipPlan(
    val id: Long = 0,
    val name: String = "",
    val days: Int = 0,
    val priceCoins: Int = 0,
    /** 折合人民币（整元）；设计图 12 的横幅写的是「¥39 / 月」。 */
    val priceYuan: Int = 0,
)

/** 一条会员权益（图标 + 标题 + 说明都由服务端给）。 */
data class VipBenefit(
    val icon: String = "",
    val title: String = "",
    val desc: String = "",
)

/** 会员中心一页要的一切。 */
data class VipInfo(
    val isVip: Boolean = false,
    /** 有效会员才有；过期时是空串。 */
    val expireAt: String = "",
    val plans: List<VipPlan> = emptyList(),
    val benefits: List<VipBenefit> = emptyList(),
    val balance: Long = 0,
    val notice: String = "",
)

/** 买套餐的结果：新的到期时间 + 最新余额。 */
data class VipBuyResult(
    val expireAt: String = "",
    val days: Int = 0,
    val priceCoins: Int = 0,
    val balance: Long = 0,
)
