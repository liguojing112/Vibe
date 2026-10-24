package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.local.db.entity.ConversationEntity
import com.qichuang.changliao.core.data.local.db.entity.MessageEntity
import com.qichuang.changliao.core.data.model.ChatConversation
import com.qichuang.changliao.core.data.model.ChatFee
import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.ChatPeer
import com.qichuang.changliao.core.data.model.ChatUnread
import com.qichuang.changliao.core.data.model.MessageStatus
import com.qichuang.changliao.core.network.dto.ChatFeeDto
import com.qichuang.changliao.core.network.dto.ChatPeerDto
import com.qichuang.changliao.core.network.dto.ConversationDto
import com.qichuang.changliao.core.network.dto.MessageDto
import com.qichuang.changliao.core.network.dto.UnreadDto

/**
 * 网络 DTO ↔ 本地库实体 ↔ 界面模型 的转换，只在这一处写。
 *
 * 三条转换里最容易错的是「对方是谁」：消息里给的是 `sender_id` / `receiver_id`，
 * 而本地库按 `peerId`（对方）归类，所以**要拿我自己是谁去反推**（[peerIdOf]）。
 */

/** 服务端消息 → 本地实体。[ownerId] 是当前登录用户，用来判断这条是不是我发的。 */
internal fun MessageDto.toEntity(ownerId: Long): MessageEntity {
    val outgoing = senderId == ownerId
    // 服务端时间优先；解析不了就用本机时间占位（收到正式消息时会被替换）
    val millis = ChatRepository.serverMillis(createdAt, System.currentTimeMillis())

    return MessageEntity(
        ownerId = ownerId,
        clientMsgId = clientMsgId,
        serverId = if (id > 0) id else null,
        conversationId = if (conversationId > 0) conversationId else null,
        peerId = peerIdOf(ownerId),
        senderId = senderId,
        outgoing = outgoing,
        type = type,
        content = content,
        giftIcon = giftIcon,
        // 服务端来的消息已经发出去了，状态一定是 sent
        status = MessageStatus.SENT.code,
        priceCoins = priceCoins,
        createdAt = millis,
        updatedAt = millis,
    )
}

internal fun MessageEntity.toModel(): ChatMessage = ChatMessage(
    localId = localId,
    clientMsgId = clientMsgId,
    serverId = serverId,
    conversationId = conversationId,
    peerId = peerId,
    senderId = senderId,
    outgoing = outgoing,
    type = type,
    content = content,
    giftIcon = giftIcon,
    localMediaPath = localMediaPath,
    status = MessageStatus.fromCode(status),
    priceCoins = priceCoins,
    createdAt = createdAt,
)

/** 服务端消息 → 界面模型（发消息成功后直接用返回值，不用再查库）。 */
internal fun MessageDto.toModel(peerId: Long, outgoing: Boolean, fallbackMillis: Long): ChatMessage =
    ChatMessage(
        clientMsgId = clientMsgId,
        serverId = if (id > 0) id else null,
        conversationId = if (conversationId > 0) conversationId else null,
        peerId = peerId,
        senderId = senderId,
        outgoing = outgoing,
        type = type,
        content = content,
        giftIcon = giftIcon,
        status = MessageStatus.SENT,
        priceCoins = priceCoins,
        createdAt = ChatRepository.serverMillis(createdAt, fallbackMillis),
    )

/** 会话（服务端）→ 本地实体。对方资料缺失时返回 null（宁可少一条，也不要一条没有对方的空会话）。 */
internal fun ConversationDto.toEntity(ownerId: Long): ConversationEntity? {
    val peer = peer ?: return null

    return ConversationEntity(
        ownerId = ownerId,
        peerId = peer.id,
        serverConversationId = if (id > 0) id else null,
        peerNickname = peer.nickname,
        peerAvatar = peer.avatar,
        lastMessagePreview = previewOf(lastMessage),
        lastMessageAt = ChatRepository.serverMillis(lastMessageAt ?: lastMessage?.createdAt, 0L),
        unread = unread,
        deleted = false,
    )
}

internal fun ConversationEntity.toModel(): ChatConversation = ChatConversation(
    peerId = peerId,
    serverConversationId = serverConversationId,
    peerNickname = peerNickname,
    peerAvatar = peerAvatar,
    lastMessagePreview = lastMessagePreview,
    lastMessageAt = lastMessageAt,
    unread = unread,
)

internal fun ChatPeerDto.toModel(): ChatPeer = ChatPeer(
    id = id,
    nickname = nickname,
    avatar = avatar,
    age = age,
    gender = gender,
    level = level,
    online = online,
)

internal fun ChatFeeDto.toModel(): ChatFee = ChatFee(
    nextPrice = nextPrice,
    freeReason = freeReason,
    pairFreeLeft = pairFreeLeft,
    vipFreeLeft = vipFreeLeft,
    balance = balance,
    stripText = stripText,
)

internal fun UnreadDto.toModel(): ChatUnread = ChatUnread(
    conversation = conversation,
    visitor = visitor,
    notice = notice,
    total = total,
)

/** 这条消息的「对方」是谁。 */
private fun MessageDto.peerIdOf(ownerId: Long): Long = if (senderId == ownerId) receiverId else senderId

/** 会话列表里的摘要文案。 */
private fun previewOf(message: MessageDto?): String = when (message?.type) {
    null -> ""
    ChatMessage.TYPE_IMAGE -> "[图片]"
    ChatMessage.TYPE_GIFT -> "[礼物]"
    else -> message.content
}
