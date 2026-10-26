<script setup lang="ts">
import { computed, ref } from 'vue'

/**
 * 提现档位编辑器（`withdraw.tiers`）。
 *
 * 数据库里仍然是 JSON 数组字符串（`[50,100,200,500]`），这里只把编辑方式换成可视化增删，
 * `v-model` 的契约不变 —— `ClConfigForm` 保存时照常提交 JSON 字符串。
 *
 * 规则和服务端 `WithdrawService::tiers()` 一致：正整数、去重、升序。
 */
defineOptions({ name: 'ClTierEditor' })

const props = withDefaults(defineProps<{
  modelValue?: string
}>(), {
  modelValue: '[]',
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const newTier = ref<number | undefined>()

/**
 * 解析成整数数组。
 *
 * 兼容历史上被双重编码的数据：数据库里可能存的是 `"[20,100,200,500]"`（一个 JSON 字符串，
 * 内容才是数组），这里多解一层；坏数据返回空数组，由 invalid 分支兜底成文本框。
 */
function parseTiers(raw: string): number[] {
  let parsed: unknown
  try {
    parsed = JSON.parse(raw || '[]')
  }
  catch {
    return []
  }

  if (typeof parsed === 'string') {
    try {
      parsed = JSON.parse(parsed)
    }
    catch {
      return []
    }
  }

  if (!Array.isArray(parsed)) {
    return []
  }

  return parsed
    .map(value => Number(value))
    .filter(value => Number.isInteger(value) && value > 0)
}

const tiers = computed<number[]>(() => parseTiers(props.modelValue))

/** 历史坏数据（不是数组 / 不是 JSON）时回退成文本框，至少还能修回来。 */
const invalid = computed(() => {
  let parsed: unknown
  try {
    parsed = JSON.parse(props.modelValue || '[]')
  }
  catch {
    return true
  }

  if (typeof parsed === 'string') {
    try {
      parsed = JSON.parse(parsed)
    }
    catch {
      return true
    }
  }

  return !Array.isArray(parsed)
})

function commit(next: number[]) {
  const sorted = [...new Set(next.filter(value => Number.isInteger(value) && value > 0))]
    .sort((a, b) => a - b)
  emit('update:modelValue', JSON.stringify(sorted))
}

function add() {
  const value = Number(newTier.value)
  if (!Number.isInteger(value) || value <= 0) {
    return
  }
  if (!tiers.value.includes(value)) {
    commit([...tiers.value, value])
  }
  newTier.value = undefined
}

function remove(value: number) {
  commit(tiers.value.filter(item => item !== value))
}

function onRawInput(value: string) {
  emit('update:modelValue', value)
}
</script>

<template>
  <div class="min-w-0 flex-1">
    <template v-if="!invalid">
      <div v-if="tiers.length === 0" class="text-xs text-gray-400">
        还没有档位，添加一个金额后女号才能申请提现
      </div>
      <div v-else class="flex flex-wrap gap-2">
        <el-tag
          v-for="tier in tiers"
          :key="tier"
          closable
          size="large"
          @close="remove(tier)"
        >
          {{ tier }} 元
        </el-tag>
      </div>

      <div class="mt-2 flex items-center gap-2">
        <el-input-number
          v-model="newTier"
          :min="1"
          :step="10"
          controls-position="right"
          placeholder="金额（元）"
          @keyup.enter="add"
        />
        <el-button type="primary" plain @click="add">
          添加档位
        </el-button>
      </div>

      <div class="mt-1 text-xs text-gray-400">
        保存时自动从小到大排序，重复金额会去掉；App 提现页立刻按新档位显示。
      </div>
    </template>

    <el-input
      v-else
      :model-value="modelValue"
      type="textarea"
      :rows="2"
      @update:model-value="onRawInput"
    />
  </div>
</template>
