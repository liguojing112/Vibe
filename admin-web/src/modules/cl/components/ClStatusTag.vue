<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'ClStatusTag' })

const props = defineProps<{
  /**
   * 状态。英文枚举名（`PENDING`/`PASSED`/`PAID`…）或服务端给的中文（`待审核`/`已打款`）都认。
   * 认不出来的按 info 处理，不会因为后端加了个新状态就画错颜色。
   */
  status?: string | number | null
  /** 显示文字。不传就用状态自己的名字（`待审核` 之类）。 */
  text?: string
}>()

type TagType = 'success' | 'warning' | 'danger' | 'info'

/** 颜色口径固定死在 docs/03 第 8 节第 5 条：待处理橙 / 成功绿 / 驳回红 / 关闭灰。 */
const EXACT: Record<string, TagType> = {
  PENDING: 'warning',
  AUDITING: 'warning',
  PROCESSING: 'warning',
  APPROVED: 'success',
  PASSED: 'success',
  PAID: 'success',
  SUCCESS: 'success',
  NORMAL: 'success',
  DONE: 'success',
  REJECTED: 'danger',
  VIOLATION: 'danger',
  BANNED: 'danger',
  FAILED: 'danger',
  INNOCENT: 'info',
  CANCELLED: 'info',
  CLOSED: 'info',
  EXPIRED: 'info',
  DISABLED: 'info',
  NONE: 'info',
}

const TEXT: Record<string, string> = {
  PENDING: '待处理',
  AUDITING: '审核中',
  APPROVED: '已通过',
  PASSED: '已通过',
  PAID: '已打款',
  REJECTED: '已驳回',
  VIOLATION: '违规',
  INNOCENT: '无违规',
  NORMAL: '正常',
  BANNED: '已封禁',
  CANCELLED: '已注销',
  NONE: '未认证',
}

const key = computed<string>(() => String(props.status ?? '').trim())

const type = computed<TagType>(() => {
  const token = key.value.toUpperCase()
  if (EXACT[token]) {
    return EXACT[token]
  }
  if (key.value.includes('驳回') || key.value.includes('违规') || key.value.includes('封禁') || key.value.includes('失败')) {
    return 'danger'
  }
  if (key.value.includes('待') || key.value.includes('审核中') || key.value.includes('处理中')) {
    return 'warning'
  }
  if (key.value.includes('通过') || key.value.includes('打款') || key.value.includes('成功') || key.value.includes('正常')) {
    return 'success'
  }
  return 'info'
})

const label = computed<string>(() => props.text || TEXT[key.value.toUpperCase()] || key.value)
</script>

<template>
  <el-tag v-if="label" :type="type" disable-transitions size="small">
    {{ label }}
  </el-tag>
</template>
