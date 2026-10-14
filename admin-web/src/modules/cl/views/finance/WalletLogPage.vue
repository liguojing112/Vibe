<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import * as api from '../../api/finance'
import ClUserCell from '../../components/ClUserCell.vue'

/**
 * 金币流水 / 积分流水（docs/07 第 3.12 节）。两张表结构差不多，所以一个组件带两个页面。
 *
 * 差别只有三处：接口、可选的类型、以及**积分流水多两列**（冻结变动 / 冻结后）——
 * 提现那几笔就是靠这两列看明白的（冻结 −、可用 +）。
 */
const props = defineProps<{ points: boolean }>()

const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

const TYPE_TEXT: Record<string, string> = {
  RECHARGE: '充值',
  MESSAGE: '私信消费',
  GIFT: '礼物消费',
  VIP: '会员',
  TASK: '任务',
  ADMIN_DEDUCT: '后台扣减',
  MESSAGE_INCOME: '私信收入',
  GIFT_INCOME: '礼物收入',
  WITHDRAW_FREEZE: '提现冻结',
  WITHDRAW_REFUND: '提现驳回退回',
  WITHDRAW_PAID: '提现成功',
  ADMIN_ADJUST: '后台调整',
}

const typeOptions = computed(() => Object.entries(TYPE_TEXT)
  .filter(([value]) => props.points
    ? ['MESSAGE_INCOME', 'GIFT_INCOME', 'TASK', 'WITHDRAW_FREEZE', 'WITHDRAW_REFUND', 'WITHDRAW_PAID', 'ADMIN_ADJUST'].includes(value)
    : ['RECHARGE', 'MESSAGE', 'GIFT', 'VIP', 'TASK', 'ADMIN_DEDUCT'].includes(value))
  .map(([value, label]) => ({ label, value })))

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => (props.points ? '积分流水' : '金币流水'),
    subTitle: () => '只读。变动后余额是当时的余额快照，可以直接对账。',
  },
  searchOptions: { fold: true },
  requestOptions: {
    api: (params: Record<string, any>) => (props.points ? api.pointLogs(params) : api.coinLogs(params)),
  },
})

/** 变动那一列：加号绿、减号红。 */
function changeCell(row: any) {
  const value = Number(row.change)
  const frozen = row.frozen_change

  return (
    <span>
      <span class={value > 0 ? 'font-medium text-green-600' : value < 0 ? 'font-medium text-red-500' : 'text-gray-400'}>
        {value > 0 ? `+${value}` : value}
      </span>
      {frozen !== null && frozen !== undefined && frozen !== 0 && (
        <span class="ml-1 text-xs text-gray-400">
          （冻结
          {frozen > 0 ? `+${frozen}` : frozen}
          ）
        </span>
      )}
    </span>
  )
}

const schema = computed<MaProTableSchema>(() => ({
  searchItems: [
    { label: '用户 ID', prop: 'user_id', render: 'input', renderProps: { clearable: true } },
    {
      label: '类型',
      prop: 'type',
      render: 'select',
      renderProps: { clearable: true, options: typeOptions.value },
    },
    { label: '开始日期', prop: 'created_from', render: 'date-picker', renderProps: { type: 'date', valueFormat: 'YYYY-MM-DD' } },
    { label: '结束日期', prop: 'created_to', render: 'date-picker', renderProps: { type: 'date', valueFormat: 'YYYY-MM-DD' } },
  ],
  tableColumns: [
    {
      label: '用户',
      prop: 'user_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.user_id, nickname: row.user_nickname, avatar: row.user_avatar }} clickable={false} />,
    },
    {
      label: '类型',
      prop: 'type',
      width: '120px',
      cellRender: ({ row }) => <span>{TYPE_TEXT[row.type] ?? row.type}</span>,
    },
    { label: '变动', prop: 'change', width: '140px', align: 'right', cellRender: ({ row }) => changeCell(row) },
    {
      label: '变动后余额',
      prop: 'balance_after',
      width: '120px',
      align: 'right',
      cellRender: ({ row }) => <span class="tabular-nums">{row.balance_after}</span>,
    },
    ...(props.points
      ? [
          { label: '冻结变动', prop: 'frozen_change', width: '110px', align: 'right' as const },
          { label: '冻结后', prop: 'frozen_after', width: '100px', align: 'right' as const },
        ]
      : []),
    { label: '标题', prop: 'title', showOverflowTooltip: true },
    {
      label: '关联单号',
      prop: 'ref_id',
      width: '150px',
      cellRender: ({ row }) => <span class="text-xs text-gray-400">{row.ref_type ? `${row.ref_type}#${row.ref_id ?? '-'}` : '—'}</span>,
    },
    { label: '时间', prop: 'created_at', width: '170px' },
  ],
}))
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema" />
  </div>
</template>
