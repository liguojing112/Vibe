package com.qichuang.changliao.core.navigation

import android.net.Uri
import com.qichuang.changliao.core.data.model.CallKind

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

    /** P10 直接落在「互动」分类（P03 的「互动通知」入口，docs/01 P03）。 */
    const val NOTICE_INTERACT = "message/notices/interact"

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

    /**
     * P17 起要区分语音/视频（标题、单价、控制区按钮都不一样），所以带上 `kind`。
     * `kind` 有默认值，老的 `callConfirm(id)` 调用不会因此报错。
     */
    const val CALL_CONFIRM = "call/confirm/{user_id}?kind={kind}"
    const val CALL_WAITING = "call/waiting/{user_id}?kind={kind}"
    const val VOICE_CALL = "call/voice/{user_id}"
    const val VIDEO_CALL = "call/video/{user_id}"

    /** P21 账单：时长从 P19/P20 传过来（演示计时器数出来的秒数）。 */
    const val CALL_BILL = "call/bill/{user_id}?kind={kind}&seconds={seconds}"
    const val CALL_RECORDS = "call/records"

    fun callConfirm(userId: Long, kind: CallKind): String = "call/confirm/$userId?kind=${kind.apiValue}"

    fun callWaiting(userId: Long, kind: CallKind): String = "call/waiting/$userId?kind=${kind.apiValue}"

    fun voiceCall(userId: Long): String = "call/voice/$userId"

    fun videoCall(userId: Long): String = "call/video/$userId"

    fun callBill(userId: Long, kind: CallKind, seconds: Int): String =
        "call/bill/$userId?kind=${kind.apiValue}&seconds=$seconds"

    // ---------- P23 礼物面板 ----------

    const val GIFT_PANEL = "gift/{user_id}?name={name}"

    /** [name] 是对方昵称，用来当标题（设计图 23 的「送给小满」）；空着就写「TA」。 */
    fun giftPanel(userId: Long, name: String = ""): String =
        "gift/$userId?name=" + Uri.encode(name)

    // ---------- P26~P29 收益 ----------

    const val INCOME = "income"
    const val LEVEL_SETTING = "income/level"
    const val WITHDRAW = "income/withdraw"
    const val REAL_NAME = "income/real-name"
}
