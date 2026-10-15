package com.qichuang.changliao.feature.call

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.style.TextAlign
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClCallControl
import com.qichuang.changliao.core.designsystem.component.ClCallControls
import com.qichuang.changliao.core.designsystem.component.ClDemoLabel
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import kotlinx.coroutines.delay
import kotlin.math.ceil

/** 计费条上「已扣 X 币」用的时长向下取整。 */
private const val SECONDS_PER_MINUTE = 60

/**
 * P19 语音通话 / P20 视频通话（docs/01 P19、P20，设计图 19、20）。
 *
 * **全是本地假数据**：计时器是本地数秒，金币数按「每开始一分钟扣一次单价」现算 ——
 * **一分钱都不会真的扣**（`01` 第 4 节：点拨打只提示，不扣币）。
 *
 * 两颗页面唯一的区别是**画面**（语音是大头像、视频是对方画面铺满 + 右上角自己那一小块）
 * 和**控制区多不多「摄像头 / 翻转」**，所以共用一个页面，靠 [kind] 分。
 *
 * 静音 / 扬声器 / 摄像头 / 翻转只切换按钮状态（`01` P19/P20 的原文要求）。
 */
@Composable
fun CallInProgressScreen(
    kind: CallKind,
    peerName: String,
    peerAvatar: String?,
    price: Long,
    balance: Long,
    onEnd: (seconds: Int) -> Unit,
    modifier: Modifier = Modifier,
) {
    // 本地计时：进来就开始数，页面离开就没了（没有任何通话状态）
    var seconds by remember { mutableIntStateOf(0) }
    LaunchedEffect(Unit) {
        while (true) {
            delay(1_000)
            seconds++
        }
    }

    val muted = remember { mutableStateOf(false) }
    val speaker = remember { mutableStateOf(true) }
    val camera = remember { mutableStateOf(true) }
    val flipped = remember { mutableStateOf(false) }

    val bill = CallBill.of(seconds, price, balance)

    ClScaffold(modifier = modifier, containerColor = ClTheme.colors.callBgDark) {
        val toast = rememberClToast()

        Box(modifier = Modifier.fillMaxSize()) {
            // 视频通话：对方画面铺满整屏（假数据就是对方的头像）
            if (kind == CallKind.VIDEO) {
                AsyncImage(
                    model = peerAvatar,
                    contentDescription = peerName,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize(),
                )
            }

            Column(modifier = Modifier.fillMaxSize()) {
                ClDemoLabel(
                    modifier = Modifier.padding(
                        start = ClTheme.spacing.l,
                        top = ClTheme.spacing.l,
                    ),
                )

                CallTopBar(
                    title = peerName,
                    chipText = if (kind == CallKind.VIDEO) "示意画面" else kind.callLabel + " · 演示已接通",
                )

                Column(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth(),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                ) {
                    if (kind == CallKind.VOICE) {
                        AvatarStage(peerAvatar, peerName)
                        Text(
                            text = peerName,
                            style = ClTheme.typography.pageTitle,
                            color = ClTheme.colors.surface,
                            modifier = Modifier.padding(top = ClTheme.spacing.l),
                        )
                        Text(
                            text = "正在用声音，拉近一点距离",
                            style = ClTheme.typography.body,
                            color = ClTheme.colors.surface,
                            modifier = Modifier.padding(top = ClTheme.spacing.s),
                        )
                        Text(
                            text = CallFormat.duration(seconds),
                            style = ClTheme.typography.amountLarge,
                            color = ClTheme.colors.surface,
                            modifier = Modifier.padding(top = ClTheme.spacing.m),
                        )
                    }

                    CallChip(text = bill.stripText)

                    // 视频通话：自己那一小块画面（设计图 20 右上角）
                    if (kind == CallKind.VIDEO) {
                        Text(
                            text = CallFormat.duration(seconds),
                            style = ClTheme.typography.amountLarge,
                            color = ClTheme.colors.surface,
                            modifier = Modifier.padding(top = ClTheme.spacing.m),
                        )
                    }
                }

                ClCallControls(
                    controls = controlsOf(kind, muted.value, speaker.value, camera.value, flipped.value),
                    onSelect = { key ->
                        when (key) {
                            KEY_MUTE -> muted.value = !muted.value
                            KEY_SPEAKER -> speaker.value = !speaker.value
                            KEY_CAMERA -> camera.value = !camera.value
                            KEY_FLIP -> flipped.value = !flipped.value
                            // 礼物：**不真的送**。送出去是要扣钱的，而通话根本没接通 ——
                            // 和 P17 的「确认拨打」一个口径，只提示
                            KEY_GIFT -> toast.show("功能暂未开放")
                        }
                    },
                    onHangup = { onEnd(seconds) },
                    modifier = Modifier.padding(bottom = ClTheme.spacing.xl),
                )

                Text(
                    text = "正在演示计时与金币扣除",
                    style = ClTheme.typography.micro,
                    color = ClTheme.colors.muted,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = ClTheme.spacing.xxl),
                )
            }

            // 视频通话才要：右上角「我的画面」（自己那份是假的，用对方头像示意不礼貌，
            // 所以画一个纯色占位 + 文案，和设计图的 PiP 位置一致）
            if (kind == CallKind.VIDEO) {
                SelfPreview(
                    label = if (camera.value) "我的画面" else "摄像头已关",
                    modifier = Modifier
                        .align(Alignment.TopEnd)
                        .padding(
                            top = ClTheme.size.topBarHeight + ClTheme.spacing.xxl,
                            end = ClTheme.spacing.l,
                        ),
                )
            }
        }
    }
}

