package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 实名状态（docs/05 第 4.8 节 `/realname`，P29）。
 *
 * **身份证号永远是打码的**（`110***********1233`）—— 明文只在服务端的密文列里。
 */
@Serializable
data class RealNameDto(
    /** 0 未认证 1 审核中 2 已通过 3 已驳回。 */
    val status: Int = 0,
    @SerialName("status_text") val statusText: String = "",
    @SerialName("real_name") val realName: String = "",
    @SerialName("id_card_mask") val idCardMask: String = "",
    @SerialName("reject_reason") val rejectReason: String? = null,
    @SerialName("submitted_at") val submittedAt: String? = null,
    @SerialName("audited_at") val auditedAt: String? = null,
    /** 未认证和被驳回可以提交；审核中和已通过不行。 */
    @SerialName("can_submit") val canSubmit: Boolean = false,
)

/** 提交实名的入参。 */
@Serializable
data class RealNameSubmitRequestDto(
    @SerialName("real_name") val realName: String,
    @SerialName("id_card") val idCard: String,
)
