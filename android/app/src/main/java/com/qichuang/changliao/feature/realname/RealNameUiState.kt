package com.qichuang.changliao.feature.realname

import com.qichuang.changliao.core.data.model.RealNameInfo

/**
 * 实名认证（P29）状态。
 *
 * 状态、打码号码、能不能提交**都由服务端给**（`docs/05` `/realname`）——
 * 客户端只负责把字填进表单、把服务端的话转出来。
 */
data class RealNameUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val info: RealNameInfo? = null,
    val nameInput: String = "",
    val idCardInput: String = "",
    val submitting: Boolean = false,
    val toast: String? = null,
) {

    /** 只有「未认证 / 被驳回」才显示表单（审核中和已通过都不该再填）。 */
    val showForm: Boolean get() = info?.canSubmit == true

    /** 身份证要 18 位才让点提交（真正的格式和校验位由服务端判，错了返回 40003）。 */
    val canSubmit: Boolean
        get() = showForm && !submitting &&
            nameInput.isNotBlank() &&
            idCardInput.trim().length == ID_CARD_LENGTH

    /** 已通过 / 审核中时展示的状态说明。 */
    val statusDesc: String
        get() = when {
            info == null -> ""
            info.passed -> "你的身份信息已通过审核，分成比例按实名的标准计算"
            info.auditing -> "已提交，等待人工审核，一般 1~3 个工作日"
            info.rejected -> "上次提交未通过，可以修改后重新提交"
            else -> "完成实名认证后，分成比例按实名的标准计算"
        }

    companion object {
        const val ID_CARD_LENGTH = 18
    }
}
