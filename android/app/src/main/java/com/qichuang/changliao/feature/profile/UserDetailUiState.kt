package com.qichuang.changliao.feature.profile

import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.UserDetail

/** 举报原因（docs/01 P05，与服务端 `cl_report.reason` 的取值一致）。 */
enum class ReportReason(val code: String, val label: String) {
    PORN("PORN", "色情低俗"),
    FRAUD("FRAUD", "广告诈骗"),
    ABUSE("ABUSE", "辱骂骚扰"),
    FAKE("FAKE", "虚假资料"),
    OTHER("OTHER", "其他"),
}

/**
 * 个人详情页状态（P05）。
 *
 * 弹窗状态（菜单 / 拉黑确认 / 举报 / 大图预览）都放在这里，页面只管照着画。
 */
data class UserDetailUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val detail: UserDetail? = null,
    val tagNames: List<String> = emptyList(),
    val menuVisible: Boolean = false,
    val blockConfirmVisible: Boolean = false,
    val reportVisible: Boolean = false,

    /** 正在提交举报（按钮转圈、防连点）。 */
    val reportSubmitting: Boolean = false,
    val reportReason: ReportReason? = null,
    val reportContent: String = "",
    val previewIndex: Int? = null,
    val submitting: Boolean = false,
    /** 拉黑成功 → 返回上一页（拉黑后互相不可见，留着这页没意义）。 */
    val blocked: Boolean = false,
    val toast: String? = null,
) {
    val isFollowing: Boolean get() = detail?.isFollowing == true

    val isMutual: Boolean get() = detail?.isMutual == true

    /** ⋯ 菜单里的关注项文案（docs/01 P05）。 */
    val followLabel: String
        get() = when {
            isMutual -> "互相关注"
            isFollowing -> "已关注"
            else -> "关注"
        }

    /** 相册（大图预览用）。 */
    val photoUrls: List<String> get() = detail?.photos?.map { it.url }.orEmpty()

    /**
     * 底部按钮文案：男用户看女用户带价格（服务端算好的），女用户看男用户只写「私信聊天」。
     */
    val chatLabel: String
        get() {
            val price = detail?.price?.messagePrice ?: return "私信聊天"

            return "私信聊天 " + MoneyFormat.coinPrice(price, MoneyFormat.UNIT_PER_MESSAGE)
        }

    /** 价格面板（只在男看女、且对方没有被拉黑时有）。 */
    val showPricePanel: Boolean get() = detail?.price != null
}
