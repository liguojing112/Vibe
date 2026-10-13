package com.qichuang.changliao.core.data.model

/**
 * 一条系统/互动通知（P10）。
 *
 * 标题、正文、分类**都是服务端给的**（后台发的公告要能改文案），客户端只管显示和挑图标。
 * [bizType] 用来挑图标（LEVEL_UP / WITHDRAW / REAL_NAME / FOLLOW / GIFT / …）。
 */
data class Notice(
    val id: Long = 0,
    val category: Int = NoticeCategory.SYSTEM.apiValue,
    val title: String = "",
    val content: String = "",
    val bizType: String? = null,
    val bizId: Long? = null,
    /** 打开这一页**之前**读没读过。 */
    val read: Boolean = false,
    /** 毫秒；解析不了就是 0。 */
    val createdAt: Long = 0,
)

/** 通知列表一页。 */
data class NoticePage(
    val list: List<Notice> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    /** 标已读之后的剩余未读总数（底部角标）。 */
    val unread: Int = 0,
)

/** P10 的两个 Tab（`docs/01` P10）。 */
enum class NoticeCategory(val apiValue: Int, val label: String) {
    SYSTEM(1, "系统通知"),
    INTERACT(2, "互动"),
}

/** 举报提交后的结果（只提交，处理在后台）。 */
data class ReportResult(
    val id: Long = 0,
    /** 服务端拼好的提示（「举报已提交，我们会尽快处理」）。 */
    val statusText: String = "",
)
