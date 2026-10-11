package com.qichuang.changliao.core.designsystem.theme

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * docs/03 第 2~4 节的每个 token 都必须存在、且数值与文档一致。
 */
class ClThemeTokensTest {

    @Test
    fun `颜色 token 与 docs 03 第 2 节一致`() {
        val c = ClLightColors

        assertEquals(Color(0xFFFFD839), c.primary)
        assertEquals(Color(0xFF2F2615), c.onPrimary)
        assertEquals(Color(0xFFFFF0C4), c.primarySoft)
        assertEquals(Color(0xFFFFF5CF), c.primaryChip)
        assertEquals(Color(0xFFFFC944), c.primaryChipBorder)
        assertEquals(Color(0xFF9B7218), c.primaryText)
        assertEquals(Color(0xFFFFC425), c.accentUnderline)

        assertEquals(Color(0xFF24231F), c.ink)
        assertEquals(Color(0xFF77736C), c.inkSecondary)
        assertEquals(Color(0xFFAAA69F), c.muted)
        assertEquals(Color(0xFFF1EDE7), c.line)

        assertEquals(Color(0xFFFFFAF1), c.bgCream)
        assertEquals(Color(0xFFF8F8F7), c.bgPage)
        assertEquals(Color(0xFFFAF8F5), c.bgChat)
        assertEquals(Color(0xFFF5F3F0), c.bgInput)
        assertEquals(Color(0xFFFFFFFF), c.surface)

        assertEquals(Color(0xFFFF6D91), c.pink)
        assertEquals(Color(0xFFFF982A), c.orange)
        assertEquals(Color(0xFF33C890), c.green)
        assertEquals(Color(0xFF2EDB73), c.onlineDot)
        assertEquals(Color(0xFFFB3E48), c.danger)
        assertEquals(Color(0xFFFC6266), c.hangup)

        assertEquals(Color(0xFFFFF0BD), c.coinBg)
        assertEquals(Color(0xFF987015), c.coinText)
        assertEquals(Color(0xFFB78A31), c.incomeText)

        assertEquals(Color(0xFFDDF9E9), c.tagGreenBg)
        assertEquals(Color(0xFF22B868), c.tagGreenText)
        assertEquals(Color(0xFFFFF0CF), c.tagGoldBg)
        assertEquals(Color(0xFFDB8B1D), c.tagGoldText)
        assertEquals(Color(0xFFFFE6EE), c.tagPinkBg)
        assertEquals(Color(0xFFE95879), c.tagPinkText)

        assertEquals(Color(0xFFFFDE5E), c.bubbleMine)
        assertEquals(Color(0xFFFFFFFF), c.bubbleOther)

        assertEquals(Color(0xFFFFF5D9), c.feeStripBg)
        assertEquals(Color(0xFF9B781E), c.feeStripText)

        assertEquals(Color(0xFF2A2928), c.callBgDark)
        // #201B12 透明度 43%
        assertEquals(Color(0x6E201B12), c.mask)
    }

    @Test
    fun `间距 token 与 docs 03 第 4 节一致`() {
        val s = ClSpacings
        assertEquals(2.dp, s.xxs)
        assertEquals(4.dp, s.xs)
        assertEquals(8.dp, s.s)
        assertEquals(12.dp, s.m)
        assertEquals(16.dp, s.l)
        assertEquals(20.dp, s.xl)
        assertEquals(24.dp, s.xxl)
        assertEquals(32.dp, s.xxxl)
    }

    @Test
    fun `尺寸 token 与 docs 03 第 4 节一致`() {
        val z = ClSizes
        assertEquals(56.dp, z.topBarHeight)
        assertEquals(68.dp, z.bottomBarHeight)
        assertEquals(46.dp, z.buttonHeight)
        assertEquals(32.dp, z.buttonHeightSmall)
        assertEquals(44.dp, z.inputHeight)
        assertEquals(55.dp, z.menuRowHeight)
        assertEquals(72.dp, z.avatarL)
        assertEquals(60.dp, z.avatarM)
        assertEquals(50.dp, z.avatarS)
        assertEquals(30.dp, z.avatarXS)
        assertEquals(22.dp, z.iconM)
        assertEquals(17.dp, z.iconS)
        assertEquals(13.dp, z.onlineDot)
        assertEquals(290.dp, z.coverHeight)
    }

    @Test
    fun `文字 token 字号与 docs 03 第 3 节一致`() {
        val t = ClTextStyles
        assertEquals(25.sp, t.pageTitle.fontSize)
        assertEquals(17.sp, t.headerTitle.fontSize)
        assertEquals(17.sp, t.sectionTitle.fontSize)
        assertEquals(16.sp, t.cardTitle.fontSize)
        assertEquals(14.sp, t.body.fontSize)
        assertEquals(13.sp, t.bodySmall.fontSize)
        assertEquals(12.sp, t.caption.fontSize)
        assertEquals(11.sp, t.tiny.fontSize)
        assertEquals(10.sp, t.micro.fontSize)
        assertEquals(39.sp, t.amountLarge.fontSize)
        assertEquals(27.sp, t.amountMedium.fontSize)
        assertEquals(14.sp, t.button.fontSize)
        assertEquals(12.sp, t.buttonSmall.fontSize)
    }

    @Test
    fun `阴影 token 与 docs 03 第 4 节一致`() {
        assertEquals(5.dp, ClElevations.cardOffsetY)
        assertEquals(20.dp, ClElevations.cardBlur)
    }
}
