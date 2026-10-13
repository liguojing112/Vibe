package com.qichuang.changliao.feature.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.RelationState
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.NoticeRepository
import com.qichuang.changliao.core.data.repository.RelationRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 个人详情页（docs/01 P05）。
 *
 * 注意 `/user/{id}` **每调一次服务端就记一条访客**，所以这里只在进入页面时拉一次，
 * 关注 / 取关之后**用服务端返回的关系快照就地更新**，不重新拉整页（不重复记访客，也不用等一整趟请求）。
 */
@HiltViewModel
class UserDetailViewModel @Inject constructor(
    private val userRepository: UserRepository,
    private val relationRepository: RelationRepository,
    private val noticeRepository: NoticeRepository,
    private val appConfigRepository: AppConfigRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(UserDetailUiState())
    val uiState: StateFlow<UserDetailUiState> = _uiState.asStateFlow()

    private var targetId: Long = 0

    fun load(userId: Long) {
        targetId = userId
        viewModelScope.launch {
            _uiState.update { it.copy(loading = true, error = null) }

            val result = userRepository.detail(userId)
            when (result) {
                is ApiResult.Success -> {
                    // 标签名字要拿到资料之后才能换（拿的是他的 tags id）
                    val tagNames = tagNamesOf(result.data.tags)
                    _uiState.update {
                        it.copy(loading = false, detail = result.data, tagNames = tagNames)
                    }
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(loading = false, error = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(loading = false, error = result.message)
                }
            }
        }
    }

    // ---------- ⋯ 菜单 ----------

    fun onMenuVisibleChange(visible: Boolean) {
        _uiState.update { it.copy(menuVisible = visible) }
    }

    /** 关注 / 取消关注。 */
    fun onToggleFollow() {
        val detail = _uiState.value.detail ?: return
        if (_uiState.value.submitting) {
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(submitting = true, menuVisible = false) }
            val result = if (detail.isFollowing) {
                relationRepository.unfollow(detail.id)
            } else {
                relationRepository.follow(detail.id)
            }

            when (result) {
                is ApiResult.Success -> applyRelation(result.data)
                is ApiResult.BizError -> _uiState.update {
                    it.copy(submitting = false, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(submitting = false, toast = result.message)
                }
            }
        }
    }

    // ---------- 拉黑 ----------

    fun onBlockConfirmVisibleChange(visible: Boolean) {
        _uiState.update { it.copy(blockConfirmVisible = visible, menuVisible = false) }
    }

    fun onConfirmBlock() {
        val detail = _uiState.value.detail ?: return

        viewModelScope.launch {
            _uiState.update { it.copy(submitting = true, blockConfirmVisible = false) }
            when (val result = relationRepository.block(detail.id)) {
                is ApiResult.Success -> _uiState.update { it.copy(submitting = false, blocked = true) }
                is ApiResult.BizError -> _uiState.update {
                    it.copy(submitting = false, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(submitting = false, toast = result.message)
                }
            }
        }
    }

    // ---------- 举报 ----------

    fun onReportVisibleChange(visible: Boolean) {
        _uiState.update {
            it.copy(
                reportVisible = visible,
                menuVisible = false,
                reportReason = if (visible) it.reportReason else null,
                reportContent = if (visible) it.reportContent else "",
            )
        }
    }

    fun onReportReasonChange(reason: ReportReason) {
        _uiState.update { it.copy(reportReason = reason) }
    }

    fun onReportContentChange(content: String) {
        _uiState.update { it.copy(reportContent = content) }
    }

    /**
     * 举报提交（`docs/05` 第 4.9 节 `/report`，T6.6 接通）。
     *
     * 举报完**把弹窗关掉、输入清空**：同一个人再举报一次是另一件事，留着上次的说明只会误导。
     * 「要提交什么」「能不能提交」都由服务端判（不能举报自己、对象要存在、带的消息得是自己的），
     * 错了就把服务端的话原样提示出来。
     */
    fun onSubmitReport() {
        val state = _uiState.value
        val reason = state.reportReason
        if (reason == null) {
            _uiState.update { it.copy(toast = "请选择举报原因") }
            return
        }
        if (state.reportSubmitting) {
            return
        }

        _uiState.update { it.copy(reportSubmitting = true) }
        viewModelScope.launch {
            when (val result = noticeRepository.report(targetId, reason.code, state.reportContent)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        reportSubmitting = false,
                        reportVisible = false,
                        reportReason = null,
                        reportContent = "",
                        toast = result.data.statusText,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(reportSubmitting = false, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(reportSubmitting = false, toast = result.message) }
            }
        }
    }

    // ---------- 相册预览 ----------

    fun onPreview(index: Int?) {
        _uiState.update { it.copy(previewIndex = index) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    // ---------- 内部 ----------

    private fun applyRelation(state: RelationState) {
        _uiState.update { current ->
            current.copy(
                submitting = false,
                detail = current.detail?.copy(
                    isFollowing = state.isFollowing,
                    isMutual = state.isMutual,
                    // 关注关系变了，原来那条免费提示（「互相关注，私信免费」之类）就不作数了。
                    // 新报价只有服务端算得出来，而重新拉整页会多记一条访客，所以这里先不显示，
                    // 宁可少一句话，也别显示一句错的。
                    price = current.detail.price?.copy(freeHint = null),
                ),
            )
        }
    }

    /** 兴趣标签要显示名字，标签库在 `/app/config` 里。 */
    private suspend fun tagNamesOf(tagIds: List<Long>): List<String> {
        if (tagIds.isEmpty()) {
            return emptyList()
        }
        val tags = appConfigRepository.config.first()?.tags.orEmpty()

        return tagIds.mapNotNull { id -> tags.firstOrNull { it.id == id }?.name }
    }
}
