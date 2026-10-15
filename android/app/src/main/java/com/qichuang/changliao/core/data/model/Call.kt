package com.qichuang.changliao.core.data.model

/**
 * 通话方式（docs/01 第 4 节、P17~P21）。
 *
 * 本期**只做页面不做功能**：没有任何真实通话，这个枚举只用来决定页面上写「语音」还是「视频」、
 * 显示哪一栏单价、以及控制区多不多「摄像头 / 翻转」两颗按钮。
 */
enum class CallKind(val apiValue: String, val label: String) {
    VOICE("voice", "语音"),
    VIDEO("video", "视频"),
    ;

    /** 「语音通话」/「视频通话」。 */
    val callLabel: String get() = label + "通话"

    companion object {
        fun fromApiValue(value: String?): CallKind =
            entries.firstOrNull { it.apiValue == value } ?: VOICE
    }
}
