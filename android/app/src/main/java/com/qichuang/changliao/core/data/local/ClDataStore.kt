package com.qichuang.changliao.core.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.qichuang.changliao.core.network.TokenStore
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking
import javax.inject.Inject
import javax.inject.Singleton

private val Context.clPreferences: DataStore<Preferences> by preferencesDataStore(name = "cl_prefs")

private val KEY_ACCESS_TOKEN = stringPreferencesKey("access_token")
private val KEY_REFRESH_TOKEN = stringPreferencesKey("refresh_token")
private val KEY_REMINDER = booleanPreferencesKey("reminder_enabled")
private val KEY_APP_CONFIG = stringPreferencesKey("app_config")

/**
 * DataStore 本地小状态（docs/02 第 4.3 节、docs/10 第 7 节）：token、筛选条件、消息提醒开关。
 *
 * token 额外在内存里缓存一份 —— OkHttp 拦截器要在非协程线程里**同步**读它。
 * 本期不做加密（客户确认，见 PROGRESS Q0-6）。
 */
@Singleton
class ClDataStore @Inject constructor(
    @ApplicationContext private val context: Context,
) : TokenStore {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    @Volatile
    private var cachedAccessToken: String? = null

    @Volatile
    private var cachedRefreshToken: String? = null

    @Volatile
    private var cachedAppConfig: String? = null

    init {
        // 启动时同步预热一次，保证第一个请求就带得上 token，首个界面就能用上配置
        runBlocking {
            val prefs = context.clPreferences.data.first()
            cachedAccessToken = prefs[KEY_ACCESS_TOKEN]
            cachedRefreshToken = prefs[KEY_REFRESH_TOKEN]
            cachedAppConfig = prefs[KEY_APP_CONFIG]
        }
    }

    override fun accessToken(): String? = cachedAccessToken

    override fun refreshToken(): String? = cachedRefreshToken

    override fun accessTokenFlow(): Flow<String?> =
        context.clPreferences.data.map { it[KEY_ACCESS_TOKEN] }

    override fun saveTokens(accessToken: String, refreshToken: String) {
        cachedAccessToken = accessToken
        cachedRefreshToken = refreshToken
        scope.launch {
            context.clPreferences.edit { prefs ->
                prefs[KEY_ACCESS_TOKEN] = accessToken
                prefs[KEY_REFRESH_TOKEN] = refreshToken
            }
        }
    }

    override fun clear() {
        cachedAccessToken = null
        cachedRefreshToken = null
        scope.launch {
            context.clPreferences.edit { prefs ->
                prefs.remove(KEY_ACCESS_TOKEN)
                prefs.remove(KEY_REFRESH_TOKEN)
            }
        }
    }

    /** 筛选条件（P07），按登录用户分开存（docs/10 第 7 节）。 */
    fun filtersFlow(userId: Long): Flow<String?> =
        context.clPreferences.data.map { it[stringPreferencesKey("filters_$userId")] }

    fun saveFilters(userId: Long, json: String) {
        scope.launch {
            context.clPreferences.edit { prefs ->
                prefs[stringPreferencesKey("filters_$userId")] = json
            }
        }
    }

    /** 消息提醒开关（P15，本地保存）。 */
    val reminderEnabled: Flow<Boolean> =
        context.clPreferences.data.map { it[KEY_REMINDER] ?: true }

    fun setReminderEnabled(enabled: Boolean) {
        scope.launch {
            context.clPreferences.edit { prefs -> prefs[KEY_REMINDER] = enabled }
        }
    }

    /**
     * 启动配置的本地缓存（docs/02 第 4.4 节）：接口拿不到时先把上次的配置用起来，
     * 保证「速配」这类开关不会因为断网而乱跳。配置与账号无关，退出登录不清。
     */
    fun appConfigJson(): String? = cachedAppConfig

    fun saveAppConfig(json: String) {
        cachedAppConfig = json
        scope.launch {
            context.clPreferences.edit { prefs -> prefs[KEY_APP_CONFIG] = json }
        }
    }
}
