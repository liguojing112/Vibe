package com.qichuang.changliao.core.data.local

import com.qichuang.changliao.core.network.ApiResult
import kotlinx.coroutines.flow.toList
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.Serializable
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder
import java.io.File

@Serializable
private data class FakeList(
    val items: List<String> = emptyList(),
    val total: Int = 0,
)

/**
 * 列表缓存（docs/10 第 3、5 节）：存取、解码失败当没缓存、切号清内存、60 秒节流。
 *
 * [LocalCache] 只依赖一个目录，所以这里用临时目录就能跑，不需要 Android 环境。
 */
class LocalCacheTest {

    @get:Rule
    val tmp = TemporaryFolder()

    private fun cache(dir: File = tmp.root) = LocalCache(dir)

    private val serializer = FakeList.serializer()

    private fun fileOf(key: String) = File(tmp.root, "$key.json")

    // ---------- 存取 ----------

    @Test
    fun `存进去能读回来`() {
        val store = cache()
        store.save("v1.test", FakeList(listOf("小满", "阿宁"), 2), serializer)

        assertEquals(FakeList(listOf("小满", "阿宁"), 2), store.load("v1.test", serializer))
    }

    @Test
    fun `没存过返回 null`() {
        assertNull(cache().load("v1.nothing", serializer))
    }

    @Test
    fun `真的落到了磁盘上`() {
        cache().save("v1.test", FakeList(listOf("小满")), serializer)

        assertTrue(fileOf("v1.test").isFile)
        assertTrue(fileOf("v1.test").readText().contains("小满"))
    }

    @Test
    fun `新实例能读到上个实例写的缓存`() {
        cache().save("v1.test", FakeList(listOf("小满")), serializer)

        assertEquals(FakeList(listOf("小满")), cache().load("v1.test", serializer))
    }

    @Test
    fun `原子写不留 tmp 残骸`() {
        cache().save("v1.test", FakeList(listOf("小满")), serializer)

        val leftovers = tmp.root.listFiles()?.filter { it.name.endsWith(".tmp") }.orEmpty()
        assertTrue("不该留下 .tmp：$leftovers", leftovers.isEmpty())
    }

    @Test
    fun `remove 之后读不到`() {
        val store = cache()
        store.save("v1.test", FakeList(listOf("小满")), serializer)

        store.remove("v1.test")

        assertNull(store.load("v1.test", serializer))
        assertFalse(fileOf("v1.test").exists())
    }

    // ---------- 解码失败 / 版本 ----------

    @Test
    fun `缓存文件被写坏了当没缓存`() {
        val store = cache()
        // 绕过 save 直接写坏内容（模拟老版本结构 / 写入失败）
        File(tmp.root, "v1.broken.json").writeText("{ 这不是合法 JSON")

        assertNull(store.load("v1.broken", serializer))
    }

    @Test
    fun `结构变了读不出来也当没缓存`() {
        val store = cache()
        // 老结构里 items 是个字符串，现在要的是数组 → 解码失败
        File(tmp.root, "v1.other.json").writeText("""{"items":"小满","total":1}""")

        assertNull(store.load("v1.other", serializer))
    }

    @Test
    fun `缺字段但有默认值时旧缓存还能读出来`() {
        val store = cache()
        // 数据类新增字段都带默认值，所以老缓存不会整体作废（docs/10 第 3 节规则 2）
        File(tmp.root, "v1.partial.json").writeText("""{"items":["小满"]}""")

        assertEquals(FakeList(listOf("小满"), 0), store.load("v1.partial", serializer))
    }

    @Test
    fun `启动时删掉旧版本的缓存文件`() {
        File(tmp.root, "v0.list.discover.1.json").writeText("{}")
        File(tmp.root, "v1.list.discover.1.json").writeText("{}")

        cache()

        assertFalse(File(tmp.root, "v0.list.discover.1.json").exists())
        assertTrue(File(tmp.root, "v1.list.discover.1.json").exists())
    }

    @Test
    fun `键里有斜杠也不会写到目录外面`() {
        cache().save("v1.a/b c", FakeList(listOf("x")), serializer)

        assertTrue(File(tmp.root, "v1.a_b_c.json").isFile)
    }

    // ---------- 切号清内存 ----------

    @Test
    fun `清内存后磁盘还在 能再读回来`() {
        val store = cache()
        store.save("v1.test", FakeList(listOf("小满")), serializer)

        store.clearMemory()

        assertEquals(FakeList(listOf("小满")), store.load("v1.test", serializer))
    }

    @Test
    fun `清内存后不会读到上一个账号的内存数据`() {
        val store = cache()
        store.save(CacheKeys.build("list.discover.推荐.1"), FakeList(listOf("女用户A")), serializer)

        store.clearMemory()

        assertNull(store.load(CacheKeys.build("list.discover.推荐.2"), serializer))
    }

    // ---------- 缓存键 ----------

