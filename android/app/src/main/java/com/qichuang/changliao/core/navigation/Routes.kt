package com.qichuang.changliao.core.navigation

/**
 * 全部页面路由（docs/01 第 5 节的 31 个页面 + P00 登录/注册/协议）。
 *
 * 页面之间一律用这里的常量跳转，不写裸字符串。P30 余额不足、P31 聊天礼物是弹窗，
 * 不是独立页面，所以没有路由。
 */
object Routes {

    // ---------- 骨架 ----------

    const val LOGIN = "login"
    const val MAIN = "main"

    /** 组件预览页（docs/03 第 7 节），只在 debug 包露入口。 */
    const val GALLERY = "gallery"

    // ---------- 底部导航的 4 个 Tab ----------

    const val TAB_DISCOVER = "tab/discover"
    const val TAB_MATCH = "tab/match"
    const val TAB_MESSAGE = "tab/message"
    const val TAB_MINE = "tab/mine"

    // ---------- P00 登录 / 注册 / 协议 ----------

    const val REGISTER = "register"
    const val AGREEMENT = "agreement/{type}"

    fun agreement(type: String): String = "agreement/$type"

    // ---------- P05 个人详情 / P06 私信聊天 / P07 筛选 ----------

    const val USER_DETAIL = "user/{user_id}"
    const val CHAT = "chat/{user_id}"
    const val FILTER = "discover/filter"

    fun userDetail(userId: Long): String = "user/$userId"

    fun chat(userId: Long): String = "chat/$userId"

    // ---------- P08 / P09 关系 ----------

    const val VISITORS = "relation/visitors"
    const val LIKED_ME = "relation/liked-me"

    // ---------- P10 系统消息 / P11 每日任务 ----------

    const val NOTICES = "message/notices"
    const val DAILY_TASK = "task/daily"

    // ---------- P12 会员 / P13 P22 P24 金币 ----------

    const val VIP = "vip"
    const val COIN_WALLET = "wallet/coin"
    const val RECHARGE = "wallet/recharge"
    const val COIN_LOGS = "wallet/logs"

    // ---------- P14 编辑资料 / P15 设置 ----------

    const val EDIT_PROFILE = "profile/edit"
    const val SETTINGS = "mine/settings"

    /** P15 设置里的黑名单列表。 */
    const val BLOCK_LIST = "mine/blocks"

    // ---------- P02 P16~P21 P25 语音视频（本期只做页面） ----------

    const val VOICE_HALL = "match/voice-hall"
    const val CALL_CONFIRM = "call/confirm/{user_id}"
    const val CALL_WAITING = "call/waiting/{user_id}"
    const val VOICE_CALL = "call/voice/{user_id}"
    const val VIDEO_CALL = "call/video/{user_id}"
    const val CALL_BILL = "call/bill/{user_id}"
    const val CALL_RECORDS = "call/records"

    fun callConfirm(userId: Long): String = "call/confirm/$userId"

    fun callWaiting(userId: Long): String = "call/waiting/$userId"

    fun voiceCall(userId: Long): String = "call/voice/$userId"

    fun videoCall(userId: Long): String = "call/video/$userId"

    fun callBill(userId: Long): String = "call/bill/$userId"

    // ---------- P23 礼物面板 ----------

    const val GIFT_PANEL = "gift/{user_id}"

    fun giftPanel(userId: Long): String = "gift/$userId"

    // ---------- P26~P29 收益 ----------

    const val INCOME = "income"
    const val LEVEL_SETTING = "income/level"
    const val WITHDRAW = "income/withdraw"
    const val REAL_NAME = "income/real-name"
}
