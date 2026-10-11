package com.qichuang.changliao.core.common

/**
 * 表单校验（docs/01 P00、P14、P29）。
 *
 * 规则必须和 `server/app/Http/Api/Request/V1/RegisterRequest.php` 里的服务端规则一致 ——
 * 客户端校验只是为了少一次往返，**服务端才是最终判定**。
 */
object Validators {

    const val PHONE_INVALID = "请输入正确的手机号"
    const val PASSWORD_INVALID = "密码 6~20 位，必须同时包含字母和数字"
    const val NICKNAME_INVALID = "昵称 2~12 个字"
    const val ID_CARD_INVALID = "请输入正确的身份证号"

    /** 11 位，1 开头，第二位 3~9。 */
    fun isPhone(value: String): Boolean {
        if (value.length != 11) {
            return false
        }
        if (value[0] != '1' || value[1] !in '3'..'9') {
            return false
        }
        return value.all { it in '0'..'9' }
    }

    /** 6~20 位，且同时含字母和数字。 */
    fun isPassword(value: String): Boolean {
        if (value.length !in 6..20) {
            return false
        }
        val hasLetter = value.any { it in 'a'..'z' || it in 'A'..'Z' }
        val hasDigit = value.any { it in '0'..'9' }
        return hasLetter && hasDigit
    }

    /** 2~12 个字。 */
    fun isNickname(value: String): Boolean = value.length in 2..12

    /** 18 位身份证：格式 + 校验位（GB 11643）。 */
    fun isIdCard(value: String): Boolean {
        if (value.length != 18) {
            return false
        }
        val body = value.take(17)
        if (!body.all { it in '0'..'9' }) {
            return false
        }
        val check = value[17].uppercaseChar()
        if (check !in '0'..'9' && check != 'X') {
            return false
        }
        var sum = 0
        WEIGHTS.forEachIndexed { index, weight -> sum += (body[index] - '0') * weight }
        return CHECK_CODES[sum % 11] == check
    }

    private val WEIGHTS = intArrayOf(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2)

    private const val CHECK_CODES = "10X98765432"
}
