<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClConversationRow, ClMessageRow } from '../../../api/chat'
import * as api from '../../../api/chat'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * 会话记录（docs/07 第 3.6 节）。
 *
 * 点开一个会话能看到**全部**消息，每条带价格、免费原因、女方所得积分 ——
 * 这些字段 App 一侧看不到，是后台对账/取证才需要的。
 */
defineOptions({ name: 'cl:chat:conversation' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

// ---------- 消息详情 ----------
const detailVisible = ref(false)
const detailLoading = ref(false)
const current = ref<ClConversationRow | null>(null)
const messages = ref<ClMessageRow[]>([])
const detailPage = ref(1)
const detailTotal = ref(0)

async function openDetail(row: ClConversationRow, page = 1) {
  current.value = row
  detailPage.value = page
  detailVisible.value = true
  detailLoading.value = true
  try {
    const res = await api.conversationMessages(row.id, { page, page_size: 20 })
    if (res.code === ResultCode.SUCCESS) {
      messages.value = res.data.list
      detailTotal.value = res.data.total
    }
    else {
      msg.error(res.message)
    }
  }
  finally {
    detailLoading.value = false
  }
}

/** 在会话详情里直接把某条标为违规（和待审消息那一页同一个接口）。 */
async function markViolation(row: ClMessageRow) {
  const res = await api.reviewMessages({ ids: [row.id], status: 3, ban_reason: '' })
  if (res.code === ResultCode.SUCCESS) {
    msg.success('已标记违规')
    row.review_status = 3
  }
  else {
    msg.error(res.message)
  }
}

const TYPE_TEXT: Record<number, string> = { 1: '文字', 2: '图片', 3: '礼物', 4: '系统' }
const FREE_TEXT: Record<string, string> = {
  PAIR_FREE: '前几条免费',
  MUTUAL_FOLLOW: '互关免费',
  VIP_FREE: '会员免费额度',
  FEMALE_SEND: '女方发送',
}

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '会话记录',
    subTitle: () => '点开任意一条会话看全部消息（含价格与分成）；可在消息上直接标记违规。',
  },
  searchOptions: { fold: true },
  requestOptions: {
    api: (params: Record<string, any>) => api.conversationList(params),
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: [
    { label: '用户 ID', prop: 'user_id', render: 'input', renderProps: { placeholder: '任一方', clearable: true } },
    { label: '开始日期', prop: 'created_from', render: 'date-picker', renderProps: { type: 'date', valueFormat: 'YYYY-MM-DD' } },
    { label: '结束日期', prop: 'created_to', render: 'date-picker', renderProps: { type: 'date', valueFormat: 'YYYY-MM-DD' } },
  ],
  tableColumns: [
    {
      label: '男方',
      prop: 'male_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.male_id, nickname: row.male_nickname, avatar: row.male_avatar, gender: 1 }} clickable={false} />,
    },
    {
      label: '女方',
      prop: 'female_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.female_id, nickname: row.female_nickname, avatar: row.female_avatar, gender: 2 }} clickable={false} />,
    },
    { label: '男方已发条数', prop: 'male_sent_count', width: '120px' },
    { label: '最后消息时间', prop: 'last_message_at', width: '170px' },
    {
      type: 'operation',
      label: '操作',
      width: '120px',
      operationConfigure: {
        actions: [
          {
            name: 'detail',
            text: '查看消息',
            icon: 'ri:chat-history-line',
            show: () => hasAuth('cl:chat:conversation'),
            onClick: ({ row }) => openDetail(row),
          },
        ],
      },
    },
  ],
})
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema" />

    <el-dialog v-model="detailVisible" :title="`会话消息：${current?.male_nickname ?? ''} ↔ ${current?.female_nickname ?? ''}`" width="900px">
      <div v-loading="detailLoading">
        <div class="mb-2 text-sm text-gray-500">
          男方已发 {{ current?.male_sent_count ?? 0 }} 条 · 共 {{ detailTotal }} 条消息
        </div>
        <el-table :data="messages" size="small" border max-height="480">
          <el-table-column prop="created_at" label="时间" width="160" />
          <el-table-column label="方向" width="90">
            <template #default="{ row }">
              {{ row.sender_id === current?.male_id ? '男 → 女' : '女 → 男' }}
            </template>
          </el-table-column>
          <el-table-column label="类型" width="70">
            <template #default="{ row }">
              {{ TYPE_TEXT[row.type] ?? row.type }}
            </template>
          </el-table-column>
          <el-table-column prop="content" label="内容" show-overflow-tooltip />
          <el-table-column label="价格" width="90" align="right">
            <template #default="{ row }">
              <span v-if="row.price_coins > 0">{{ row.price_coins }} 币</span>
              <span v-else class="text-gray-400">免费</span>
            </template>
          </el-table-column>
          <el-table-column label="免费原因" width="120">
            <template #default="{ row }">
              <span v-if="row.free_reason">{{ FREE_TEXT[row.free_reason] ?? row.free_reason }}</span>
              <span v-else class="text-gray-400">—</span>
            </template>
          </el-table-column>
          <el-table-column label="女方所得" width="100" align="right">
            <template #default="{ row }">
              <span v-if="row.receiver_points > 0">+{{ row.receiver_points }}</span>
              <span v-else class="text-gray-400">0</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag v-if="row.review_status === 3" type="danger" size="small" disable-transitions>
                违规
              </el-tag>
              <el-tag v-else-if="row.review_status === 1" type="warning" size="small" disable-transitions>
                待审
              </el-tag>
              <span v-else class="text-gray-400">—</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="100">
            <template #default="{ row }">
              <el-link
                v-if="row.review_status !== 3 && hasAuth('cl:chat:pending')"
                type="danger"
                @click="markViolation(row as any)"
              >
                标记违规
              </el-link>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination
          class="mt-3 justify-end"
          layout="prev, pager, next"
          :total="detailTotal"
          :page-size="20"
          :current-page="detailPage"
          @current-change="(page: number) => current && openDetail(current, page)"
        />
      </div>
    </el-dialog>
  </div>
</template>
