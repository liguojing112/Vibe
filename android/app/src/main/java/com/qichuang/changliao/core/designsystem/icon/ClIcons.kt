package com.qichuang.changliao.core.designsystem.icon

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.AccountBalanceWallet
import androidx.compose.material.icons.rounded.ArrowBack
import androidx.compose.material.icons.rounded.AutoAwesome
import androidx.compose.material.icons.rounded.CalendarMonth
import androidx.compose.material.icons.rounded.Cameraswitch
import androidx.compose.material.icons.rounded.CardGiftcard
import androidx.compose.material.icons.rounded.ChatBubble
import androidx.compose.material.icons.rounded.Check
import androidx.compose.material.icons.rounded.ChevronRight
import androidx.compose.material.icons.rounded.Edit
import androidx.compose.material.icons.rounded.Favorite
import androidx.compose.material.icons.rounded.FilterList
import androidx.compose.material.icons.rounded.Home
import androidx.compose.material.icons.rounded.Mic
import androidx.compose.material.icons.rounded.MoreHoriz
import androidx.compose.material.icons.rounded.Notifications
import androidx.compose.material.icons.rounded.Person
import androidx.compose.material.icons.rounded.Phone
import androidx.compose.material.icons.rounded.Place
import androidx.compose.material.icons.rounded.Search
import androidx.compose.material.icons.rounded.Settings
import androidx.compose.material.icons.rounded.Shield
import androidx.compose.material.icons.rounded.Star
import androidx.compose.material.icons.rounded.Videocam
import androidx.compose.material.icons.rounded.Visibility
import androidx.compose.material.icons.rounded.VolumeUp
import androidx.compose.ui.graphics.vector.ImageVector

/**
 * 全项目所有图标（docs/03 第 6 节第 6 条）。
 *
 * **页面里只能用 `ClIcons.xxx`**，不准直接用 `Icons.Rounded.*` —— 这样换图标风格只改这里。
 * 基于 Material Icons Rounded（线条风格）。
 */
object ClIcons {
    val home: ImageVector = Icons.Rounded.Home
    val heart: ImageVector = Icons.Rounded.Favorite
    val message: ImageVector = Icons.Rounded.ChatBubble
    val user: ImageVector = Icons.Rounded.Person
    val search: ImageVector = Icons.Rounded.Search
    val filter: ImageVector = Icons.Rounded.FilterList
    val pin: ImageVector = Icons.Rounded.Place
    val chevron: ImageVector = Icons.Rounded.ChevronRight
    val back: ImageVector = Icons.Rounded.ArrowBack
    val bell: ImageVector = Icons.Rounded.Notifications
    val eye: ImageVector = Icons.Rounded.Visibility
    val star: ImageVector = Icons.Rounded.Star
    val wallet: ImageVector = Icons.Rounded.AccountBalanceWallet
    val shield: ImageVector = Icons.Rounded.Shield
    val settings: ImageVector = Icons.Rounded.Settings
    val calendar: ImageVector = Icons.Rounded.CalendarMonth
    val edit: ImageVector = Icons.Rounded.Edit
    val mic: ImageVector = Icons.Rounded.Mic
    val phone: ImageVector = Icons.Rounded.Phone
    val video: ImageVector = Icons.Rounded.Videocam
    val gift: ImageVector = Icons.Rounded.CardGiftcard
    val check: ImageVector = Icons.Rounded.Check
    val more: ImageVector = Icons.Rounded.MoreHoriz
    val spark: ImageVector = Icons.Rounded.AutoAwesome
    val speaker: ImageVector = Icons.Rounded.VolumeUp
    val flip: ImageVector = Icons.Rounded.Cameraswitch
}
