<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClMessageRow, ClReportRow } from '../../../api/chat'
import * as api from '../../../api/chat'
import ClImagePreview from '../../../components/ClImagePreview.vue'
import ClStatusTag from '../../../components/ClStatusTag.vue'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * 举报处理（docs/07 第 3.9 节）。
 *
 * 处理完**一定要通知举报人** —— 举报了没下文，下次就没人举报了（服务端在 handle 里发）。
 * 「判定违规」和「封号」是两个勾选框，但封号走的是和用户管理同一个封禁入口。
 */
defineOptions({ name: 'cl:report:list' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

const TABS = [
  { label: '待处理', value: 0 },
  { label: '已处理', value: 1 },
]
const status = ref(0)

function onTabChange() {
  proTableRef.value?.setRequestParams({ status: status.value }, true)
}

// ---------- 处理 ----------
const handleVisible = ref(false)
const handleTarget = ref<ClReportRow | null>(null)
const handleForm = reactive({ violation: true, remark: '', banDays: 7, banReason: '' })
const handleLoading = ref(false)
const BAN_DAYS = [
  { label: '1 天', value: 1 },
  { label: '3 天', value: 3 },
  { label: '7 天', value: 7 },
  { label: '30 天', value: 30 },
  { label: '永久', value: 0 },
]

function openHandle(row: ClReportRow) {
  handleTarget.value = row
  handleForm.violation = true
  handleForm.remark = ''
  handleForm.banDays = 7
  handleForm.banReason = ''
  handleVisible.value = true
}

async function submitHandle() {
  if (!handleTarget.value) {
    return
  }
  handleLoading.value = true
  try {
    const res = await api.reportHandle(handleTarget.value.id, {
      violation: handleForm.violation,
      remark: handleForm.remark || undefined,
      // 只有「判定违规 + 填了封禁原因」才封
      ban_days: handleForm.banDays,
      ban_reason: handleForm.violation ? (handleForm.banReason || undefined) : undefined,
    })
    if (res.code === ResultCode.SUCCESS) {
      msg.success(handleForm.banReason !== '' && handleForm.violation ? '已判定违规并封禁' : '处理完成')
      handleVisible.value = false
      proTableRef.value?.refresh()
    }
    else {
      msg.error(res.message)
    }
  }
  finally {
    handleLoading.value = false
  }
}

// ---------- 关联消息上下文 ----------
const contextVisible = ref(false)
const contextLoading = ref(false)
const contextMessages = ref<ClMessageRow[]>([])
const contextReport = ref<ClReportRow | null>(null)

async function openContext(row: ClReportRow) {
  if (!row.message_id) {
    msg.warning('这条举报没有关联消息')
    return
  }
  contextReport.value = row
  contextVisible.value = true
  contextLoading.value = true
  try {
    const res = await api.messageContext(row.message_id)
    contextMessages.value = res.code === ResultCode.SUCCESS ? res.data : []
  }
  finally {
    contextLoading.value = false
  }
}

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '举报处理',
    subTitle: () => '处理结果会通知举报人；判定违规时可以顺手封了被举报人。',
  },
  searchOptions: { show: false },
  requestOptions: {
    api: (params: Record<string, any>) => api.reportList({ ...params, status: status.value }),
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: [],
  tableColumns: [
    {
      label: '举报人',
      prop: 'reporter_nickname',
      width: '170px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.reporter_id, nickname: row.reporter_nickname, avatar: row.reporter_avatar, gender: row.reporter_gender }} clickable={false} />,
    },
    {
      label: '被举报人',
      prop: 'target_nickname',
      width: '170px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.target_id, nickname: row.target_nickname, avatar: row.target_avatar, gender: row.target_gender }} clickable={false} />,
    },
    { label: '原因', prop: 'reason_label', width: '110px' },
    { label: '描述', prop: 'content', showOverflowTooltip: true },
    {
      label: '截图',
      prop: 'images',
      width: '120px',
      cellRender: ({ row }) => (
        row.images?.length
          ? <ClImagePreview src={row.images} size={44} />
          : <span class="text-gray-400">—</span>
      ),
    },
    {
      label: '关联消息',
      prop: 'message_id',
      width: '100px',
      cellRender: ({ row }) => (
        row.message_id
          ? <el-link type="primary" onClick={() => openContext(row)}>查看上下文</el-link>
          : <span class="text-gray-400">—</span>
      ),
    },
    { label: '时间', prop: 'created_at', width: '170px' },
    {
      label: '状态',
      prop: 'status',
      width: '110px',
      cellRender: ({ row }) => <ClStatusTag status={row.status === 1 ? 'VIOLATION' : row.status === 2 ? 'INNOCENT' : 'PENDING'} />,
    },
    {
      type: 'operation',
      label: '操作',
      width: '110px',
      operationConfigure: {
        actions: [
          {
            name: 'handle',
            text: '处理',
            icon: 'ri:gavel-line',
            show: ({ row }) => row.status === 0 && hasAuth('cl:report:handle'),
            onClick: ({ row }) => openHandle(row),
          },
        ],
      },
    },
  ],
})
</script>

