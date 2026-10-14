<script setup lang="tsx">
import type { ClLevelRow } from '../../api/operation'
import * as api from '../../api/operation'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 等级配置（docs/07 第 3.16 节）：5 行直接编辑。
 *
 * 校验在服务端（门槛递增、Lv1 门槛为 0、分成 0~10000），这里只把值传上去。
 * **改门槛不会让已经升级的用户降级** —— 升级只由 `LevelService` 在收到钱之后推，而且只升不降。
 */
defineOptions({ name: 'cl:operation:level' })

const msg = useMessage()
const loading = ref(true)
const saving = ref(false)
const rows = ref<ClLevelRow[]>([])

async function load() {
  loading.value = true
  try {
    const res = await api.levels()
    rows.value = res.code === ResultCode.SUCCESS ? res.data : []
  }
  finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const res = await api.saveLevels(rows.value)
    if (res.code === ResultCode.SUCCESS) {
      msg.success('已保存，之后的消息按新单价算')
      await load()
    }
    else {
      msg.error(res.message)
    }
  }
  finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="mine-layout">
    <el-card v-loading="loading" shadow="never" class="m-3">
      <template #header>
        <div class="text-base font-medium">
          等级配置
        </div>
        <div class="mt-1 text-xs text-gray-500">
          升级门槛必须一级比一级高、Lv1 门槛固定为 0；分成用万分比（3000 = 30%）。
          改单价只影响之后的消息；改门槛不会让已经升级的用户降级。
        </div>
      </template>

      <el-table :data="rows" border>
        <el-table-column label="等级" width="90">
          <template #default="{ row }">
            Lv{{ row.level }}
          </template>
        </el-table-column>
        <el-table-column label="名称" width="130">
          <template #default="{ row }">
            <el-input v-model="row.name" maxlength="16" />
          </template>
        </el-table-column>
        <el-table-column label="升级门槛(积分)" width="170">
          <template #default="{ row }">
            <el-input-number v-model="row.threshold_points" :min="0" :disabled="row.level === 1" controls-position="right" />
          </template>
        </el-table-column>
        <el-table-column label="私信单价(金币/条)" width="170">
          <template #default="{ row }">
            <el-input-number v-model="row.message_price" :min="0" controls-position="right" />
          </template>
        </el-table-column>
        <el-table-column label="私信分成(万分比)" width="170">
          <template #default="{ row }">
            <el-input-number v-model="row.message_share_rate" :min="0" :max="10000" :step="100" controls-position="right" />
          </template>
        </el-table-column>
        <el-table-column label="语音单价(仅展示)" width="160">
          <template #default="{ row }">
            <el-input-number v-model="row.voice_price" :min="0" controls-position="right" />
          </template>
        </el-table-column>
        <el-table-column label="视频单价(仅展示)" width="160">
          <template #default="{ row }">
            <el-input-number v-model="row.video_price" :min="0" controls-position="right" />
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-3">
        <el-button type="primary" :loading="saving" @click="save">
          保存
        </el-button>
      </div>
    </el-card>
  </div>
</template>
