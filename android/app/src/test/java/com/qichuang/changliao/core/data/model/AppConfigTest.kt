package com.qichuang.changliao.core.data.model

import com.qichuang.changliao.core.network.clJson
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * `/app/config` 真实返回的字段名是下划线风格，解析错了会静默用默认值（开关就永远关着），
 * 所以这里拿接口实际返回值钉住契约。
 */
class AppConfigTest {

    private val realPayload = """
        {"call_enabled":false,
         "compliance_disclosure":"本平台为付费聊天服务，部分女用户为平台签约陪聊人员。",
         "service":{"wechat":"","qrcode_url":"","phone":""},
         "chat_max_length":500,
         "image_max_mb":5,
         "cities":["北京","上海"],
         "tags":[{"id":1,"name":"旅行"}],
         "version":"1.0.0"}
    """.trimIndent()

    private fun decode(json: String) = clJson.decodeFromString(AppConfig.serializer(), json)

    @Test
    fun `解析接口真实返回`() {
        val config = decode(realPayload)

        assertFalse(config.callEnabled)
        assertEquals("1.0.0", config.version)
        assertEquals(500, config.chatMaxLength)
        assertEquals(5, config.imageMaxMb)
        assertEquals(listOf("北京", "上海"), config.cities)
        assertEquals(1L, config.tags.first().id)
        assertEquals("旅行", config.tags.first().name)
        assertTrue(config.complianceDisclosure.isNotEmpty())
    }

    @Test
    fun `开启语音视频开关能读到`() {
        assertTrue(decode("""{"call_enabled":true}""").callEnabled)
    }

    @Test
    fun `服务端多返回字段不会解析失败`() {
        val config = decode("""{"call_enabled":true,"brand_new_field":{"a":1}}""")

        assertTrue(config.callEnabled)
    }

    @Test
    fun `缺字段时走默认值且开关按关闭处理`() {
        val config = decode("""{"version":"1.0.0"}""")

        assertFalse(config.callEnabled)
        assertEquals(0, config.chatMaxLength)
        assertTrue(config.cities.isEmpty())
        assertEquals("", config.service.wechat)
    }
}
