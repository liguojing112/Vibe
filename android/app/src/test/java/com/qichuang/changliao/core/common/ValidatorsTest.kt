package com.qichuang.changliao.core.common

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * 校验规则要和 `RegisterRequest.php` 一致（docs/01 P00、P14、P29）。
 */
class ValidatorsTest {

    @Test
    fun `手机号`() {
        assertTrue(Validators.isPhone("13800138000"))
        assertTrue(Validators.isPhone("19912345678"))
        assertTrue(Validators.isPhone("15000000000"))

        assertFalse(Validators.isPhone("12800138000")) // 第二位不能是 2
        assertFalse(Validators.isPhone("10800138000")) // 第二位不能是 0
        assertFalse(Validators.isPhone("1380013800")) // 10 位
        assertFalse(Validators.isPhone("138001380000")) // 12 位
        assertFalse(Validators.isPhone("1380013800a"))
        assertFalse(Validators.isPhone("23800138000"))
        assertFalse(Validators.isPhone(""))
    }

    @Test
    fun `密码必须同时有字母和数字`() {
        assertTrue(Validators.isPassword("abc123"))
        assertTrue(Validators.isPassword("a12345"))
        assertTrue(Validators.isPassword("Abcdefg12345678901")) // 18 位
        assertTrue(Validators.isPassword("Abcdefg1234567890123")) // 20 位
        assertTrue(Validators.isPassword("A1b2c3"))

        assertFalse(Validators.isPassword("abcde")) // 5 位
        assertFalse(Validators.isPassword("abc12")) // 5 位且少数字
        assertFalse(Validators.isPassword("Abcdefg12345678901234")) // 21 位
        assertFalse(Validators.isPassword("abcdefgh")) // 全字母
        assertFalse(Validators.isPassword("12345678")) // 全数字
        assertFalse(Validators.isPassword("")) // 空
    }

    @Test
    fun `昵称 2 到 12 个字`() {
        assertTrue(Validators.isNickname("小明"))
        assertTrue(Validators.isNickname("一二三四五六七八九十十一")) // 12 字
        assertTrue(Validators.isNickname("ab"))

        assertFalse(Validators.isNickname("我")) // 1 字
        assertFalse(Validators.isNickname("")) // 空
        assertFalse(Validators.isNickname("一二三四五六七八九十十一二")) // 13 字
    }

    @Test
    fun `身份证校验位`() {
        assertTrue(Validators.isIdCard("11010519491231002X"))
        assertTrue(Validators.isIdCard("440306199001011236"))
        assertTrue(Validators.isIdCard("11010519491231002x")) // 小写 x 也认（校验位是 X 的那个号）
    }

    @Test
    fun `身份证位数和字符不对就不过`() {
        assertFalse(Validators.isIdCard("11010519491231002")) // 17 位
        assertFalse(Validators.isIdCard("1101051949123100211")) // 19 位
        assertFalse(Validators.isIdCard("11010519491231002A")) // 末位只能数字或 X
        assertFalse(Validators.isIdCard("11010519491231002Y"))
        assertFalse(Validators.isIdCard("1101051949123100XX")) // 前 17 位必须数字
        assertFalse(Validators.isIdCard(""))
    }

    @Test
    fun `身份证校验位错了就不过`() {
        // 把合法的 11010519491231002X 改掉校验位
        assertFalse(Validators.isIdCard("110105194912310021"))
        assertFalse(Validators.isIdCard("110105194912310029"))
        // 440306199001011236 → 改一位数字，校验位就对不上了
        assertFalse(Validators.isIdCard("440306199001011336"))
    }
}
