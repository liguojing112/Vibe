package com.qichuang.changliao.feature.moment

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.Moment
import com.qichuang.changliao.core.data.repository.MomentRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 朋友圈列表状态管理：分页 + 下拉刷新 + 点赞（乐观更新）+ 删除。
 *
 * 点赞先改本地再发请求：**失败时回滚**并提示，不然用户会以为赞上了其实没有。
 */
@HiltViewModel
class MomentViewModel @Inject constructor(
    private val momentRepository: MomentRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(MomentUiState())

    val uiState: StateFlow<MomentUiState> = _uiState.asStateFlow()

    private var loadedFor: Long? = null

    /** @param userId 0 = 我的朋友圈 */
    fun enter(userId: Long) {
        if (loadedFor == userId) {
            return
        }
        loadedFor = userId
        _uiState.update { it.copy(userId = userId, loading = true, error = null, moments = emptyList()) }
        load()
    }

    fun onRefresh() {
        _uiState.update { it.copy(refreshing = true) }
        load(keepList = true)
    }

    fun onLoadMore() {
        val state = _uiState.value
        if (state.loadingMore || !state.hasMore) {
            return
        }
        _uiState.update { it.copy(loadingMore = true) }
        fetch(state.nextPage, append = true)
    }

    fun onRetry() {
        _uiState.update { it.copy(loading = true, error = null) }
        load()
    }

    /** 点赞 / 取消：先乐观改 UI，再按服务端返回校准，失败回滚。 */
    fun onToggleLike(moment: Moment) {
        val liked = !moment.isLiked
        replace(moment.toggledLike(liked))

        viewModelScope.launch {
            val result = if (liked) {
                momentRepository.like(moment.id)
            } else {
                momentRepository.unlike(moment.id)
            }

            when (result) {
                is ApiResult.Success -> {
                    // 用服务端给的计数校准（别人同时点赞时本地算出来的数会偏）
                    _uiState.update { state ->
                        state.copy(
                            moments = state.moments.map {
                                if (it.id == moment.id) {
                                    it.copy(
                                        isLiked = result.data.liked,
                                        likeCount = result.data.likeCount,
                                    )
                                } else {
                                    it
                                }
                            },
                        )
                    }
                }

                is ApiResult.BizError -> rollback(moment, result.message)
                is ApiResult.NetworkError -> rollback(moment, result.message)
            }
        }
    }

    fun onDelete(moment: Moment) {
        viewModelScope.launch {
            when (val result = momentRepository.remove(moment.id)) {
                is ApiResult.Success ->
                    _uiState.update { it.copy(moments = it.moments.filterNot { m -> m.id == moment.id }) }

                is ApiResult.BizError -> _uiState.update { it.copy(toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(toast = result.message) }
            }
        }
    }

    fun onPreview(urls: List<String>, index: Int) {
        _uiState.update { it.copy(previewUrls = urls, previewIndex = index) }
    }

    fun onClosePreview() {
        _uiState.update { it.copy(previewIndex = null) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    /** 评论数变了（从评论弹窗回来时同步一下卡片上的数字）。 */
    fun onCommentsCountChanged(momentId: Long, count: Int) {
        _uiState.update { state ->
            state.copy(
                moments = state.moments.map {
                    if (it.id == momentId) it.copy(commentCount = count) else it
                },
            )
        }
    }

    private fun load(keepList: Boolean = false) {
        if (!keepList) {
            _uiState.update { it.copy(loading = true, error = null) }
        }
        fetch(page = 1, append = false)
    }

    private fun fetch(page: Int, append: Boolean) {
        val userId = _uiState.value.userId
        viewModelScope.launch {
            val result = if (userId == 0L) {
                momentRepository.mine(page)
            } else {
                momentRepository.userMoments(userId, page)
            }

            when (result) {
                is ApiResult.Success -> _uiState.update { state ->
                    val merged = if (append) {
                        // 发新动态后翻页可能出现重复条，按 id 去重
                        (state.moments + result.data.list).distinctBy { it.id }
                    } else {
                        result.data.list
                    }
                    state.copy(
                        moments = merged,
                        page = result.data.page,
                        hasMore = result.data.hasMore,
                        loading = false,
                        refreshing = false,
                        loadingMore = false,
                        error = null,
                    )
                }

                is ApiResult.BizError -> fail(result.message, append)
                is ApiResult.NetworkError -> fail(result.message, append)
            }
        }
    }

    private fun fail(message: String, append: Boolean) {
        _uiState.update { state ->
            state.copy(
                loading = false,
                refreshing = false,
                loadingMore = false,
                // 加载更多失败不该把整页顶成错误态
                error = if (append) state.error else message,
                toast = if (append) message else state.toast,
            )
        }
    }

    private fun replace(moment: Moment) {
        _uiState.update { state ->
            state.copy(moments = state.moments.map { if (it.id == moment.id) moment else it })
        }
    }

    private fun rollback(original: Moment, message: String) {
        replace(original)
        _uiState.update { it.copy(toast = message) }
    }
}
