<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'ClAmount' })

const props = defineProps<{
  /** 数值。金币/积分是整数个，`fen` 是「分」（1 元 = 100 分）。 */
  value?: number | null
  /** coin 金币 / point 积分 / fen 人民币分 */
  type?: 'coin' | 'point' | 'fen'
}>()

const text = computed<string>(() => {
  const amount = props.value ?? 0
  switch (props.type ?? 'coin') {
    case 'point':
      return `${amount.toLocaleString('zh-CN')} 积分`
    case 'fen':
      return `¥${(amount / 100).toFixed(2)}`
    default:
      return `${amount.toLocaleString('zh-CN')} 金币`
  }
})
</script>

<template>
  <span class="cl-amount" :class="`cl-amount--${props.type ?? 'coin'}`">{{ text }}</span>
</template>

<style scoped lang="scss">
.cl-amount {
  @apply font-medium tabular-nums;
}

.cl-amount--coin {
  @apply text-amber-600;
}

.cl-amount--point {
  @apply text-violet-600;
}

/* 钱是真钱，颜色跟主题走，不跟着金币/积分的配色走 */
.cl-amount--fen {
  color: var(--el-color-primary);

  @apply font-semibold;
}
</style>
