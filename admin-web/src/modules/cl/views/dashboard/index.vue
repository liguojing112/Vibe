<script setup lang="ts">
import type { ClDashboardCards, ClDashboardChart, ClDashboardOverview } from '../../api/dashboard'
import * as api from '../../api/dashboard'
import { fenText } from '../../api/finance'
import { useColorMode } from '@vueuse/core'
import { useEcharts } from '@/hooks/useEcharts.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 数据看板（docs/07 第 3.1 节）。
 *
 * 三块：今日经营数字、「待办」卡片（点一下直接跳到对应页面处理）、近 30 天三个图。
 *
 * **所有金额都是「分」**，展示时才换算 —— 后端不返回元，避免两边各留一位小数
 * 最后对不上（`fenText` 和资金管理那几个页面共用一个）。
 *
 * 图的数据在接口里是**连续 30 天、缺的天补 0**的，所以这里直接铺上去，
 * 不用自己补日期；不补零的话 echarts 会把两个有数据的天直接连起来，看着像天天都有量。
 */
defineOptions({ name: 'cl:dashboard' })

const router = useRouter()
const isDark = useColorMode()

const loading = ref(false)
const error = ref('')
const data = ref<ClDashboardOverview | null>(null)

const EMPTY_CARDS: ClDashboardCards = {
  new_male: 0,
  new_female: 0,
  active_users: 0,
  paid_messages: 0,
  gift_coins: 0,
  recharge_coins: 0,
  recharge_amount_fen: 0,
  platform_income_fen: 0,
  pending_profile: 0,
  pending_realname: 0,
  pending_reports: 0,
  pending_messages: 0,
  pending_withdraw_count: 0,
  pending_withdraw_fen: 0,
}

const EMPTY_CHART: ClDashboardChart = {
  dates: [],
  new_male: [],
  new_female: [],
  recharge_amount_fen: [],
  platform_income_fen: [],
}

const cards = computed<ClDashboardCards>(() => data.value?.cards ?? EMPTY_CARDS)
const chart = computed<ClDashboardChart>(() => data.value?.chart ?? EMPTY_CHART)

/** 主题色 token：背景用 `light-9`、前景用本色，深色主题自动跟着变。 */
type Tone = 'primary' | 'success' | 'warning' | 'danger' | 'info'

interface CardDef {
  key: string
  title: string
  text: string
  icon: string
  tone: Tone
  /** 有值就是可点的待办卡片。 */
  path?: string
}

const todayCards = computed<CardDef[]>(() => [
  { key: 'new_male', title: '今日新增男用户', text: String(cards.value.new_male), icon: 'ri:men-line', tone: 'primary' },
  { key: 'new_female', title: '今日新增女用户', text: String(cards.value.new_female), icon: 'ri:women-line', tone: 'danger' },
  { key: 'active_users', title: '今日活跃用户', text: String(cards.value.active_users), icon: 'ri:pulse-line', tone: 'success' },
  { key: 'paid_messages', title: '今日付费私信', text: `${cards.value.paid_messages} 条`, icon: 'ri:chat-3-line', tone: 'primary' },
  { key: 'gift_coins', title: '今日礼物金币', text: `${cards.value.gift_coins.toLocaleString('zh-CN')} 金币`, icon: 'ri:gift-line', tone: 'warning' },
  { key: 'recharge_coins', title: '今日充值金币', text: `${cards.value.recharge_coins.toLocaleString('zh-CN')} 金币`, icon: 'ri:coins-line', tone: 'warning' },
  { key: 'recharge_amount_fen', title: '今日充值实收', text: fenText(cards.value.recharge_amount_fen), icon: 'ri:wallet-3-line', tone: 'success' },
  { key: 'platform_income_fen', title: '今日平台毛收入', text: fenText(cards.value.platform_income_fen), icon: 'ri:line-chart-line', tone: 'primary' },
])

