package com.qichuang.changliao.core.data.local.db.entity

import androidx.room.Entity

/**
 * 本地会话表（docs/10 第 1.2 节）。
 *
 * 主键是 `(ownerId, peerId)`：**第一次发消息之前服务端还没有会话**，本地得能先有一条记录
 * 把界面撑起来，所以不能等服务端会话 id；[serverConversationId] 拿到之后再回填。
 * `ownerId` 放在主键里是因为同一台设备换号时两个人的会话列表必须完全隔开。
 *
 * 对方的昵称/头像**冗余存一份**在这里：会话列表要秒开，不能为了显示一个昵称去逐个查用户。
 */
@Entity(tableName = "conversation", primaryKeys = ["ownerId", "peerId"])
data class ConversationEntity(
    val ownerId: Long = 0,
    val peerId: Long,
    val serverConversationId: Long? = null,
    val peerNickname: String = "",
    val peerAvatar: String = "",
    /** 列表里显示的摘要（服务端给的最后一条消息内容，展示口径由服务端定）。 */
    val lastMessagePreview: String = "",
    /** 毫秒。 */
    val lastMessageAt: Long = 0,
    val unread: Int = 0,
    /**
     * 本地删除标记（对应服务端的「只对自己隐藏」）。
     * 删除之后对方来新消息，服务端会把会话重新推给我，写入时这个标记会被清掉。
     */
    val deleted: Boolean = false,
)
