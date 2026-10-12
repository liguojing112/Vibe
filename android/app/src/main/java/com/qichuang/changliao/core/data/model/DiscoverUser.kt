package com.qichuang.changliao.core.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 发现列表里的一个人（docs/05 第 4.3 节）。
 *
 * `online` / `isNew` / `verified` 都是**服务端算好的**，客户端不做任何判断；
 * 男用户看女用户时才带价格（[messagePrice]，开关打开才带语音/视频价）。
 */
@Serializable
data class DiscoverUser(
    val id: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    val city: String = "",
    val age: Int? = null,
    val job: String = "",
    val bio: String = "",
    val level: Int = 1,
    val online: Boolean = false,
    @SerialName("is_new") val isNew: Boolean = false,
    val verified: Boolean = false,
    @SerialName("message_price") val messagePrice: Long? = null,
    @SerialName("voice_price") val voicePrice: Long? = null,
    @SerialName("video_price") val videoPrice: Long? = null,
)

/** 发现接口的一页（分页外壳，docs/05 第 1 节）。 */
@Serializable
data class DiscoverPage(
    val list: List<DiscoverUser> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    @SerialName("page_size") val pageSize: Int = 20,
    @SerialName("has_more") val hasMore: Boolean = false,
)

/**
 * 发现页的筛选条件（docs/01 P07），存在本地、回到 P01 生效。
 *
 * 默认值就是「没筛过」的样子：`isActive` 为 false 时请求不带这些参数。
 */
@Serializable
data class DiscoverFilter(
    val city: String = "",
    val ageMin: Int = DEFAULT_AGE_MIN,
    val ageMax: Int = DEFAULT_AGE_MAX,
    val purposes: List<String> = emptyList(),
    val verified: Boolean = false,
    val complete: Boolean = false,
    val onlineOnly: Boolean = false,
) {
    /** 有没有真正筛过东西（决定要不要带筛选参数、以及缓存键要不要加后缀）。 */
    val isActive: Boolean
        get() = city.isNotEmpty() ||
            ageMin != DEFAULT_AGE_MIN ||
            ageMax != DEFAULT_AGE_MAX ||
            purposes.isNotEmpty() ||
            verified ||
            complete ||
            onlineOnly

    /**
     * 缓存键后缀：筛选条件不一样，缓存不能混用，否则改了筛选会先闪一眼旧结果。
     * 用 hashCode 的十六进制就够了（键里不能有中文，会被文件名转义成下划线）。
     */
    val signature: String
        get() = if (isActive) Integer.toHexString(hashCode()) else ""

    companion object {
        /** 年龄范围默认值（docs/01 P07：双滑块 18~60）。 */
        const val DEFAULT_AGE_MIN = 18
        const val DEFAULT_AGE_MAX = 60

        /** 交友期待的可选值（与服务端 `in:` 校验一致）。 */
        val PURPOSES = listOf("认真交友", "兴趣搭子", "长期关系")
    }
}
