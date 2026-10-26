package com.qichuang.changliao.feature.relation

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.RelationKind
import com.qichuang.changliao.core.data.model.RelationPerson
import com.qichuang.changliao.core.data.repository.RelationRepository
import com.qichuang.changliao.core.data.repository.UserRepository
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
 * 「最近访客」（P08）和「喜欢我的人」（P09）共用的 ViewModel。
 *
 * 进入页面走 stale-while-revalidate：先渲染本地缓存再静默刷新；
 * 服务端说 [RelationListUiState.locked] 时列表是打码数据，点任何一行都引导去开通会员。
 */
@HiltViewModel
class RelationListViewModel @Inject constructor(
    private val relationRepository: RelationRepository,
    private val userRepository: UserRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(RelationListUiState())
    val uiState: StateFlow<RelationListUiState> = _uiState.asStateFlow()

    private var userId: Long = 0
    private var listJob: Job? = null

    fun enter(kind: RelationKind) {
        if (_uiState.value.kind != kind) {
            _uiState.value = RelationListUiState(kind = kind)
        }

        viewModelScope.launch {
            val id = currentUserId() ?: return@launch
            userId = id
            // 访客列表读一次服务端会清零未读；缓存 60 秒节流会让服务端收不到这次请求，
            // 所以访客页每次进入都强制请求（先发缓存再刷新，页面仍然秒开）。
            // 喜欢我的人没有未读副作用，保留节流。
            load(kind, force = kind == RelationKind.VISITORS)
        }
    }

    fun onRefresh() {
        load(_uiState.value.kind, force = true)
    }

    fun onLoadMore() {
        val state = _uiState.value
        if (state.loadingMore || state.refreshing || !state.hasMore || state.loadMoreError != null) {
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(loadingMore = true) }
            val nextPage = state.people.size / PAGE_SIZE + 1

            when (val result = relationRepository.more(state.kind, nextPage)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        people = it.people + result.data.list,
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

    /**
     * 「回关」（P09）：关注对方。对方本来就是关注我的，所以回关后就是互关 ——
     * 服务端会把关系带回来，行上的按钮随之从「回关」变成「查看」。
     */
    fun onFollowBack(person: RelationPerson) {
        val state = _uiState.value
        if (state.locked) {
            onVipPromptVisibleChange(true)
            return
        }
        if (state.submitting) {
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(submitting = true) }
            when (val result = relationRepository.follow(person.id)) {
                is ApiResult.Success -> _uiState.update { current ->
                    current.copy(
                        submitting = false,
                        people = current.people.map { item ->
                            if (item.id == person.id) {
                                item.copy(
                                    isFollowing = result.data.isFollowing,
                                    isMutual = result.data.isMutual,
                                )
                            } else {
                                item
                            }
                        },
                    )
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(submitting = false, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(submitting = false, toast = result.message)
                }
            }
        }
    }

    fun onVipPromptVisibleChange(visible: Boolean) {
        _uiState.update { it.copy(vipPromptVisible = visible) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    // ---------- 内部 ----------

    private fun load(kind: RelationKind, force: Boolean) {
        if (userId <= 0) {
            return
        }

        listJob?.cancel()
        listJob = viewModelScope.launch {
            val hasContent = _uiState.value.people.isNotEmpty()
            _uiState.update {
                it.copy(
                    kind = kind,
                    loading = !hasContent,
                    refreshing = force && hasContent,
                    error = null,
                    loadMoreError = null,
                )
            }

            relationRepository.firstPage(kind, userId, force).collect { result ->
                when (result) {
                    is ApiResult.Success -> _uiState.update {
                        it.copy(
                            people = result.data.list,
                            total = result.data.total,
                            hasMore = result.data.hasMore,
                            locked = result.data.locked,
                            loading = false,
                            refreshing = false,
                            error = null,
                        )
                    }

                    is ApiResult.BizError -> applyError(result.message)
                    is ApiResult.NetworkError -> applyError(result.message)
                }
            }
        }
    }

    private fun applyError(message: String) {
        _uiState.update {
            if (it.people.isEmpty()) {
                it.copy(loading = false, refreshing = false, error = message)
            } else {
                // 有缓存时不把整页变成错误页，弹个提示就够了
                it.copy(loading = false, refreshing = false, toast = message)
            }
        }
    }

    /** 缓存键要按用户分，所以先确认「我」是谁。 */
    private suspend fun currentUserId(): Long? {
        userRepository.currentUser.value?.let { return it.id }

        return when (val result = userRepository.me()) {
            is ApiResult.Success -> result.data.id
            is ApiResult.BizError -> {
                _uiState.update { it.copy(loading = false, error = result.message) }
                null
            }

            is ApiResult.NetworkError -> {
                _uiState.update { it.copy(loading = false, error = result.message) }
                null
            }
        }
    }

    companion object {
        /** 与服务端关系列表的分页大小一致（`RelationService::PAGE_SIZE`）。 */
        const val PAGE_SIZE = 20
    }
}