const todoCards = computed<CardDef[]>(() => [
  { key: 'pending_profile', title: '待审资料', text: String(cards.value.pending_profile), icon: 'ri:profile-line', tone: 'warning', path: '/cl/audit/profile' },
  { key: 'pending_realname', title: '待审实名', text: String(cards.value.pending_realname), icon: 'ri:id-card-line', tone: 'warning', path: '/cl/audit/realname' },
  { key: 'pending_reports', title: '待处理举报', text: String(cards.value.pending_reports), icon: 'ri:alert-line', tone: 'danger', path: '/cl/chat/report' },
  { key: 'pending_messages', title: '待审消息', text: String(cards.value.pending_messages), icon: 'ri:message-2-line', tone: 'danger', path: '/cl/chat/pending' },
  {
    key: 'pending_withdraw_count',
    title: '待审核提现',
    text: `${cards.value.pending_withdraw_count} 笔`,
    icon: 'ri:bank-card-line',
    tone: 'warning',
    path: '/cl/finance/withdraw',
  },
])

const pendingWithdrawText = computed(() => `申请金额合计 ${fenText(cards.value.pending_withdraw_fen)}`)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.overview()
    if (res.code === ResultCode.SUCCESS) {
      data.value = res.data
    }
    else {
      error.value = res.message
    }
  }
  finally {
    loading.value = false
  }
}

// ---------- 图 ----------

const rechargeRef = ref<HTMLDivElement>()
const incomeRef = ref<HTMLDivElement>()
const userRef = ref<HTMLDivElement>()

const axisColor = computed(() => (isDark.value === 'dark' ? '#a3a6ad' : '#6b7280'))
const splitColor = computed(() => (isDark.value === 'dark' ? '#2b2f38' : '#f0f2f5'))

/** 分 → 元的数组（图上用元，纵轴刻度才有意义）。 */
const yuan = (values: number[]) => values.map(value => Number((value / 100).toFixed(2)))

function axisBase() {
  return {
    axisLine: { lineStyle: { color: splitColor.value } },
    axisLabel: { color: axisColor.value, fontSize: 11 },
  }
}

function rechargeOption() {
  return {
    tooltip: { trigger: 'axis' },
    grid: { top: 16, right: 16, bottom: 24, left: 52 },
    xAxis: { type: 'category', data: chart.value.dates, ...axisBase() },
    yAxis: { type: 'value', ...axisBase(), splitLine: { lineStyle: { color: splitColor.value } } },
    series: [{
      name: '充值实收',
      type: 'bar',
      data: yuan(chart.value.recharge_amount_fen),
      barMaxWidth: 16,
      itemStyle: { color: '#409eff', borderRadius: [3, 3, 0, 0] },
      tooltip: { valueFormatter: (value: number) => `¥${Number(value).toFixed(2)}` },
    }],
  }
}

function incomeOption() {
  return {
    tooltip: { trigger: 'axis' },
    grid: { top: 16, right: 16, bottom: 24, left: 52 },
    xAxis: { type: 'category', data: chart.value.dates, ...axisBase() },
    yAxis: { type: 'value', ...axisBase(), splitLine: { lineStyle: { color: splitColor.value } } },
    series: [{
      name: '平台毛收入',
      type: 'line',
      smooth: true,
      showSymbol: false,
      data: yuan(chart.value.platform_income_fen),
      lineStyle: { color: '#67c23a' },
      itemStyle: { color: '#67c23a' },
      areaStyle: { color: 'rgba(103,194,58,0.12)' },
      tooltip: { valueFormatter: (value: number) => `¥${Number(value).toFixed(2)}` },
    }],
  }
}

function userOption() {
  return {
    tooltip: { trigger: 'axis' },
    legend: { data: ['男', '女'], textStyle: { color: axisColor.value } },
    grid: { top: 40, right: 16, bottom: 24, left: 44 },
    xAxis: { type: 'category', data: chart.value.dates, ...axisBase() },
    yAxis: { type: 'value', ...axisBase(), splitLine: { lineStyle: { color: splitColor.value } } },
    series: [
      { name: '男', type: 'line', smooth: true, data: chart.value.new_male, lineStyle: { color: '#409eff' }, itemStyle: { color: '#409eff' } },
      { name: '女', type: 'line', smooth: true, data: chart.value.new_female, lineStyle: { color: '#f56c6c' }, itemStyle: { color: '#f56c6c' } },
    ],
  }
}

const rechargeChart = useEcharts(rechargeRef)
const incomeChart = useEcharts(incomeRef)
const userChart = useEcharts(userRef)

