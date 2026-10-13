package com.qichuang.changliao.core.data.local.db.entity

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey

/**
 * 本地消息表（docs/10 第 1.2 节）。**这是聊天记录的主数据源**，界面全部从它读。
 *
 * 几个关键点：
 * - 主键是**本地自增** [localId]：[serverId] 在消息发出去之前是没有的，不能拿它当主键；
 * - [clientMsgId] 加了唯一索引：**幂等的唯一手段** —— 乐观 UI 先写一条本地记录，
 *   服务端确认后同一条（同一个 client_msg_id）会再回来一次，靠这个索引 `INSERT OR IGNORE` 掉重；
 *   WebSocket 推送和 `/chat/sync` 补拉也可能给同一条消息，同样靠它去重；
 * - 会话用 [peerId] 关联而不是服务端会话 id：第一次发消息之前服务端还没有会话（docs/04 第 3 节）；
 * - [ownerId] 是**这条记录属于哪个登录用户**：同一台设备换号时两个人的聊天记录不能串
 *   （docs/10 第 3 节规则 4 的意思，只是这里落在库里而不是缓存文件里）。
 */
@Entity(
    tableName = "message",
    indices = [
        Index(value = ["ownerId", "peerId", "createdAt"]),
        Index(value = ["clientMsgId"], unique = true),
        Index(value = ["serverId"]),
    ],
)
data class MessageEntity(
    @PrimaryKey(autoGenerate = true)
    val localId: Long = 0,
    val ownerId: Long = 0,
    val clientMsgId: String,
    val serverId: Long? = null,
    val conversationId: Long? = null,
    val peerId: Long,
    val senderId: Long = 0,
    val outgoing: Boolean = false,
    val type: Int = 1,
    val content: String = "",
    /** 图片消息的本地文件路径（发出去之前先存本地，重发直接用）。 */
    val localMediaPath: String? = null,
    /** 见 `MessageStatus`：0 sending / 1 sent / 2 failed。 */
    val status: Int = 1,
    val priceCoins: Int = 0,
    /** 服务端时间，毫秒；本地先出来的消息用本机时间占位，收到正式消息后会被替换。 */
    val createdAt: Long = 0,
    /** 本地最后修改时间，毫秒（重发时更新）。 */
    val updatedAt: Long = 0,
)
