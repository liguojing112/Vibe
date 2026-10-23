package com.qichuang.changliao.feature.moment

/** 发布动态页的状态。 */
data class PublishMomentUiState(
    val content: String = "",
    /** 已经传完的图片地址（只有上传成功的才进这个列表）。 */
    val images: List<String> = emptyList(),
    val uploading: Boolean = false,
    val publishing: Boolean = false,
    /** 发布成功，页面该关掉了。 */
    val published: Boolean = false,
    val toast: String? = null,
) {

    /** 上传没结束时不让发，否则会把「还在传的图」漏掉。 */
    val canPublish: Boolean
        get() = !uploading && !publishing && (content.isNotBlank() || images.isNotEmpty())

    companion object {
        const val MAX_IMAGES = 9
        const val MAX_CONTENT = 500
    }
}
