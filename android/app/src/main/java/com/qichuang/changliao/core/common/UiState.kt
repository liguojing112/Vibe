package com.qichuang.changliao.core.common

/**
 * 页面状态统一封装（docs/02 第 4.4 节）。
 * 每个有数据的页面都要处理这四种状态。
 */
sealed interface UiState<out T> {
    /** 加载中 */
    data object Loading : UiState<Nothing>

    /** 空数据 */
    data object Empty : UiState<Nothing>

    /** 出错，可重试 */
    data class Error(val message: String) : UiState<Nothing>

    /** 有内容 */
    data class Content<T>(val data: T) : UiState<T>
}