    @Test
    fun `缓存键带版本号和 userId`() {
        assertEquals("v1.list.conversations.7", CacheKeys.conversations(7))
        assertEquals("v1.list.discover.推荐.7", CacheKeys.discover("推荐", 7))
        assertEquals("v1.list.square.7", CacheKeys.square(7))
        assertEquals("v1.list.visitors.7", CacheKeys.visitors(7))
        assertEquals("v1.list.notices.7", CacheKeys.notices(7))
        assertEquals("v1.profile.7.9", CacheKeys.profile(7, 9))
        assertEquals("v1.wallet.summary.7", CacheKeys.walletSummary(7))
        assertEquals("v1.blacklist.7", CacheKeys.blacklist(7))
    }

    @Test
    fun `不同账号的同一个列表不会串`() {
        val store = cache()
        store.save(CacheKeys.conversations(1), FakeList(listOf("A 的会话")), serializer)

        assertNull(store.load(CacheKeys.conversations(2), serializer))
    }

    // ---------- stale-while-revalidate ----------

    @Test
    fun `先发缓存再发新数据 并把新数据落盘`() = runTest {
        val store = cache()
        store.save("v1.test", FakeList(listOf("旧"), 1), serializer)

        val emitted = store.staleWhileRevalidate("v1.test", serializer) {
            ApiResult.Success(FakeList(listOf("新"), 2))
        }.toList()

        assertEquals(
            listOf<ApiResult<FakeList>>(
                ApiResult.Success(FakeList(listOf("旧"), 1)),
                ApiResult.Success(FakeList(listOf("新"), 2)),
            ),
            emitted,
        )
        assertEquals(FakeList(listOf("新"), 2), cache().load("v1.test", serializer))
    }

    @Test
    fun `没有缓存时只发网络数据`() = runTest {
        val emitted = cache().staleWhileRevalidate("v1.test", serializer) {
            ApiResult.Success(FakeList(listOf("新"), 1))
        }.toList()

        assertEquals(listOf<ApiResult<FakeList>>(ApiResult.Success(FakeList(listOf("新"), 1))), emitted)
    }

    @Test
    fun `有缓存时网络失败不发错误 页面继续显示缓存`() = runTest {
        val store = cache()
        store.save("v1.test", FakeList(listOf("旧")), serializer)

        val emitted = store.staleWhileRevalidate("v1.test", serializer) {
            ApiResult.NetworkError("网络不太好，请稍后重试")
        }.toList()

        assertEquals(listOf<ApiResult<FakeList>>(ApiResult.Success(FakeList(listOf("旧")))), emitted)
    }

    @Test
    fun `没有缓存时网络失败要把错误给页面`() = runTest {
        val emitted = cache().staleWhileRevalidate("v1.test", serializer) {
            ApiResult.NetworkError("网络不太好，请稍后重试")
        }.toList()

        assertEquals(
            listOf<ApiResult<FakeList>>(ApiResult.NetworkError("网络不太好，请稍后重试")),
            emitted,
        )
    }

    @Test
    fun `业务错误也按失败处理`() = runTest {
        val emitted = cache().staleWhileRevalidate("v1.test", serializer) {
            ApiResult.BizError(10001, "账号或密码错误")
        }.toList()

        assertEquals(listOf<ApiResult<FakeList>>(ApiResult.BizError(10001, "账号或密码错误")), emitted)
    }

    // ---------- 节流 ----------

    @Test
    fun `60 秒内切回来不重复请求`() = runTest {
        val store = cache()
        var calls = 0
        val fetch = suspend {
            calls += 1
            ApiResult.Success(FakeList(listOf("新"), calls))
        }

        store.staleWhileRevalidate("v1.test", serializer, fetch = fetch).toList()
        val second = store.staleWhileRevalidate("v1.test", serializer, fetch = fetch).toList()

        assertEquals(1, calls)
        assertEquals(listOf<ApiResult<FakeList>>(ApiResult.Success(FakeList(listOf("新"), 1))), second)
    }

    @Test
    fun `下拉刷新 force 后不受 60 秒限制`() = runTest {
        val store = cache()
        var calls = 0
        val fetch = suspend {
            calls += 1
            ApiResult.Success(FakeList(listOf("新"), calls))
        }

        store.staleWhileRevalidate("v1.test", serializer, fetch = fetch).toList()
        store.staleWhileRevalidate("v1.test", serializer, force = true, fetch = fetch).toList()

        assertEquals(2, calls)
    }

    @Test
    fun `没有缓存时不受节流影响 该请求还是请求`() = runTest {
        val store = cache()
        var calls = 0
        val fetch = suspend {
            calls += 1
            ApiResult.NetworkError("断网")
        }

        store.staleWhileRevalidate("v1.test", serializer, fetch = fetch).toList()
        store.staleWhileRevalidate("v1.test", serializer, fetch = fetch).toList()

        assertEquals(2, calls)
    }

    @Test
    fun `不同列表的节流互不影响`() = runTest {
        val store = cache()
        var calls = 0
        val fetch = suspend {
            calls += 1
            ApiResult.Success(FakeList(listOf("新"), calls))
        }

        store.staleWhileRevalidate("v1.a", serializer, fetch = fetch).toList()
        store.staleWhileRevalidate("v1.b", serializer, fetch = fetch).toList()

        assertEquals(2, calls)
    }
}
