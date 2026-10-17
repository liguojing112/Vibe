package com.qichuang.changliao.feature.chat

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.MessageStatus
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClChatBubble
import com.qichuang.changliao.core.designsystem.component.ClChatContent
import com.qichuang.changliao.core.designsystem.component.ClChatInputBar
import com.qichuang.changliao.core.designsystem.component.ClChatMessage
import com.qichuang.changliao.core.designsystem.component.ClChatStatus
import com.qichuang.changliao.core.designsystem.component.ClChatToolItem
import com.qichuang.changliao.core.designsystem.component.ClChatToolBar
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClFeeStrip
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.feature.common.CoinNotEnoughSheet

/**
 * 私信聊天页（P06，设计图 06）。
 *
 * 自上而下：顶部（返回 + 昵称 + 资料）→ 收费条（**只有男用户有**）→ 消息列表 → 工具栏 → 输入区。
 *
 * 三条硬要求（`09` 第 1.3 节）：
 * - 收费条**只显示服务端拼好的 `strip_text`**，客户端不拼价格文案（铁律 6）；
 * - 余额不足弹 P30，**输入内容保留**；
 * - 失败的气泡点红色感叹号可以重发。
 */
@Composable
fun ChatScreen(
    state: ChatUiState,
    onBack: () -> Unit,
    onOpenProfile: () -> Unit,
    onOpenRecharge: () -> Unit,
    onOpenGift: () -> Unit,
    onOpenCall: (CallKind) -> Unit,
    onOpenFeeRules: () -> Unit,
    onDismissFeeRules: () -> Unit,
    onDismissCoinNotEnough: () -> Unit,
    onInputChange: (String) -> Unit,
    onSend: () -> Unit,
    onResend: (Long) -> Unit,
    onImagePicked: (Uri) -> Unit,
    onLoadMore: () -> Unit,
    onRetry: () -> Unit,
    onToastShown: () -> Unit,
    modifier: Modifier = Modifier,
) {
    // 图片：系统相册选择器（docs/01 P06 的「图片按钮」）。压缩和上传都在仓库层做。
    val imagePicker = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia(),
    ) { uri -> uri?.let(onImagePicked) }

    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgChat,
        topBar = {
            ClTopBar(
                title = state.peer.nickname,
                onBack = onBack,
                action = {
                    ClTextButton(text = "资料", onClick = onOpenProfile)
                },
            )
        },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        if (state.fee != null) {
            // 收费条文案是服务端拼的，客户端只显示（铁律 6）
            ClFeeStrip(
                text = state.fee.stripText,
                actionText = "收费说明",
                onAction = onOpenFeeRules,
            )
        }

        Box(modifier = Modifier.weight(1f)) {
            when {
                state.loading -> ClLoading()

                state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

                state.isEmpty -> ClEmpty(
                    title = "打个招呼吧",
                    desc = if (state.isMale) "第一条消息她收到就能看到" else null,
                )

                else -> MessageList(
                    state = state,
                    onResend = onResend,
                    onLoadMore = onLoadMore,
                )
            }
        }

        ChatTools(
            state = state,
            onOpenGift = onOpenGift,
            onOpenCall = onOpenCall,
        )

        ClChatInputBar(
            value = state.input,
            onValueChange = onInputChange,
            onSend = onSend,
            placeholder = state.placeholder,
            maxLength = state.maxLength,
            sending = state.sending,
            onPickImage = { imagePicker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
            // 不挂表情按钮：docs/01 P06 写的是「表情按钮（系统 emoji 面板即可）」，
            // 而输入法的 emoji 面板本来就够用（设计图 06 的输入区也只有输入框 + 发送）
        )
    }

    state.feeRules?.let { items ->
        ClBottomSheet(title = "收费说明", onClose = onDismissFeeRules) {
            items.forEach { item ->
                Text(
                    text = "· $item",
                    style = ClTheme.typography.bodySmall,
                    color = ClTheme.colors.inkSecondary,
                    modifier = Modifier.padding(vertical = ClTheme.spacing.xxs),
                )
            }
        }
    }

    state.coinNotEnough?.let { info ->
        CoinNotEnoughSheet(
            info = info,
            onDismiss = onDismissCoinNotEnough,
            // 去充值之前**先把弹窗关掉**：不然从充值页返回时会看到它又在那儿，
            // 用户还以为余额没充上（T4.8 走查时踩到的）
            onRecharge = {
                onDismissCoinNotEnough()
                onOpenRecharge()
            },
        )
    }
}

