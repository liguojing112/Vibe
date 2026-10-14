<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClMessageRow } from '../../../api/chat'
import * as api from '../../../api/chat'
import ClReasonDialog from '../../../components/ClReasonDialog.vue'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * 待审消息（docs/07 第 3.7 节）：命中了敏感词、但**已经发给对方**的消息。
 *
 * `04` 第 3 节定的是「命中只标记不拦截」—— 消息照常送达，事后人工审。
 * 所以这一页的动作不是「放行 / 拦下」，而是**标记正常 / 标记违规**（违规可以顺手封号）。
 */
defineOptions({ name: 'cl:chat:pending' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<ClMessageRow[]>([])

// ---------- 标记违规（单条 / 批量，都能顺手封号）----------
const reviewVisible = ref(false)
const reviewBatch = ref(false)
const reviewTarget = ref<ClMessageRow | null>(null)
const reviewLoading = ref(false)
const banDays = ref(7)
const banReason = ref('')
const BAN_DAYS = [
  { label: '1 天', value: 1 },
  { label: '3 天', value: 3 },
  { label: '7 天', value: 7 },
  { label: '30 天', value: 30 },
  { label: '永久', value: 0 },
]

function openViolation(row: ClMessageRow) {
  reviewBatch.value = false
  reviewTarget.value = row
  banReason.value = ''
  reviewVisible.value = true
}

function openBatchViolation() {
  reviewBatch.value = true
  reviewTarget.value = null
  banReason.value = ''
  reviewVisible.value = true
}

async function submitViolation(reason: string) {
  reviewLoading.value = true
  try {
    const ids = reviewBatch.value ? selections.value.map(row => row.id) : [reviewTarget.value!.id]
    // 填了封禁原因才封号（空着就是只标记）
    const res = await api.reviewMessages({
      ids,
      status: 3,
      ban_days: banDays.value,
      ban_reason: reason,
    })
    if (res.code !== ResultCode.SUCCESS) {
      msg.error(res.message)
      return
    }
    msg.success(res.data.banned ? '已标记违规并封禁发送方' : '已标记违规')
    reviewVisible.value = false
    proTableRef.value?.refresh()
  }
  finally {
    reviewLoading.value = false
  }
}

async function markNormal(row: ClMessageRow) {
  const res = await api.reviewMessages({ ids: [row.id], status: 2 })
  if (res.code === ResultCode.SUCCESS) {
    msg.success('已标记正常')
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

async function batchNormal() {
  if (selections.value.length === 0) {
    return
  }
  const res = await api.reviewMessages({ ids: selections.value.map(row => row.id), status: 2 })
  if (res.code === ResultCode.SUCCESS) {
    msg.success(`已把 ${res.data.updated} 条标记为正常`)
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

/** 把命中的词标红（docs/07 第 3.7 节）。 */
function highlight(content: string, words: string[] = []) {
  if (words.length === 0) {
    return <span>{content}</span>
  }

  const pattern = new RegExp(`(${words.map(word => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'g')
  return (
    <span>
      {content.split(pattern).map(part =>
        words.includes(part)
          ? <span class="text-red-500 font-medium">{part}</span>
          : <span>{part}</span>,
      )}
    </span>
  )
}

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '待审消息',
    subTitle: () => '命中敏感词的消息会照常送达，在这里人工判断是正常还是违规；违规可以顺手封了发送方。',
  },
  searchOptions: { show: false },
  requestOptions: {
    api: (params: Record<string, any>) => api.pendingMessages(params),
  },
  tableOptions: {
    on: {
      onSelectionChange: (rows: ClMessageRow[]) => selections.value = rows,
    },
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: [],
  tableColumns: [
    { type: 'selection', label: '选择' },
    {
      label: '发送方',
      prop: 'sender_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.sender_id, nickname: row.sender_nickname, avatar: row.sender_avatar, gender: row.sender_gender }} clickable={false} />,
    },
    {
      label: '接收方',
      prop: 'receiver_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.receiver_id, nickname: row.receiver_nickname, avatar: row.receiver_avatar, gender: row.receiver_gender }} clickable={false} />,
    },
    {
      label: '内容（红字是命中的词）',
      prop: 'content',
      cellRender: ({ row }) => highlight(row.content, row.hit_words),
    },
    { label: '时间', prop: 'created_at', width: '170px' },
    {
      type: 'operation',
      label: '操作',
      width: '180px',
      operationConfigure: {
        actions: [
          {
            name: 'normal',
            text: '标记正常',
            icon: 'ri:check-line',
            show: () => hasAuth('cl:chat:pending'),
            onClick: ({ row }) => markNormal(row),
          },
          {
            name: 'violation',
            text: '标记违规',
            icon: 'ri:forbid-2-line',
            show: () => hasAuth('cl:chat:pending'),
            onClick: ({ row }) => openViolation(row),
          },
        ],
      },
    },
  ],
})
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema">
      <template #toolbarLeft>
        <el-button
          v-auth="['cl:chat:pending']"
          type="primary"
          plain
          :disabled="selections.length === 0"
          @click="batchNormal"
        >
          批量标记正常
        </el-button>
        <el-button
          v-auth="['cl:chat:pending']"
          type="danger"
          plain
          :disabled="selections.length === 0"
          @click="openBatchViolation"
        >
          批量标记违规
        </el-button>
      </template>
    </MaProTable>

    <ClReasonDialog
      v-model="reviewVisible"
      :title="reviewBatch ? '批量标记违规' : '标记违规'"
      :message="reviewBatch
        ? `确定把选中的 ${selections.length} 条标为违规吗？`
        : `确定把「${reviewTarget?.sender_nickname ?? ''}」这条消息标为违规吗？`"
      confirm-text="确认违规"
      placeholder="封禁原因（填了才会封号，不填只标记）"
      :loading="reviewLoading"
      :default-reason="banReason"
      @confirm="submitViolation"
    >
      <el-form label-width="70px" class="mb-2">
        <el-form-item label="封禁时长">
          <el-select v-model="banDays">
            <el-option v-for="item in BAN_DAYS" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
          <span class="ml-2 text-xs text-gray-400">要封号的话，上面那个框就是封禁原因</span>
        </el-form-item>
      </el-form>
    </ClReasonDialog>
  </div>
</template>
