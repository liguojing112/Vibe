<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import * as api from '../../../api/finance'
import ClUserCell from '../../../components/ClUserCell.vue'

/**
 * 充值记录（docs/07 第 3.11 节）。
 *
 * 底部合计放标题下面一直显示 —— 「这一批一共充了多少」是客服对账时第一个要看数。
 */
defineOptions({ name: 'cl:finance:recharge-list' })

const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const totals = reactive({ coins: 0, amountFen: 0 })

const CHANNEL_TEXT: Record<string, string> = {
  WECHAT: '微信',
  ALIPAY: '支付宝',
  OTHER: '其他',
  GIFT: '赠送',
}

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '充值记录',
    subTitle: () => `当前筛选合计：${totals.coins} 金币 / ${api.fenText(totals.amountFen)}`,
  },
  searchOptions: { fold: true },
  requestOptions: {
    // 合计由服务端用**同一套筛选条件**算（前端自己累加会只累当页，对不上）
    api: async (params: Record<string, any>) => {
      const res = await api.rechargeList(params)
      if (res.code === 200) {
        totals.coins = res.data.coins
        totals.amountFen = res.data.amount_fen
      }
      return res
    },
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: [
    { label: '用户 ID', prop: 'user_id', render: 'input', renderProps: { clearable: true } },
    { label: '操作人 ID', prop: 'operator_id', render: 'input', renderProps: { clearable: true } },
    {
      label: '支付方式',
      prop: 'pay_channel',
      render: 'select',
      renderProps: {
        clearable: true,
        options: Object.entries(CHANNEL_TEXT).map(([value, label]) => ({ label, value })),
      },
    },
    { label: '开始日期', prop: 'created_from', render: 'date-picker', renderProps: { type: 'date', valueFormat: 'YYYY-MM-DD' } },
    { label: '结束日期', prop: 'created_to', render: 'date-picker', renderProps: { type: 'date', valueFormat: 'YYYY-MM-DD' } },
  ],
  tableColumns: [
    { label: '单号', prop: 'order_no', width: '200px' },
    {
      label: '用户',
      prop: 'user_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.user_id, nickname: row.user_nickname, avatar: row.user_avatar, gender: 1 }} clickable={false} />,
    },
    {
      label: '金币',
      prop: 'coins',
      width: '110px',
      align: 'right',
      cellRender: ({ row }) => (
        <span class="text-amber-600 font-medium">
          +
          {row.coins}
        </span>
      ),
    },
    {
      label: '实收金额',
      prop: 'amount_fen',
      width: '120px',
      align: 'right',
      cellRender: ({ row }) => <span>{api.fenText(row.amount_fen)}</span>,
    },
    {
      label: '支付方式',
      prop: 'pay_channel',
      width: '100px',
      cellRender: ({ row }) => <span>{CHANNEL_TEXT[row.pay_channel] ?? row.pay_channel}</span>,
    },
    { label: '操作人', prop: 'operator_name', width: '110px' },
    { label: '备注', prop: 'remark', showOverflowTooltip: true },
    { label: '时间', prop: 'created_at', width: '170px' },
  ],
})
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema" />
  </div>
</template>