/** 消息列表：**倒着铺**（最新的在最下面），往上滚到头就加载更早的。 */
@Composable
private fun MessageList(
    state: ChatUiState,
    onResend: (Long) -> Unit,
    onLoadMore: () -> Unit,
) {
    val listState = rememberLazyListState()
    val rows = state.displayRows

    // 首次加载完成时**无条件贴底**：聊天页的惯例是「打开就看最新消息」。
    //
    // 为什么不能只靠下面那个「新消息才滚」：`rememberLazyListState()` 底层是 rememberSaveable，
    // **进程被杀后回来会恢复上一次的滚动位置**（T9.9 实测：重启后进聊天页停在 15:27 的老消息上，
    // 最新的失败消息在屏幕外看不见）。
    var didInitialScroll by remember { mutableStateOf(false) }
    LaunchedEffect(rows.isNotEmpty()) {
        if (!didInitialScroll && rows.isNotEmpty()) {
            didInitialScroll = true
            listState.scrollToItem(0)
        }
    }

    // 新消息 / 刚发出去的消息要自动贴底。
    //
    // 倒序列表**不会自动**跟着新项走：只有当列表正好停在 index 0 时才贴底，
    // 一旦被键盘收起之类的原因顶偏一条（或者用户正在往上翻历史），新消息就留在屏幕外，
    // 用户以为没发出去（T9.8 真机踩到：消息已在库里、服务端也收到了，就是看不见）。
    //
    // 只在用户本来就贴着底部时滚 —— 正在翻历史的时候不该被拽下去。
    //
    // 用 `requestScrollToItem` 而不是 `scrollToItem`：后者是**立刻**按当前已布局的项滚，
    // 这时候新消息还没参与布局、列表还以为自己在 index 0，于是什么都不做（T9.8 第一版就栽在这）。
    // `requestScrollToItem` 会把「滚到第 0 项」排到下一次布局，等新项进来了再执行。
    //
    // **自己发的一定贴底**：发送时键盘收起/弹起会把列表顶偏一条，光看 firstVisibleItemIndex
    // 会误判成「用户在翻历史」而不滚（T9.8 第二次真机踩到：断网时发的失败气泡看不见，得手动上滑）。
    LaunchedEffect(rows.firstOrNull()?.key()) {
        val first = rows.firstOrNull()
        if (first == null) {
            return@LaunchedEffect
        }
        val mine = first is ChatRow.Message && first.message.outgoing
        if (mine || listState.firstVisibleItemIndex <= 1) {
            listState.requestScrollToItem(0)
        }
    }

    // 滚到最上面（倒序列表的末尾）就加载更早的消息
    LaunchedEffect(listState, rows.size, state.hasMore) {
        snapshotFlow { listState.layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: 0 }
            .collect { lastVisible ->
                if (state.hasMore && rows.isNotEmpty() && lastVisible >= rows.size - 2) {
                    onLoadMore()
                }
            }
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        state = listState,
        reverseLayout = true,
        contentPadding = PaddingValues(vertical = ClTheme.spacing.s),
    ) {
        items(rows, key = { row -> row.key() }) { row ->
            when (row) {
                is ChatRow.Time -> Text(
                    text = row.label,
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = ClTheme.spacing.s),
                )

                is ChatRow.Message -> ClChatBubble(
                    message = row.message.toBubble(),
                    isMine = row.message.outgoing,
                    avatarUrl = if (row.message.outgoing) state.myAvatar else state.peer.avatar,
                    onRetry = if (row.message.canResend) {
                        { onResend(row.message.localId) }
                    } else {
                        null
                    },
                )
            }
        }

        if (state.hasMore) {
            // 倒序列表里，写在最后的东西显示在最上面 —— 正好是「正在加载更早的消息」
            item(key = LOADING_MORE_KEY) {
                Text(
                    text = if (state.loadingMore) "正在加载更早的消息…" else "",
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = ClTheme.spacing.s),
                )
            }
        }
    }
}

/** 工具栏：语音/视频（开关控制）、送礼物（男用户才有）。都没有就整条不显示。 */
@Composable
private fun ChatTools(
    state: ChatUiState,
    onOpenGift: () -> Unit,
    onOpenCall: (CallKind) -> Unit,
) {
    val items = buildList {
        if (state.callEnabled) {
            add(ClChatToolItem(key = TOOL_VOICE, label = "语音通话", icon = ClIcons.mic))
            add(ClChatToolItem(key = TOOL_VIDEO, label = "视频通话", icon = ClIcons.video))
        }
        if (state.isMale) {
            add(ClChatToolItem(key = TOOL_GIFT, label = "送礼物", icon = ClIcons.gift))
        }
    }

    if (items.isEmpty()) {
        return
    }

    ClChatToolBar(
        items = items,
        onSelect = { key ->
            when (key) {
                TOOL_GIFT -> onOpenGift()
                TOOL_VIDEO -> onOpenCall(CallKind.VIDEO)
                else -> onOpenCall(CallKind.VOICE)
            }
        },
    )
}

/** 本地消息 → 气泡模型。图片优先显示**本地那份**（秒开、离线也能看，docs/10 第 2.5 节）。 */
private fun ChatMessage.toBubble(): ClChatMessage = ClChatMessage(
    id = clientMsgId,
    content = when (type) {
        ChatMessage.TYPE_IMAGE -> ClChatContent.Image
        ChatMessage.TYPE_GIFT -> ClChatContent.Gift
        else -> ClChatContent.Text
    },
    text = if (type == ChatMessage.TYPE_IMAGE) localMediaPath ?: content else content,
    status = when (status) {
        MessageStatus.SENDING -> ClChatStatus.Sending
        MessageStatus.FAILED -> ClChatStatus.Failed
        MessageStatus.SENT -> ClChatStatus.Sent
    },
)

private fun ChatRow.key(): String = when (this) {
    is ChatRow.Time -> "t-$label"
    is ChatRow.Message -> "m-${message.localId}"
}

private const val LOADING_MORE_KEY = "loading-more"

private const val TOOL_VOICE = "voice"

private const val TOOL_VIDEO = "video"

private const val TOOL_GIFT = "gift"
