<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import * as api from '../../api/operation'
import ClAmount from '../../components/ClAmount.vue'
import ClUserCell from '../../components/ClUserCell.vue'

/** 会员开通记录（docs/07 第 3.18 节）：只读，看看谁什么时候开了多久。 */
defineOptions({ name: 'cl:operation:vip-order' })

const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '会员开通记录',
    subTitle: () => '买卖家都有：来源「后台」的是客服赠送，不扣金币。',
  },
  searchOptions: { fold: true },
  requestOptions: {
    api: (params: Record<string, any>) => api.vipOrders(params),
  },
})

const schema: MaProTableSchema = {
  searchItems: [
    { label: '用户 ID', prop: 'user_id', render: 'input', renderProps: { clearable: true } },
    {
      label: '来源',
      prop: 'source',
      render: 'select',
      renderProps: {
        clearable: true,
        options: [
          { label: '金币购买', value: 1 },
          { label: '后台开通', value: 2 },
        ],
      },
    },
  ],
  tableColumns: [
    { label: '单号', prop: 'order_no', width: '220px' },
    {
      label: '用户',
      prop: 'user_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.user_id, nickname: row.user_nickname, avatar: row.user_avatar, gender: 1 }} clickable={false} />,
    },
    { label: '套餐', prop: 'plan_name', width: '130px' },
    { label: '天数', prop: 'days', width: '80px' },
    {
      label: '金币',
      prop: 'price_coins',
      width: '110px',
      align: 'right',
      cellRender: ({ row }) => <ClAmount value={row.price_coins} type="coin" />,
    },
    {
      label: '来源',
      prop: 'source',
      width: '110px',
      cellRender: ({ row }) => (
        <el-tag type={row.source === 2 ? 'warning' : 'success'} size="small" disable-transitions>
          {row.source === 2 ? '后台开通' : '金币购买'}
        </el-tag>
      ),
    },
    { label: '开通前到期', prop: 'expire_before', width: '170px' },
    { label: '开通后到期', prop: 'expire_after', width: '170px' },
    { label: '操作人', prop: 'operator_name', width: '110px' },
    { label: '时间', prop: 'created_at', width: '170px' },
  ],
}
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema" />
  </div>
</template>
