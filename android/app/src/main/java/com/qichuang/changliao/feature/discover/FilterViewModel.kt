package com.qichuang.changliao.feature.discover

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.DiscoverRepository
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
 * 筛选条件页（docs/01 P07）。
 *
 * 城市列表从后台配置取（`/app/config` 的 `cities`），筛选条件按用户存在本地，
 * 点「查看匹配的人」保存后回 P01；P01 重新进入时会重新读一遍，所以改动立即生效。
 */
@HiltViewModel
class FilterViewModel @Inject constructor(
    private val discoverRepository: DiscoverRepository,
    private val userRepository: UserRepository,
    private val appConfigRepository: AppConfigRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(FilterUiState())
    val uiState: StateFlow<FilterUiState> = _uiState.asStateFlow()

    private var userId: Long = 0

    fun load() {
        viewModelScope.launch {
            _uiState.update { it.copy(loading = true, loadError = null) }

            val cities = appConfigRepository.config.first()?.cities.orEmpty()
            val profile = userRepository.currentUser.value
                ?: (userRepository.me() as? ApiResult.Success)?.data

            if (profile == null) {
                // 存筛选要按用户分键，拿不到「我」就先别让用户改（避免存错人）
                _uiState.update {
                    it.copy(loading = false, loadError = "资料没加载出来，请重试")
                }
                return@launch
            }

            userId = profile.id
            _uiState.update {
                it.copy(
                    loading = false,
                    loadError = null,
                    cities = cities,
                    draft = discoverRepository.filter(profile.id),
                )
            }
        }
    }

    // ---------- 各筛选条件 ----------

    /** 城市单选：「全部」= 不筛城市。 */
    fun onCitySelected(label: String) {
        val city = if (label == FilterUiState.ALL_CITIES) "" else label
        _uiState.update { it.copy(draft = it.draft.copy(city = city)) }
    }

    fun onAgeRangeChange(range: ClosedFloatingPointRange<Float>) {
        _uiState.update {
            it.copy(
                draft = it.draft.copy(
                    ageMin = range.start.toInt(),
                    ageMax = range.endInclusive.toInt(),
                ),
            )
        }
    }

    fun onPurposesChange(labels: Set<String>) {
        _uiState.update { it.copy(draft = it.draft.copy(purposes = labels.toList())) }
    }

    fun onVerificationsChange(labels: Set<String>) {
        _uiState.update {
            it.copy(
                draft = it.draft.copy(
                    verified = FilterUiState.VERIFIED_REAL_NAME in labels,
                    complete = FilterUiState.VERIFIED_COMPLETE in labels,
                ),
            )
        }
    }

    fun onOnlineOnlyChange(value: Boolean) {
        _uiState.update { it.copy(draft = it.draft.copy(onlineOnly = value)) }
    }

    fun onReset() {
        _uiState.update { it.copy(draft = DiscoverFilter()) }
    }

    /** 保存并回上一页。 */
    fun onApply() {
        if (userId <= 0) {
            // 没拿到用户 id 时存不了（缓存键要按用户分），但也不能把用户卡在这页
            _uiState.update { it.copy(applied = true) }
            return
        }

        discoverRepository.saveFilter(userId, _uiState.value.draft)
        _uiState.update { it.copy(applied = true) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }
}