/** 语音通话正中的大头像（设计图 19）。 */
@Composable
private fun AvatarStage(avatar: String?, name: String) {
    ClAvatar(
        url = avatar,
        size = ClTheme.size.avatarL,
        contentDescription = name,
    )
}

/** 视频通话右上角的「我的画面」小窗（设计图 20）。 */
@Composable
private fun SelfPreview(label: String, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .size(width = ClTheme.size.avatarL, height = ClTheme.size.avatarM)
            .clip(ClTheme.shapes.cardSmall)
            .background(ClTheme.colors.surface.copy(alpha = DARK_CHIP_ALPHA)),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            text = label,
            style = ClTheme.typography.micro,
            color = ClTheme.colors.surface,
            textAlign = TextAlign.Center,
        )
    }
}

internal const val KEY_MUTE = "mute"
internal const val KEY_SPEAKER = "speaker"
internal const val KEY_CAMERA = "camera"
internal const val KEY_FLIP = "flip"
internal const val KEY_GIFT = "gift"

/** 控制区按钮：语音三颗、视频四颗（设计图 19/20）。 */
private fun controlsOf(
    kind: CallKind,
    muted: Boolean,
    speaker: Boolean,
    camera: Boolean,
    flipped: Boolean,
): List<ClCallControl> = buildList {
    add(ClCallControl(KEY_MUTE, "静音", ClIcons.mic, active = muted))
    if (kind == CallKind.VOICE) {
        add(ClCallControl(KEY_SPEAKER, "扬声器", ClIcons.speaker, active = speaker))
    } else {
        add(ClCallControl(KEY_CAMERA, "关摄像头", ClIcons.video, active = !camera))
        add(ClCallControl(KEY_FLIP, "翻转", ClIcons.flip, active = flipped))
    }
    add(ClCallControl(KEY_GIFT, "礼物", ClIcons.gift))
}

/**
 * 演示账单（P19/P20 的计费条和 P21 的账单都从它来）。
 *
 * 口径和 `docs/06` 一致：**每开始一分钟扣一次单价**（不足一分钟按一分钟算）。
 * 纯展示，不产生任何真实扣费。
 */
data class CallBill(
    val seconds: Int,
    val price: Long,
    val balance: Long,
) {
    /** 计费分钟数：开始通话就算 1 分钟（`ceil`）。 */
    val minutes: Int get() = ceil(seconds.toDouble() / SECONDS_PER_MINUTE).toInt().coerceAtLeast(1)

    val charged: Long get() = price * minutes

    val balanceAfter: Long get() = balance - charged

    /** 「60币/分钟 · 已扣 60 币 · 余额 300 币」（设计图 19/20 的计费条）。 */
    val stripText: String
        get() = "${price}币/分钟 · 已扣 $charged 币 · 余额 $balanceAfter 币"

    companion object {
        fun of(seconds: Int, price: Long, balance: Long) = CallBill(seconds, price, balance)
    }
}

/** 「01:05」。 */
object CallFormat {
    fun duration(seconds: Int): String {
        val minutes = seconds / SECONDS_PER_MINUTE
        val rest = seconds % SECONDS_PER_MINUTE

        return "%02d:%02d".format(minutes, rest)
    }
}