<template>
  <div class="mine-layout">
    <el-tabs v-model="status" class="px-3" @tab-change="onTabChange">
      <el-tab-pane v-for="tab in TABS" :key="tab.value" :label="tab.label" :name="tab.value" />
    </el-tabs>

    <MaProTable ref="proTableRef" :options="options" :schema="schema" />

    <el-dialog v-model="handleVisible" title="处理举报" width="560px">
      <el-descriptions :column="1" border size="small" class="mb-3">
        <el-descriptions-item label="举报人">
          {{ handleTarget?.reporter_nickname }}
        </el-descriptions-item>
        <el-descriptions-item label="被举报人">
          {{ handleTarget?.target_nickname }}
        </el-descriptions-item>
        <el-descriptions-item label="举报原因">
          {{ handleTarget?.reason_label }}
        </el-descriptions-item>
        <el-descriptions-item label="补充说明">
          {{ handleTarget?.content || '—' }}
        </el-descriptions-item>
      </el-descriptions>

      <el-form label-width="90px">
        <el-form-item label="判定结果">
          <el-radio-group v-model="handleForm.violation">
            <el-radio :value="true">
              判定违规
            </el-radio>
            <el-radio :value="false">
              未发现违规
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="处理说明">
          <el-input v-model="handleForm.remark" maxlength="200" placeholder="会一并通知举报人" />
        </el-form-item>
        <template v-if="handleForm.violation">
          <el-form-item label="封禁时长">
            <el-select v-model="handleForm.banDays">
              <el-option v-for="item in BAN_DAYS" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item label="封禁原因">
            <el-input v-model="handleForm.banReason" maxlength="200" placeholder="留空就只判定、不封号" />
          </el-form-item>
        </template>
      </el-form>

      <template #footer>
        <el-button @click="handleVisible = false">
          取消
        </el-button>
        <el-button type="primary" :loading="handleLoading" @click="submitHandle">
          提交处理
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="contextVisible" title="关联消息上下文" width="720px">
      <div v-loading="contextLoading">
        <div class="mb-2 text-sm text-gray-500">
          被举报的是 ID {{ contextReport?.message_id }} 那条
        </div>
        <el-table :data="contextMessages" size="small" border max-height="420">
          <el-table-column prop="created_at" label="时间" width="160" />
          <el-table-column prop="sender_nickname" label="发送方" width="120" />
          <el-table-column prop="content" label="内容" show-overflow-tooltip />
          <el-table-column label="价格" width="80" align="right">
            <template #default="{ row }">
              <span v-if="row.price_coins > 0">{{ row.price_coins }} 币</span>
              <span v-else class="text-gray-400">免费</span>
            </template>
          </el-table-column>
          <el-table-column label="" width="60">
            <template #default="{ row }">
              <el-tag v-if="row.id === contextReport?.message_id" type="danger" size="small" disable-transitions>
                被举报
              </el-tag>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>
  </div>
</template>
