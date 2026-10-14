<script setup lang="ts">
import type { ClConfigItem } from '../../api/operation'
import * as api from '../../api/operation'
import ClConfigForm from '../../components/ClConfigForm.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 配置分组页（docs/07 第 3.15、3.19、3.21、3.23 节）。
 *
 * 一个分组一个 Tab（单分组就直接铺），表单用 `ClConfigForm` —— 它按 `value_type`
 * 自动渲染成数字框 / 输入框 / 开关 / JSON 编辑器，所以加一个配置项不用改前端。
 *
 * `showExample` 打开时上面多一块**计费示例**：`07` 第 3.15 节要求「随输入实时更新」，
 * 所以它是拿当前表单值现算的，不是写死的文案。
 */
const props = withDefaults(defineProps<{
  groups: string[]
  title: string
  subtitle?: string
  /** 显示「Lv1 女用户收到 1 条私信」的算账示例（只有计费页要）。 */
  showExample?: boolean
  /** 算示例时用的等级（默认 Lv1）。 */
  exampleLevel?: number
}>(), {
  subtitle: '',
  showExample: false,
  exampleLevel: 1,
})

const msg = useMessage()
const loading = ref(true)
const saving = ref(false)
const active = ref(props.groups[0] ?? '')
const GROUP_LABELS: Record<string, string> = {
  billing: '基础',
  share: '分成',
  chat: '聊天规则',
  vip: '会员',
  withdraw: '提现',
  recharge: '充值档位与客服',
  agreement: '协议',
  compliance: '合规声明',
  feature: '功能开关',
}

/** 等级表（算示例要用 Lv1 的单价和分成；它不在 billing 组里，在 cl_level_config）。 */
const levelRows = ref<Array<{ level: number, message_price: number, message_share_rate: number }>>([])

const items = reactive<Record<string, ClConfigItem[]>>({})
const values = reactive<Record<string, Record<string, string>>>({})

async function load(group: string) {
  const res = await api.configGroup(group)
  if (res.code !== ResultCode.SUCCESS) {
    msg.error(res.message)
    return
  }
  items[group] = res.data.items
  values[group] = Object.fromEntries(res.data.items.map(item => [item.key, item.value]))
}

async function loadAll() {
  loading.value = true
  try {
    for (const group of props.groups) {
      await load(group)
    }
    const res = await api.levels()
    if (res.code === ResultCode.SUCCESS) {
      levelRows.value = res.data
    }
  }
  finally {
    loading.value = false
  }
}

async function save() {
  const group = active.value
  saving.value = true
  try {
    const res = await api.saveConfigGroup(group, { ...values[group] })
    if (res.code === ResultCode.SUCCESS) {
      msg.success('已保存，App 那边立刻生效')
      await load(group)
    }
    else {
      msg.error(res.message)
    }
  }
  finally {
    saving.value = false
  }
}

/**
 * 计费示例（`07` 第 3.15 节）：Lv1 女用户（**已实名**）收到 1 条私信，钱怎么分。
 *
 * 拿当前表单里的汇率和等级表的单价现算，所以改了汇率/分成保存后，这个数跟着变。
 * 算法和 `06` 第 3.4 节一致：积分 = 金币 × 分成比例 × (积分汇率 ÷ 金币汇率)。
 */
const example = computed(() => {
  const billing = values.billing ?? {}
  const lv1 = levelRows.value.find(row => Number(row.level) === 1)
  if (!lv1) {
    return null
  }

  const coinPerYuan = Math.max(1, Number(billing['coin.per_yuan'] ?? 10))
  const pointPerYuan = Math.max(1, Number(billing['point.per_yuan'] ?? 100))
  const price = Number(lv1.message_price)
  const shareCoins = price * Number(lv1.message_share_rate) / 10000

  return {
    price,
    points: Math.floor(shareCoins * pointPerYuan / coinPerYuan),
    pointsYuan: (shareCoins * pointPerYuan / coinPerYuan / pointPerYuan).toFixed(2),
    platformYuan: ((price - shareCoins) / coinPerYuan).toFixed(2),
  }
})

onMounted(loadAll)
</script>

<template>
  <div v-loading="loading">
    <el-card shadow="never" class="m-3">
      <template #header>
        <div class="text-base font-medium">
          {{ props.title }}
        </div>
        <div v-if="props.subtitle" class="mt-1 text-xs text-gray-500">
          {{ props.subtitle }}
        </div>
      </template>

      <el-alert
        v-if="props.showExample && example"
        type="warning"
        :closable="false"
        class="mb-3"
        :title="`Lv1 女用户（已实名）收到 1 条私信：男方 −${example.price} 金币，女方 +${example.points} 积分（¥${example.pointsYuan}），平台留 ¥${example.platformYuan}`"
      />

      <el-tabs v-if="props.groups.length > 1" v-model="active">
        <el-tab-pane
          v-for="group in props.groups"
          :key="group"
          :label="GROUP_LABELS[group] ?? group"
          :name="group"
        />
      </el-tabs>

      <ClConfigForm
        v-if="items[active]"
        v-model="values[active]"
        :items="items[active]"
      />

      <div class="mt-3">
        <el-button type="primary" :loading="saving" @click="save">
          保存
        </el-button>
      </div>
    </el-card>
  </div>
</template>
