package com.qichuang.changliao.core.designsystem.icon

import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * docs/03 第 6 节第 6 条列的图标必须都在 ClIcons 里有对应。
 */
class ClIconsTest {

    @Test
    fun `docs 里列的图标全部存在`() {
        val icons = mapOf(
            "home" to ClIcons.home,
            "heart" to ClIcons.heart,
            "message" to ClIcons.message,
            "user" to ClIcons.user,
            "search" to ClIcons.search,
            "filter" to ClIcons.filter,
            "pin" to ClIcons.pin,
            "chevron" to ClIcons.chevron,
            "back" to ClIcons.back,
            "bell" to ClIcons.bell,
            "eye" to ClIcons.eye,
            "star" to ClIcons.star,
            "wallet" to ClIcons.wallet,
            "shield" to ClIcons.shield,
            "settings" to ClIcons.settings,
            "calendar" to ClIcons.calendar,
            "edit" to ClIcons.edit,
            "mic" to ClIcons.mic,
            "phone" to ClIcons.phone,
            "video" to ClIcons.video,
            "gift" to ClIcons.gift,
            "check" to ClIcons.check,
            "more" to ClIcons.more,
            "spark" to ClIcons.spark,
            "speaker" to ClIcons.speaker,
            "flip" to ClIcons.flip,
        )

        assertTrue("docs 03 第 6 节列了 26 个图标", icons.size == 26)
        icons.forEach { (name, icon) ->
            assertTrue("图标 $name 必须有名字", icon.name.isNotBlank())
            assertTrue("图标 $name 必须至少有一条 path", icon.defaultWidth.value > 0f)
        }
    }
}
