package com.qichuang.changliao.core.data.model

/**
 * 性别（docs/01 第 1 节）：注册或开号时确定，用户自己不能改。
 * 数值与服务端 `cl_user.gender` 一致（1 男 / 2 女）。
 */
object Gender {
    const val UNKNOWN = 0
    const val MALE = 1
    const val FEMALE = 2

    fun label(value: Int): String = when (value) {
        MALE -> "男"
        FEMALE -> "女"
        else -> ""
    }

    /** 反查（页面用 Chip 选性别时把文案换回数值）。 */
    fun of(label: String): Int = when (label) {
        "男" -> MALE
        "女" -> FEMALE
        else -> UNKNOWN
    }
}
