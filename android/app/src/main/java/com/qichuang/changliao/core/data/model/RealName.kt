package com.qichuang.changliao.core.data.model

/**
 * 实名状态（P29）。
 *
 * [idCardMask] 永远是打码的（`110***********1233`）—— 明文只在服务端的密文列里，
 * 客户端**拿不到也存不下**完整号码。
 */
data class RealNameInfo(
    val status: Int = STATUS_NONE,
    /** 「未认证 / 审核中 / 已通过 / 已驳回」（服务端给）。 */
    val statusText: String = "",
    val realName: String = "",
    val idCardMask: String = "",
    /** 被驳回时才有。 */
    val rejectReason: String? = null,
    val submittedAt: String? = null,
    val auditedAt: String? = null,
    val canSubmit: Boolean = false,
) {
    val passed: Boolean get() = status == STATUS_PASSED
    val auditing: Boolean get() = status == STATUS_AUDITING
    val rejected: Boolean get() = status == STATUS_REJECTED

    companion object {
        const val STATUS_NONE = 0
        const val STATUS_AUDITING = 1
        const val STATUS_PASSED = 2
        const val STATUS_REJECTED = 3
    }
}