/**
 * ⚠️ **必须传普通对象，不能传 `computed`**。
 *
 * echarts 拿到 option 会先深拷贝一遍，而 `ComputedRefImpl` 上有 `toString`，
 * 拷到它就无限递归 —— 表现是整个页面白图 + 控制台 `RangeError: Maximum call stack size exceeded`，
 * 而且**没有图表渲染失败的提示**，只是画不出来（我这次就是先看到三个空框才发现）。
 * 所以数据变了要**重新调一次 `setOption`**，而不是把 computed 丢进去指望它自己跟。
 */
function renderCharts() {
  rechargeChart.setOption(rechargeOption())
  incomeChart.setOption(incomeOption())
  userChart.setOption(userOption())
}

watch([chart, axisColor, splitColor], renderCharts, { immediate: true })

onMounted(load)
</script>

<template>
  <div class="mine-layout">
    <el-card shadow="never" class="m-3">
      <template #header>
        <div class="flex items-start justify-between">
          <div>
            <div class="text-base font-medium">
              数据看板
            </div>
            <div class="mt-1 text-xs text-gray-500">
              按服务端日期统计，今天是 {{ data?.date ?? '—' }}。金额的「实收」是按后台充值单录的实收金额算的。
            </div>
          </div>
          <el-button :loading="loading" @click="load">
            刷新
          </el-button>
        </div>
      </template>

      <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="mb-3" />

      <div v-loading="loading" class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div v-for="item in todayCards" :key="item.key" class="cl-dash-card">
          <div
            class="cl-dash-card__icon"
            :style="{ background: `var(--el-color-${item.tone}-light-9)`, color: `var(--el-color-${item.tone})` }"
          >
            <ma-svg-icon :name="item.icon" :size="22" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="truncate text-xs text-gray-500">
              {{ item.title }}
            </div>
            <div class="mt-1 text-lg font-semibold tabular-nums">
              {{ item.text }}
            </div>
          </div>
        </div>
      </div>
    </el-card>

    <el-card shadow="never" class="m-3">
      <template #header>
        <div class="text-base font-medium">
          待办
        </div>
        <div class="mt-1 text-xs text-gray-500">
          点一下直接跳到对应页面去处理。
        </div>
      </template>

      <div v-loading="loading" class="grid grid-cols-2 gap-3 md:grid-cols-5">
        <div
          v-for="item in todoCards"
          :key="item.key"
          class="cl-dash-card cl-dash-card--link"
          @click="router.push(item.path as string)"
        >
          <div
            class="cl-dash-card__icon"
            :style="{ background: `var(--el-color-${item.tone}-light-9)`, color: `var(--el-color-${item.tone})` }"
          >
            <ma-svg-icon :name="item.icon" :size="22" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="truncate text-xs text-gray-500">
              {{ item.title }}
            </div>
            <div class="mt-1 text-lg font-semibold tabular-nums">
              {{ item.text }}
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3 text-xs text-gray-500">
        {{ pendingWithdrawText }}
      </div>
    </el-card>

    <div class="grid m-3 gap-3 xl:grid-cols-2">
      <el-card shadow="never">
        <template #header>
          <div class="text-base font-medium">
            近 30 天充值实收（元）
          </div>
        </template>
        <div ref="rechargeRef" class="h-300px" />
      </el-card>

      <el-card shadow="never">
        <template #header>
          <div class="text-base font-medium">
            近 30 天平台毛收入（元）
          </div>
          <div class="mt-1 text-xs text-gray-500">
            毛收入 = 消耗金币 ÷ 金币汇率 − 发放积分 ÷ 积分汇率，和「平台收入」页同一个口径。
          </div>
        </template>
        <div ref="incomeRef" class="h-300px" />
      </el-card>

      <el-card shadow="never" class="xl:col-span-2">
        <template #header>
          <div class="text-base font-medium">
            近 30 天新增用户
          </div>
        </template>
        <div ref="userRef" class="h-300px" />
      </el-card>
    </div>
  </div>
</template>

<style scoped lang="scss">
.cl-dash-card {
  @apply flex items-center gap-3 rounded-md border border-solid p-3;

  background-color: var(--el-bg-color);
  border-color: var(--el-border-color-lighter);
}

.cl-dash-card--link {
  cursor: pointer;
  transition: border-color 0.2s;

  &:hover {
    border-color: var(--el-color-primary);
  }
}

.cl-dash-card__icon {
  @apply h-11 w-11 flex flex-none items-center justify-center rounded-md;
}
</style>
