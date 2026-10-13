package com.qichuang.changliao.feature.notice

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.NoticeCategory
import com.qichuang.changliao.core.data.repository.NoticeRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 系统消息（P10）状态管理：两个分类 Tab + 下拉刷新 + 上拉加载。
 *
 * 切 Tab 会重新拉一次（每个分类的已读是分开标的）；
 * 拉列表本身就会让服务端把这个分类标已读，页面不用再调「标已读」接口。
 */
@HiltViewModel
class NoticeViewModel @Inject constructor(
    private val noticeRepository: NoticeRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(NoticeUiState())

    val uiState: StateFlow<NoticeUiState> = _uiState.asStateFlow()

    private var loaded = false

    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        load(category = _uiState.value.category)
    }

    fun onCategoryChange(category: NoticeCategory) {
        if (category == _uiState.value.category) {
            return
        }
        _uiState.update { it.copy(category = category, loading = true, error = null, notices = emptyList()) }
        load(category)
    }

    fun onRefresh() {
        _uiState.update { it.copy(refreshing = true) }
        load(_uiState.value.category, keepList = true)
    }

    fun onLoadMore() {
        val state = _uiState.value
        if (state.loadingMore || !state.hasMore) {
            return
        }

        _uiState.update { it.copy(loadingMore = true) }
        viewModelScope.launch {
            when (val result = noticeRepository.list(state.category, state.nextPage)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        loadingMore = false,
                        notices = it.notices + result.data.list,
                        hasMore = result.data.list.size >= NoticeUiState.PAGE_SIZE,
                        page = it.nextPage,
                        unread = result.data.unread,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loadingMore = false, error = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loadingMore = false, error = result.message) }
            }
        }
    }

    fun onRetry() {
        loaded = false
        enter()
    }

    private fun load(category: NoticeCategory, keepList: Boolean = false) {
        viewModelScope.launch {
            when (val result = noticeRepository.list(category, 1)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        loading = false,
                        refreshing = false,
                        error = null,
                        notices = result.data.list,
                        hasMore = result.data.list.size >= NoticeUiState.PAGE_SIZE,
                        page = 1,
                        unread = result.data.unread,
                    )
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(loading = false, refreshing = false, error = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(
                        loading = false,
                        refreshing = false,
                        // 下拉刷新失败时别把已经看到的列表清掉
                        error = if (keepList) null else result.message,
                    )
                }
            }
        }
    }
}
