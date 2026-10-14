<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClWithdrawRow } from '../../../api/finance'
import * as api from '../../../api/finance'
import ClReasonDialog from '../../../components/ClReasonDialog.vue'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * 提现审核（docs/07 第 3.13 节、docs/06 第 9.2 节）。
 *
 * 三个动作的状态流转是**服务端守着的**（`WithdrawService`）：通过只是改状态、积分还冻着；
 * 驳回要把冻结原路退回；标记已打款才是真扣掉。界面上只把该出现的按钮露出来，
 * 但就算硬调，服务端也会拦。
 */
defineOptions({ name: 'cl:withdraw:list' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const totals = reactive({ amountFen: 0, actualFen: 0 })
const exporting = ref(false)

const TABS = [
  { label: '待审核', value: 0 },
  { label: '待打款', value: 1 },
  { label: '已打款', value: 3 },
  { label: '已驳回', value: 2 },
]
const status = ref(0)

const query = reactive({ from: '', to: '' })

function onTabChange() {
  proTableRef.value?.setRequestParams({ status: status.value, from: query.from, to: query.to }, true)
}

function reload() {
  proTableRef.value?.setRequestParams({ status: status.value, from: query.from, to: query.to }, true)
}

// ---------- 通过 ----------
async function approve(row: ClWithdrawRow) {
  const res = await api.withdrawApprove(row.id)
  after(res, '已通过，等待打款')
}

// ---------- 驳回（必填原因）----------
const rejectVisible = ref(false)
const rejectTarget = ref<ClWithdrawRow | null>(null)
const rejecting = ref(false)
// 常用原因：点了先填进弹窗（弹窗自己管输入框，用 defaultReason 传进去）
const rejectReason = ref('')
const COMMON_REASONS = ['收款信息有误', '实名信息不符', '疑似刷单', '其他']

function openReject(row: ClWithdrawRow) {
  rejectTarget.value = row
  rejectReason.value = ''
  rejectVisible.value = true
}

async function submitReject(reason: string) {
  rejecting.value = true
  try {
    const res = await api.withdrawReject(rejectTarget.value!.id, reason)
    after(res, '已驳回，冻结的积分已原路退回')
    if (res.code === ResultCode.SUCCESS) {
      rejectVisible.value = false
    }
  }
  finally {
    rejecting.value = false
  }
}

// ---------- 标记已打款（必填打款流水号）----------
const paidVisible = ref(false)
const paidTarget = ref<ClWithdrawRow | null>(null)
const payProof = ref('')
const paying = ref(false)

function openPaid(row: ClWithdrawRow) {
  paidTarget.value = row
  payProof.value = ''
  paidVisible.value = true
}

async function submitPaid() {
  if (payProof.value.trim() === '') {
    msg.error('必须填打款流水号或备注')
    return
  }
  paying.value = true
  try {
    const res = await api.withdrawPaid(paidTarget.value!.id, payProof.value.trim())
    after(res, '已标记打款完成')
    if (res.code === ResultCode.SUCCESS) {
      paidVisible.value = false
    }
  }
  finally {
    paying.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  try {
    const params = new URLSearchParams({ status: String(status.value) })
    if (query.from) {
      params.append('from', query.from)
    }
    if (query.to) {
      params.append('to', query.to)
    }

    const res = await useHttp().request<Blob>({
      url: `/admin/cl/withdraw/export?${params.toString()}`,
      method: 'get',
      responseType: 'blob',
    })
    const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `提现单-${Date.now()}.xlsx`
    link.click()
    URL.revokeObjectURL(url)
  }
  catch (error: any) {
    msg.alertError(error)
  }
  finally {
    exporting.value = false
  }
}

function after(res: any, successText: string) {
  if (res.code === ResultCode.SUCCESS) {
    msg.success(successText)
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '提现审核',
    subTitle: () => `当前筛选合计：申请 ${api.fenText(totals.amountFen)} / 实到 ${api.fenText(totals.actualFen)}`,
  },
  searchOptions: { show: false },
  requestOptions: {
    api: async (params: Record<string, any>) => {
      const res = await api.withdrawList({ ...params, status: status.value, from: query.from, to: query.to })
      if (res.code === 200) {
        totals.amountFen = res.data.amount_fen
        totals.actualFen = res.data.actual_fen
      }
      return res
    },
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: [],
  tableColumns: [
    { label: '单号', prop: 'order_no', width: '190px' },
    {
      label: '用户',
      prop: 'user_nickname',
      width: '200px',
      cellRender: ({ row }) => (
        <div>
          <ClUserCell user={{ id: row.user_id, nickname: row.user_nickname, avatar: row.user_avatar, gender: 2 }} clickable={false} />
          <div class="text-xs text-gray-400">
            Lv
            {row.level}
            {' '}
            · 实名：
            {row.real_name || '—'}
          </div>
        </div>
      ),
    },
    {
      label: '申请金额',
      prop: 'amount_fen',
      width: '110px',
      align: 'right',
      cellRender: ({ row }) => <span class="font-medium">{api.fenText(row.amount_fen)}</span>,
    },
    {
      label: '手续费',
      prop: 'fee_fen',
      width: '100px',
      align: 'right',
      cellRender: ({ row }) => <span class="text-gray-500">{api.fenText(row.fee_fen)}</span>,
    },
    {
      label: '实际到账',
      prop: 'actual_fen',
      width: '110px',
      align: 'right',
      cellRender: ({ row }) => <span class="text-green-600">{api.fenText(row.actual_fen)}</span>,
    },
    { label: '冻结积分', prop: 'points', width: '100px', align: 'right' },
    { label: '收款方式', prop: 'account_type_text', width: '100px' },
    { label: '收款账号', prop: 'account_no', width: '180px' },
    { label: '收款人', prop: 'account_name', width: '100px' },
    { label: '申请时间', prop: 'created_at', width: '170px' },
    {
      label: '状态',
      prop: 'status',
      width: '100px',
      cellRender: ({ row }) => (
        <div>
          <el-tag
            type={row.status === 0 ? 'warning' : row.status === 3 ? 'success' : row.status === 2 ? 'danger' : 'info'}
            size="small"
            disable-transitions
          >
            {row.status_text}
          </el-tag>
          {row.reject_reason && <div class="mt-1 text-xs text-gray-400">{row.reject_reason}</div>}
        </div>
      ),
    },
    {
      type: 'operation',
      label: '操作',
      width: '180px',
      operationConfigure: {
        actions: [
          {
            name: 'approve',
            text: '通过',
            icon: 'ri:check-line',
            show: ({ row }) => row.status === 0 && hasAuth('cl:withdraw:approve'),
            onClick: ({ row }) => approve(row),
          },
          {
            name: 'paid',
            text: '标记已打款',
            icon: 'ri:bank-card-line',
            show: ({ row }) => row.status === 1 && hasAuth('cl:withdraw:paid'),
            onClick: ({ row }) => openPaid(row),
          },
          {
            name: 'reject',
            text: '驳回',
            icon: 'ri:close-line',
            show: ({ row }) => (row.status === 0 || row.status === 1) && hasAuth('cl:withdraw:reject'),
            onClick: ({ row }) => openReject(row),
          },
        ],
      },
    },
  ],
})
</script>

<template>
  <div class="mine-layout">
    <div class="flex items-center gap-3 px-3">
      <el-tabs v-model="status" class="flex-1" @tab-change="onTabChange">
        <el-tab-pane v-for="tab in TABS" :key="tab.value" :label="tab.label" :name="tab.value" />
      </el-tabs>
    </div>

    <div class="flex items-center gap-3 px-3">
      <el-date-picker v-model="query.from" type="date" value-format="YYYY-MM-DD" placeholder="开始日期" />
      <span class="text-gray-400">至</span>
      <el-date-picker v-model="query.to" type="date" value-format="YYYY-MM-DD" placeholder="结束日期" />
      <el-button type="primary" @click="reload">
        查询
      </el-button>
      <el-button v-auth="['cl:withdraw:list']" :loading="exporting" @click="exportExcel">
        导出 Excel（给财务打款）
      </el-button>
    </div>

    <MaProTable ref="proTableRef" :options="options" :schema="schema" />

    <ClReasonDialog
      v-model="rejectVisible"
      title="驳回提现"
      :message="`确定驳回「${rejectTarget?.user_nickname ?? ''}」的 ${api.fenText(rejectTarget?.amount_fen ?? 0)} 提现吗？冻结的积分会原路退回。`"
      confirm-text="确认驳回"
      :loading="rejecting"
      :default-reason="rejectReason"
      @confirm="submitReject"
    >
      <div class="mb-2">
        <el-tag
          v-for="reason in COMMON_REASONS"
          :key="reason"
          class="mr-1 cursor-pointer"
          :type="rejectReason === reason ? 'primary' : 'info'"
          @click="rejectReason = reason"
        >
          {{ reason }}
        </el-tag>
      </div>
    </ClReasonDialog>

    <el-dialog v-model="paidVisible" title="标记已打款" width="460px">
      <el-descriptions :column="1" border size="small" class="mb-3">
        <el-descriptions-item label="收款人">
          {{ paidTarget?.account_name }}（{{ paidTarget?.account_type_text }} {{ paidTarget?.account_no }}）
        </el-descriptions-item>
        <el-descriptions-item label="实际到账">
          {{ api.fenText(paidTarget?.actual_fen ?? 0) }}
        </el-descriptions-item>
      </el-descriptions>
      <el-input v-model="payProof" maxlength="200" placeholder="打款流水号或备注（必填）" />
      <template #footer>
        <el-button @click="paidVisible = false">
          取消
        </el-button>
        <el-button type="primary" :loading="paying" @click="submitPaid">
          确认已打款
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>
