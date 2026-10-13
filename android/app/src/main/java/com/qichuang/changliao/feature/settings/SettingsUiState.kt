package com.qichuang.changliao.feature.settings

import com.qichuang.changliao.core.common.Validators

/**
 * 设置（P15）状态。
 *
 * 三类东西混在一页：
 * - **本地开关**（消息提醒）：存在本地，和服务端无关（`docs/01` P15 原文「本地保存」）；
 * - **服务端开关**（展示给附近的人）：走 `/user/settings`，和 P27 的在线接听是同一套接口；
 * - **一次性动作**（改密码、注销、退出登录）。
 */
data class SettingsUiState(
    val loading: Boolean = true,
    val reminderEnabled: Boolean = true,
    val showInNearby: Boolean = true,
    /** 正在提交某个开关（防止连点）。 */
    val switching: Boolean = false,
    val passwordSheetVisible: Boolean = false,
    val oldPassword: String = "",
    val newPassword: String = "",
    val confirmPassword: String = "",
    val passwordError: String? = null,
    val submittingPassword: Boolean = false,
    /** 注销账号的二次确认弹窗。 */
    val cancelConfirmVisible: Boolean = false,
    val cancelSubmitting: Boolean = false,
    val toast: String? = null,
) {

    /** 改密码能不能提交：三项都填了、新密码合规、两次一致。 */
    val canSubmitPassword: Boolean
        get() = oldPassword.isNotBlank() &&
            Validators.isPassword(newPassword) &&
            newPassword == confirmPassword &&
            !submittingPassword

    /**
     * 只在这个字段被填过之后才提示格式问题 —— 刚打开弹窗就飘红字很烦人。
     *
     * 没有问题时**必须返回 null**（不是空串）：`ClTextField` 只要 `error != null` 就把框画成红色，
     * 空串会让两个新密码框一打开就飘红（真跑出来过）。
     */
    val newPasswordHint: String?
        get() = if (newPassword.isNotEmpty() && !Validators.isPassword(newPassword)) {
            "密码 8~20 位，要同时有字母和数字"
        } else {
            null
        }

    val confirmHint: String?
        get() = if (confirmPassword.isNotEmpty() && confirmPassword != newPassword) "两次输入的密码不一致" else null
}
