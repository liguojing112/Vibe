<script setup lang="ts">
import type { ClBotChatTargetRow } from '../../../api/botChat'
import * as api from '../../../api/botChat'
import ClAmount from '../../../components/ClAmount.vue'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 目标女号明细：今天每个女号赚了多少（虚拟）、聊了几轮、有没有被上限停掉。
 *
 * 「收益」是虚拟积分，**不进钱包**，这里只用来判断当天还能不能继续聊。
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
const rows = ref<ClBotChatTargetRow[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)

const limitReachedCount = computed(() => rows.value.filter(row => row.limit_reached).length)
const amountTodayTotal = computed(() => rows.value.reduce((sum, row) => sum + Number(row.amount_today_fen ?? 0), 0))

async function load() {
  if (props.ruleId === null) {
    return
  }
  loading.value = true
  try {
    const res = await api.botChatTargets(props.ruleId, { page: page.value, page_size: pageSize.value })
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
  <el-drawer v-model="drawerVisible" :title="`目标女号 · ${props.ruleName}`" size="900px" destroy-on-close>
    <div class="mb-3 flex items-center gap-4 text-sm text-gray-600">
      <span>目标 {{ total }} 个</span>
      <span>本页已达上限：{{ limitReachedCount }} 个</span>
      <span>
        本页虚拟收益合计：
        <ClAmount :value="amountTodayTotal" type="fen" />
      </span>
    </div>

    <el-table v-loading="loading" :data="rows" size="small" border>
      <el-table-column label="女号" min-width="200">
        <template #default="{ row }">
          <ClUserCell :user="{ id: row.user_id, nickname: row.nickname, avatar: row.avatar, gender: 2 }" />
        </template>
      </el-table-column>
      <el-table-column label="今日虚拟收益" width="140" align="right">
        <template #default="{ row }">
          <ClAmount :value="row.amount_today_fen" type="fen" />
        </template>
      </el-table-column>
      <el-table-column label="今日轮数" width="100" align="center">
        <template #default="{ row }">
          {{ row.rounds_today }} 轮
        </template>
      </el-table-column>
      <el-table-column label="状态" width="130" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.limit_reached" type="warning" size="small">
            已达上限
          </el-tag>
          <el-tag v-else type="success" size="small">
            进行中
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="last_sent_at" label="最后发送" width="170" />
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
