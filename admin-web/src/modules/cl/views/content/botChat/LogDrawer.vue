<script setup lang="ts">
import type { ClBotChatLogRow } from '../../../api/botChat'
import * as api from '../../../api/botChat'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 发送记录：每一条男号发起 / 女 AI 回复都留痕，失败的带原因。
 *
 * 失败原因写在日志里而不是吞掉，出问题时能直接看出是模型报错、还是消息落库撞了唯一键。
 */
const props = defineProps<{
  visible: boolean
  ruleId: number | null
  ruleName: string
}>()

const emit = defineEmits<{ 'update:visible': [value: boolean] }>()

const msg = useMessage()

const drawerVisible = computed({
  get: () => props.visible,
  set: (value: boolean) => emit('update:visible', value),
})

const loading = ref(false)
const rows = ref<ClBotChatLogRow[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const filters = reactive({
  female_id: undefined as number | undefined,
  status: '' as number | string,
  dateRange: [] as string[],
})

const statusOptions = [
  { label: '成功', value: 1 },
  { label: '失败', value: 2 },
]

const roleLabel: Record<string, string> = {
  male_bot: '男号发起',
  female_ai: '女 AI 回复',
}

async function load() {
  if (props.ruleId === null) {
    return
  }
  loading.value = true
  try {
    const res = await api.botChatLogs(props.ruleId, {
      page: page.value,
      page_size: pageSize.value,
      female_id: filters.female_id,
      status: filters.status === '' ? undefined : filters.status,
      date_from: filters.dateRange?.[0],
      date_to: filters.dateRange?.[1],
    })
    if (res.code !== ResultCode.SUCCESS) {
      msg.error(res.message)
      return
    }
    rows.value = res.data.list
    total.value = res.data.total
  }
  finally {
    loading.value = false
  }
}

function search() {
  page.value = 1
  load()
}

watch(
  () => [props.visible, props.ruleId] as const,
  ([visible]) => {
    if (!visible) {
      return
    }
    page.value = 1
    load()
  },
  { immediate: true },
)

watch([page, pageSize], load)
</script>

<template>
  <el-drawer v-model="drawerVisible" :title="`发送记录 · ${props.ruleName}`" size="1000px" destroy-on-close>
    <div class="mb-3 flex flex-wrap items-end gap-2">
      <el-input-number
        v-model="filters.female_id"
        :min="1"
        :controls="false"
        placeholder="女号 ID"
        style="width: 120px;"
      />
      <el-select v-model="filters.status" placeholder="状态" clearable style="width: 120px;">
        <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-date-picker
        v-model="filters.dateRange"
        type="daterange"
        value-format="YYYY-MM-DD"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        style="width: 260px;"
      />
      <el-button type="primary" @click="search">
        查询
      </el-button>
    </div>

    <el-table v-loading="loading" :data="rows" size="small" border>
      <el-table-column prop="created_at" label="时间" width="160" />
      <el-table-column label="男号" min-width="170">
        <template #default="{ row }">
          <ClUserCell :user="{ id: row.male_id, nickname: row.male_nickname, avatar: row.male_avatar, gender: 1 }" />
        </template>
      </el-table-column>
      <el-table-column label="女号" min-width="170">
        <template #default="{ row }">
          <ClUserCell :user="{ id: row.female_id, nickname: row.female_nickname, avatar: row.female_avatar, gender: 2 }" />
        </template>
      </el-table-column>
      <el-table-column prop="content" label="内容" min-width="200" show-overflow-tooltip />
      <el-table-column label="类型" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="row.role === 'female_ai' ? 'success' : 'primary'" size="small">
            {{ roleLabel[row.role] ?? row.role }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="virtual_points" label="虚拟积分" width="100" align="right" />
      <el-table-column label="状态" width="150" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.status === 1" type="success" size="small">
            成功
          </el-tag>
          <el-tooltip v-else :content="row.fail_reason || '未知原因'" placement="top">
            <el-tag type="danger" size="small">
              失败
            </el-tag>
          </el-tooltip>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-3 flex justify-end">
      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        background
      />
    </div>
  </el-drawer>
</template>
