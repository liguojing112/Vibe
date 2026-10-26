<script setup lang="ts">
import type { ClConfigItem } from '../types'
import { computed, ref } from 'vue'
import ClTierEditor from './ClTierEditor.vue'

defineOptions({ name: 'ClConfigForm' })

const props = withDefaults(defineProps<{
  /** 配置项（对应 `cl_config` 的行）。 */
  items?: ClConfigItem[]
  /** key → 原始值（`cl_config.value` 都是字符串）。 */
  modelValue?: Record<string, string>
  /** 只显示这个分组（不传就全显示）。 */
  group?: string
}>(), {
  items: () => [],
  modelValue: () => ({}),
  group: '',
})

const emit = defineEmits<{ 'update:modelValue': [value: Record<string, string>] }>()

/** JSON 项的报错（key → 提示），提交前要挡住。 */
const jsonErrors = ref<Record<string, string>>({})

const visible = computed(() => props.group
  ? props.items.filter(item => item.group === props.group)
  : props.items)

function valueOf(item: ClConfigItem): string {
  return props.modelValue[item.key] ?? item.value ?? ''
}

function setValue(key: string, value: string) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function onJsonInput(item: ClConfigItem, value: string) {
  setValue(item.key, value)
  try {
    JSON.parse(value)
    delete jsonErrors.value[item.key]
  }
  catch {
    jsonErrors.value[item.key] = '不是合法的 JSON'
  }
}

/** 提交前调一次；JSON 写得不对就别让提交。 */
function validate(): boolean {
  return Object.keys(jsonErrors.value).length === 0
}

defineExpose({ validate })
</script>

<template>
  <el-form label-width="160px" label-position="right">
    <el-form-item v-for="item in visible" :key="item.key" :label="item.label || item.key">
      <div class="cl-config-form__field">
        <el-switch
          v-if="item.value_type === 'bool'"
          :model-value="valueOf(item) === '1'"
          @update:model-value="(v: string | number | boolean) => setValue(item.key, v ? '1' : '0')"
        />

        <el-input-number
          v-else-if="item.value_type === 'int'"
          :model-value="Number(valueOf(item) || 0)"
          :min="0"
          controls-position="right"
          @update:model-value="(v: number | undefined) => setValue(item.key, String(v ?? 0))"
        />

        <ClTierEditor
          v-else-if="item.key === 'withdraw.tiers'"
          :model-value="valueOf(item)"
          @update:model-value="(v: string) => setValue(item.key, v)"
        />

        <el-input
          v-else-if="item.value_type === 'json'"
          :model-value="valueOf(item)"
          type="textarea"
          :rows="3"
          @update:model-value="(v: string) => onJsonInput(item, v)"
        />

        <el-input
          v-else
          :model-value="valueOf(item)"
          @update:model-value="(v: string) => setValue(item.key, v)"
        />

        <div v-if="jsonErrors[item.key]" class="cl-config-form__error">
          {{ jsonErrors[item.key] }}
        </div>
        <div v-else-if="item.remark" class="cl-config-form__remark">
          {{ item.remark }}
        </div>
        <div class="cl-config-form__key">
          {{ item.key }}
        </div>
      </div>
    </el-form-item>
  </el-form>
</template>

<style scoped lang="scss">
.cl-config-form__field {
  @apply min-w-0 flex-1;
}

.cl-config-form__remark {
  @apply mt-1 text-xs text-gray-400;
}

.cl-config-form__error {
  @apply mt-1 text-xs;

  color: var(--el-color-danger);
}

.cl-config-form__key {
  @apply w-full text-xs text-gray-300;
}
</style>
