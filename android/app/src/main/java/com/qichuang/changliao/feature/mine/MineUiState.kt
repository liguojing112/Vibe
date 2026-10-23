package com.qichuang.changliao.feature.mine

import androidx.compose.ui.graphics.vector.ImageVector
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.RealNameInfo
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.navigation.Routes

/** 「我」页面菜单里的一项。 */
data class MineMenu(
    val icon: ImageVector,
    val label: String,
    /** 右边的小字（「去认证」「审核中」「已认证」这类）。 */
    val extra: String? = null,
    val route: String,
)

/**
 * 我的（P04）状态。
 *
 * **两套菜单在这里拼**（`docs/01` P04 男女各一份），页面只照着画 ——
 * 这样「女号不该出现会员中心」这类规则能被单测钉住，不用靠肉眼看页面。
 */
data class MineUiState(
    val user: UserProfile? = null,
    val loading: Boolean = true,
    val refreshing: Boolean = false,
    val error: String? = null,
    /** `feature.call_enabled`：通话记录那一项受它控制。 */
    val callEnabled: Boolean = false,
) {

    val isMale: Boolean get() = user?.gender == Gender.MALE

    val nickname: String get() = user?.shownNickname().orEmpty()

    val avatar: String get() = user?.shownAvatar().orEmpty()

    val isVip: Boolean get() = user?.isVip == true

    /** 「ID：100001」——用手机号/账号不好看，用 id 更稳。 */
    val idText: String get() = user?.let { "ID：${it.id}" }.orEmpty()

    /** 「资料完善度 67%」。 */
    val completenessText: String get() = user?.let { "资料完善度 ${it.completeness}%" }.orEmpty()

    /**
     * 等级徽章下的那行字：女用户是「魅力等级 LvX」，男用户是「VIP 剩余 X 天」或「开通会员」。
     */
    val levelText: String
        get() = user?.let {
            if (it.gender == Gender.FEMALE) {
                "魅力等级 Lv${it.level}"
            } else if (it.isVip) {
                vipDaysLeft()?.let { days -> "VIP 剩余 $days 天" } ?: "VIP 会员"
            } else {
                "开通会员"
            }
        }.orEmpty()

    /** 钱包卡：男的是金币，女的是积分。 */
    val walletText: String
        get() = user?.let {
            if (it.gender == Gender.FEMALE) {
                MoneyFormat.points(it.pointBalance ?: 0)
            } else {
                MoneyFormat.coins(it.coinBalance ?: 0)
            }
        }.orEmpty()

    val walletDesc: String get() = if (isMale) "消费钱包" else "我的收益"

    val walletAction: String get() = if (isMale) "去充值" else "去提现"

    /** 钱包卡点去哪儿：男的是金币钱包 P13，女的是我的收益 P26。 */
    val walletRoute: String get() = if (isMale) Routes.COIN_WALLET else Routes.INCOME

    /** 实名菜单右边的小字（`docs/01` P04：去认证 / 审核中 / 已认证 / 被驳回）。 */
    val realNameText: String
        get() = when (user?.realNameStatus) {
            RealNameInfo.STATUS_AUDITING -> "审核中"
            RealNameInfo.STATUS_PASSED -> "已认证"
            RealNameInfo.STATUS_REJECTED -> "被驳回"
            else -> "去认证"
        }

    /**
     * 男女两套菜单（`docs/01` P04）。
     *
     * 通话记录受 `feature.call_enabled` 控制（`docs/01` 第 4 节：开关关着连入口都不显示）。
     */
    val menus: List<MineMenu>
        get() {
            val call = MineMenu(ClIcons.phone, "通话记录", route = Routes.CALL_RECORDS)
            val task = MineMenu(ClIcons.calendar, "每日任务", route = Routes.DAILY_TASK)
            val profile = MineMenu(ClIcons.edit, "基本资料", route = Routes.EDIT_PROFILE)
            val settings = MineMenu(ClIcons.settings, "隐私与设置", route = Routes.SETTINGS)
            val moments = MineMenu(ClIcons.image, "我的朋友圈", route = Routes.MOMENT_MINE)

            val mine = if (isMale) {
                listOf(
                    moments,
                    MineMenu(ClIcons.wallet, "金币收支明细", route = Routes.COIN_LOGS),
                    MineMenu(ClIcons.star, "畅撩交友会员", extra = if (isVip) "已是会员" else null, route = Routes.VIP),
                    task,
                    profile,
                )
            } else {
                listOf(
                    moments,
                    MineMenu(ClIcons.wallet, "我的收益", route = Routes.INCOME),
                    MineMenu(ClIcons.star, "我的等级与收费", route = Routes.LEVEL_SETTING),
                    MineMenu(ClIcons.shield, "实名认证", extra = realNameText, route = Routes.REAL_NAME),
                    task,
                    profile,
                )
            }

            return (if (callEnabled) listOf(call) else emptyList()) + mine + settings
        }

    val isEmpty: Boolean get() = !loading && error == null && user == null

    /** VIP 还剩几天（过期或没填就是 null）。 */
    private fun vipDaysLeft(): Long? {
        val expireAt = user?.vipExpireAt ?: return null
        val seconds = TimeFormat.parse(expireAt) ?: return null
        val left = seconds - System.currentTimeMillis() / 1000

        return if (left <= 0) null else (left + 86_399) / 86_400
    }
}
