<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import * as api from '../../../api/finance'
import { useMessage } from '@/hooks/useMessage.ts'

/**
 * 平台收入（docs/07 第 3.14 节）。
 *
 * 按天汇总。**毛收入 = 金币 ÷ coin.per_yuan − 积分 ÷ point.per_yuan**（docs/04 第 4 节）：
 * 收进来的是金币，发出去的是积分，两者各自按自己的汇率折成钱再相减 —— 这个数由服务端算好，
 * 客户端不自己除（除错了没人看得出来）。
 */
defineOptions({ name: 'cl:finance:income' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const totals = reactive({ coins: 0, points: 0, incomeFen: 0 })
const exporting = ref(false)

const query = reactive({
  from: new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10),
  to: new Date().toISOString().slice(0, 10),
})

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '平台收入',
    subTitle: () => `合计：收 ${totals.coins} 金币 / 发 ${totals.points} 积分 / 毛收入 ${api.fenText(totals.incomeFen)}`,
  },
  searchOptions: { show: false },
  requestOptions: {
    // 这个接口不分页（按天汇总，最多几百行），包装成表格要的形状即可
    api: async () => {
      const res = await api.income({ from: query.from, to: query.to })
      if (res.code !== 200) {
        return res as any
      }
      totals.coins = res.data.total_coins
      totals.points = res.data.total_points
      totals.incomeFen = res.data.total_income_fen

      return { code: 200, message: '成功', data: { list: res.data.list, total: res.data.list.length } } as any
    },
  },
})

const schema: MaProTableSchema = {
  searchItems: [],
  tableColumns: [
    { label: '日期', prop: 'date', width: '140px' },
    { label: '私信收入(金币)', prop: 'message_coins', width: '140px', align: 'right' },
    { label: '礼物收入(金币)', prop: 'gift_coins', width: '140px', align: 'right' },
    { label: '会员收入(金币)', prop: 'vip_coins', width: '140px', align: 'right' },
    {
      label: '收进来合计',
      prop: 'coins',
      width: '130px',
      align: 'right',
      cellRender: ({ row }) => <span class="text-amber-600 font-medium">{row.coins}</span>,
    },
    {
      label: '发给女用户积分',
      prop: 'points',
      width: '150px',
      align: 'right',
      cellRender: ({ row }) => <span class="text-violet-600">{row.points}</span>,
    },
    {
      label: '平台毛收入',
      prop: 'income_fen',
      align: 'right',
      cellRender: ({ row }) => <span class="font-medium">{api.fenText(row.income_fen)}</span>,
    },
  ],
}

async function exportExcel() {
  exporting.value = true
  try {
    const res = await useHttp().request<Blob>({
      url: `/admin/cl/finance/income/export?from=${query.from}&to=${query.to}`,
      method: 'get',
      responseType: 'blob',
    })
    const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `平台收入-${query.from}-${query.to}.xlsx`
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
</script>

<template>
  <div class="mine-layout">
    <div class="flex items-center gap-3 px-3 pt-3">
      <el-date-picker v-model="query.from" type="date" value-format="YYYY-MM-DD" placeholder="开始日期" />
      <span class="text-gray-400">至</span>
      <el-date-picker v-model="query.to" type="date" value-format="YYYY-MM-DD" placeholder="结束日期" />
      <el-button type="primary" @click="proTableRef?.refresh()">
        查询
      </el-button>
      <el-button :loading="exporting" @click="exportExcel">
        导出 Excel
      </el-button>
    </div>

    <MaProTable ref="proTableRef" :options="options" :schema="schema" />
  </div>
</template>
