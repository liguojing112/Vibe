package com.qichuang.changliao.core.data.local

import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.clJson
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.serialization.KSerializer
import java.io.File
import java.util.concurrent.ConcurrentHashMap
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 列表缓存的键（docs/10 第 5 节）：`v1.list.discover.<tab>.<userId>` 这种形状。
 *
 * 两件事必须由这里保证，页面不要自己拼字符串：
 * 1. **带版本号**（docs/10 第 3 节规则 3）—— 数据类结构改了就把 [VERSION] +1，旧缓存自动作废；
 * 2. **带 userId**（规则 4）—— 只写当前登录用户的，切号不会串数据。
 */
object CacheKeys {

    /** 缓存结构版本。改任何会写进缓存的数据类结构时 +1。 */
    const val VERSION = 1

    fun conversations(userId: Long): String = build("list.conversations.$userId")

    /**
     * 发现页第一页。多一个 [signature]：**筛选条件不一样时缓存不能混用**，
     * 否则用户改了筛选会先闪一眼旧结果。没筛选时就是 docs/10 第 5 节的原样键。
     */
    fun discover(tab: String, userId: Long, signature: String = ""): String =
        build("list.discover.$tab.$userId" + if (signature.isEmpty()) "" else ".$signature")

    fun square(userId: Long): String = build("list.square.$userId")

    fun visitors(userId: Long): String = build("list.visitors.$userId")

    fun notices(userId: Long): String = build("list.notices.$userId")

    fun profile(viewerId: Long, targetId: Long): String = build("profile.$viewerId.$targetId")

    fun walletSummary(userId: Long): String = build("wallet.summary.$userId")

    fun blacklist(userId: Long): String = build("blacklist.$userId")

    internal fun build(base: String): String = "v$VERSION.$base"
}

/**
 * 列表 / 页面缓存（docs/10 第 3、5 节）：把接口结果按 key 落成 JSON 文件，
 * 页面打开时先渲染缓存（秒开、不转圈），再静默刷新。
 *
 * 规则（逐条对齐 docs/10 第 3 节）：
 * 1. 不设 TTL —— 缓存只为「马上有内容」，最终以接口为准；
 * 2. 解码失败当作没缓存（数据类新增字段要有默认值，否则旧缓存整体读不出来）；
 * 3. 键带版本号（见 [CacheKeys]）；
 * 4. 只写当前登录用户的（键带 userId）；
 * 5. [clearMemory] 给切号 / 退出登录用；
 * 6. 同一列表 60 秒内切回来不重复请求，下拉刷新（`force = true`）不受限。
 *
 * 写盘是**原子**的：先写 `.tmp` 再改名，避免写到一半被杀导致半个 JSON。
 */
@Singleton
class LocalCache @Inject constructor(
    @CacheDir private val rootDir: File,
) {

    /** 内存里留一份原始 JSON，避免同一页来回切时反复读盘。 */
    private val memory = ConcurrentHashMap<String, String>()

    private val lastRefreshedAt = ConcurrentHashMap<String, Long>()

    init {
        pruneOldVersions()
    }

    fun <T> save(key: String, value: T, serializer: KSerializer<T>) {
        val text = clJson.encodeToString(serializer, value)
        memory[key] = text
        writeAtomically(fileFor(key), text)
    }

    fun <T> load(key: String, serializer: KSerializer<T>): T? {
        val text = memory[key] ?: readFromDisk(key)?.also { memory[key] = it } ?: return null
        return runCatching { clJson.decodeFromString(serializer, text) }.getOrNull()
    }

    fun remove(key: String) {
        memory.remove(key)
        lastRefreshedAt.remove(key)
        fileFor(key).delete()
    }

    /** 切号 / 退出登录时清空内存（docs/10 第 3 节规则 4、5）。磁盘文件按 userId 分键，留着不影响别人。 */
    fun clearMemory() {
        memory.clear()
        lastRefreshedAt.clear()
    }

    /**
     * stale-while-revalidate 的通用封装：**先发缓存，再发网络**。
     *
     * - 有缓存 → 先 `Success(缓存)`，页面立刻有内容；
     * - 网络成功 → 覆盖缓存并发 `Success(新数据)`；
     * - 网络失败**且有缓存** → 不发错误（页面继续显示缓存内容，符合「静默刷新」）；
     *   网络失败**且没缓存** → 把错误发给页面（该转错误页就转）。
     *
     * @param force 下拉刷新传 true，跳过 60 秒节流、始终请求
     */
    fun <T> staleWhileRevalidate(
        key: String,
        serializer: KSerializer<T>,
        force: Boolean = false,
        fetch: suspend () -> ApiResult<T>,
    ): Flow<ApiResult<T>> = flow {
        val cached = load(key, serializer)
        if (cached != null) {
            emit(ApiResult.Success(cached))
        }

        if (!force && cached != null && isRecentlyRefreshed(key)) {
            return@flow
        }

        when (val fresh = fetch()) {
            is ApiResult.Success -> {
                save(key, fresh.data, serializer)
                lastRefreshedAt[key] = System.currentTimeMillis()
                emit(fresh)
            }

            else -> if (cached == null) {
                emit(fresh)
            }
        }
    }

    private fun isRecentlyRefreshed(key: String): Boolean {
        val at = lastRefreshedAt[key] ?: return false
        return System.currentTimeMillis() - at < THROTTLE_WINDOW_MS
    }

    private fun fileFor(key: String): File = File(rootDir, sanitize(key) + ".json")

    private fun readFromDisk(key: String): String? {
        val file = fileFor(key)
        if (!file.isFile) {
            return null
        }
        return runCatching { file.readText() }.getOrNull()
    }

    private fun writeAtomically(file: File, text: String) {
        runCatching {
            rootDir.mkdirs()
            val tmp = File(rootDir, file.name + ".tmp")
            tmp.writeText(text)
            if (!tmp.renameTo(file)) {
                file.writeText(text)
                tmp.delete()
            }
        }
    }

    /** 版本号变了以后，上一版的缓存文件不会再被读到，直接删掉免得越堆越多。 */
    private fun pruneOldVersions() {
        runCatching {
            rootDir.mkdirs()
            val keepPrefix = "v${CacheKeys.VERSION}."
            rootDir.listFiles()
                ?.filter { it.name.endsWith(".json") && !it.name.startsWith(keepPrefix) }
                ?.forEach { it.delete() }
        }
    }

    /** 键里可能有 `/`、空格之类，转义掉免得写到缓存目录外面去。 */
    private fun sanitize(key: String): String = key.replace(NON_FILE_CHAR, "_")

    companion object {
        /** 同一列表 60 秒内切回来不重复请求（docs/10 第 3 节规则 6）。 */
        const val THROTTLE_WINDOW_MS = 60_000L

        private val NON_FILE_CHAR = Regex("[^A-Za-z0-9._-]")
    }
}
