package com.qichuang.changliao.feature.discover

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.DiscoverRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.navigation.DiscoverTabIntent
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 发现页（docs/01 P01，也就是客户说的「广场」）。
 *
 * 进入页面走 **stale-while-revalidate**：先渲染本地缓存（秒开、不转圈），再静默刷新；
 * 下拉刷新和切 Tab 传 `force = true`，不受 60 秒节流限制。
 *
 * 页面上的价格、在线/新人/已认证标记**全是服务端算好的**，这里只负责显示（铁律 6）。
 */
@HiltViewModel
class DiscoverViewModel @Inject constructor(
    private val discoverRepository: DiscoverRepository,
    private val userRepository: UserRepository,
    private val discoverTabIntent: DiscoverTabIntent,
    appConfigRepository: AppConfigRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(DiscoverUiState())

    val uiState: StateFlow<DiscoverUiState> = _uiState.asStateFlow()

    /** 当前登录用户 id：缓存键要按用户分（docs/10 第 3 节规则 4），所以先拿到它再拉列表。 */
    private var userId: Long = 0

    private var listJob: Job? = null

    init {
        // `feature.call_enabled`（docs/01 第 4 节）打开时多出「视频/语音」两个 Tab；
        // 关掉时如果正停在其中一个上，退回「推荐」—— 不然会停在一个已经不该存在的 Tab 上。
        viewModelScope.launch {
            appConfigRepository.config.collect { config ->
                applyCallEnabled(config)
            }
        }

        // P03 的「附近的人」入口会请求切到「同城」（docs/01 P03）。
        // 请求可能在我们建好之前就写下了 —— StateFlow 会把当前值补给我们，所以不会漏。
        viewModelScope.launch {
            discoverTabIntent.pending.collect { apiValue ->
                if (apiValue == null) {
                    return@collect
                }
                DiscoverTab.entries
                    .firstOrNull { it.apiValue == apiValue && it in _uiState.value.tabs }
                    ?.let(::onTabSelected)
                discoverTabIntent.consume()
            }
        }
    }

    /**
     * 每次进入页面都调一次（含从 P07 筛选页返回）。
     *
     * 会重新读一遍本地筛选条件：**筛选改动后在 P01 立即生效**；
     * 没改动时这一步只是触发一次静默刷新，60 秒内的重复进入不会再发请求（`LocalCache` 节流）。
     */
    fun enter() {
        viewModelScope.launch {
            val profile = ensureCurrentUser() ?: return@launch
            userId = profile.id

            val savedFilter = discoverRepository.filter(userId)
            val filterChanged = savedFilter != _uiState.value.filter
            _uiState.update { it.copy(filter = savedFilter) }

            load(_uiState.value.tab, force = filterChanged)
        }
    }

    fun onTabSelected(tab: DiscoverTab) {
        // 不在可见列表里的 Tab 一律忽略（开关关着时有人请求切「语音」tab 就走这里）
        if (tab == _uiState.value.tab || tab !in _uiState.value.tabs) {
            return
        }
        // 切 Tab 当成一次刷新：要立刻看到这个 Tab 的内容，不等节流
        _uiState.update { it.copy(tab = tab, users = emptyList(), hasMore = false, error = null) }
        load(tab, force = true)
    }

    /**
     * 下拉刷新：**把「我」的资料也重拉一遍**，不然客服刚充完值、用户下拉一下还是旧数字
     * （下拉原本只重拉列表，T9.1 走查时就是这么发现的）。
     */
    fun onRefresh() {
        viewModelScope.launch {
            ensureCurrentUser()
            load(_uiState.value.tab, force = true)
        }
    }

    fun onLoadMore() {
        val state = _uiState.value
        if (state.loadingMore || state.refreshing || !state.hasMore || state.loadMoreError != null) {
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(loadingMore = true) }
            val nextPage = state.users.size / PAGE_SIZE + 1

            when (val result = discoverRepository.more(state.tab.apiValue, state.filter, nextPage)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        users = it.users + result.data.list,
                        hasMore = result.data.hasMore,
                        loadingMore = false,
                        loadMoreError = null,
                    )
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(loadingMore = false, loadMoreError = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(loadingMore = false, loadMoreError = result.message)
                }
            }
        }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    // ---------- 内部 ----------

    private fun load(tab: DiscoverTab, force: Boolean) {
        if (userId <= 0) {
            return
        }

        listJob?.cancel()
        listJob = viewModelScope.launch {
            val hasContent = _uiState.value.users.isNotEmpty()
            _uiState.update {
                it.copy(
                    // 已经有内容时不转圈（静默刷新），没有内容才显示整页加载
                    loading = !hasContent,
                    refreshing = force && hasContent,
                    error = null,
                    loadMoreError = null,
                )
            }

            val filter = _uiState.value.filter
            discoverRepository.firstPage(tab.apiValue, userId, filter, force).collect { result ->
                when (result) {
                    is ApiResult.Success -> _uiState.update {
                        it.copy(
                            users = result.data.list,
                            hasMore = result.data.hasMore,
                            loading = false,
                            refreshing = false,
                            error = null,
                        )
                    }

                    is ApiResult.BizError -> _uiState.update {
                        it.copy(
                            loading = false,
                            refreshing = false,
                            // 有缓存时静默失败不会走到这里；走到这里说明确实没内容可显示
                            error = if (it.users.isEmpty()) result.message else null,
                            toast = if (it.users.isEmpty()) null else result.message,
                        )
                    }

                    is ApiResult.NetworkError -> _uiState.update {
                        it.copy(
                            loading = false,
                            refreshing = false,
                            error = if (it.users.isEmpty()) result.message else null,
                            toast = if (it.users.isEmpty()) null else result.message,
                        )
                    }
                }
            }
        }
    }

    /**
     * 拿到「我」的资料：顶部要显示性别对应的余额，列表缓存键也要用它。
     *
     * **每次进页面都真拉一次 `/user/me`**，不是「有缓存就用缓存」——
     * 余额是钱（`WalletRepository` 的注释里也是这个口径）：客服在后台刚充完，
     * 用户手上就是这一页，**不能等他切到别的页面才更新**。
     * T9.1 走查就是这么发现的：充值成功后 App 一直显示 0，下拉刷新也不动。
     *
     * 拉失败**不整页报错**：有缓存就继续用缓存（页面照常可用），没有缓存才报错。
     */
    private suspend fun ensureCurrentUser(): UserProfile? {
        return when (val result = userRepository.me()) {
            is ApiResult.Success -> {
                applyBalance(result.data)

                result.data
            }

            is ApiResult.BizError -> cachedOrError(result.message)

            is ApiResult.NetworkError -> cachedOrError(result.message)
        }
    }

    private fun cachedOrError(message: String): UserProfile? {
        val cached = userRepository.currentUser.value
        if (cached != null) {
            applyBalance(cached)

            return cached
        }

        _uiState.update { it.copy(loading = false, error = message) }

        return null
    }

    /** 男用户显示金币、女用户显示积分（docs/01 P01）。 */
    private fun applyBalance(profile: UserProfile) {
        val male = profile.gender == Gender.MALE
        _uiState.update {
            it.copy(
                balanceAmount = if (male) {
                    profile.coinBalance?.toString()
                } else {
                    profile.pointBalance?.toString()
                },
                balanceUnit = if (male) MoneyFormat.UNIT_COIN else MoneyFormat.UNIT_POINT,
            )
        }
    }

    private fun applyCallEnabled(config: AppConfig?) {
        val callEnabled = config?.callEnabled == true
        val tabs = DiscoverTab.visibleTabs(callEnabled)
        _uiState.update { state ->
            state.copy(
                tabs = tabs,
                callEnabled = callEnabled,
                voicePriceMin = config?.voicePriceMin ?: 0,
                videoPriceMin = config?.videoPriceMin ?: 0,
                tab = if (state.tab in tabs) state.tab else DiscoverTab.RECOMMEND,
            )
        }
    }

    companion object {
        /** 与 `docs/01` P01 的分页大小一致。 */
        const val PAGE_SIZE = 20
    }
}
