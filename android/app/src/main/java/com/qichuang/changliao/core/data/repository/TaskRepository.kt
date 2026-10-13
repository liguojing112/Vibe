package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.DailyTask
import com.qichuang.changliao.core.data.model.TaskReward
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.TaskApi
import com.qichuang.changliao.core.network.dto.DailyTaskDto
import com.qichuang.changliao.core.network.dto.TaskClaimRequestDto
import com.qichuang.changliao.core.network.dto.TaskListDto
import com.qichuang.changliao.core.network.dto.TaskRewardDto
import com.qichuang.changliao.core.network.safeApiCall
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 每日任务（docs/05 第 4.9 节，P11）。
 *
 * **不做本地缓存**：任务的进度和状态随时在变（今天发了几条消息、照片审过了没有），
 * 缓存住反而会显示「可领取」而其实已经领过了；而且这一页打开频率不高，实时拉一次就够。
 */
@Singleton
class TaskRepository @Inject constructor(
    private val taskApi: TaskApi,
) {

    suspend fun list(): ApiResult<List<DailyTask>> =
        when (val result = safeApiCall<TaskListDto> { taskApi.list() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.list.map { it.toModel() })
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /** 签到（服务端会把签到任务的奖励一起发了）。 */
    suspend fun signIn(): ApiResult<TaskReward> =
        when (val result = safeApiCall<TaskRewardDto> { taskApi.signIn() }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }

    /** 领奖。服务端会重新判断一次完成条件，没完成会返回 40001。 */
    suspend fun claim(taskId: Long): ApiResult<TaskReward> =
        when (val result = safeApiCall<TaskRewardDto> { taskApi.claim(TaskClaimRequestDto(taskId)) }) {
            is ApiResult.Success -> ApiResult.Success(result.data.toModel())
            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
}

private fun DailyTaskDto.toModel() = DailyTask(
    id = id,
    name = name,
    description = description,
    icon = icon,
    type = type,
    period = period,
    target = target,
    progress = progress,
    reward = reward,
    rewardType = rewardType,
    rewardText = rewardText,
    status = status,
    buttonText = buttonText,
    action = action,
)

private fun TaskRewardDto.toModel() = TaskReward(
    reward = reward,
    rewardType = rewardType,
    rewardText = rewardText,
    balance = balance,
)
