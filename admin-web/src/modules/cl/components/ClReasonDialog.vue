<script setup lang="ts">
import { computed, ref, watch } from 'vue'

defineOptions({ name: 'ClReasonDialog' })

const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  /** 副标题，说清这次操作会发生什么。 */
  message?: string
  confirmText?: string
  placeholder?: string
  /** 提交中：按钮转圈并挡住重复点击。 */
  loading?: boolean
}>(), {
  message: '',
  confirmText: '确定',
  placeholder: '请填写原因（会记入操作日志）',
  loading: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'confirm': [reason: string]
}>()

const reason = ref('')
const touched = ref(false)

const visible = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

// 危险操作二次确认，所以每次打开都是干净的：不带上一次填的原因
watch(() => props.modelValue, (open) => {
  if (open) {
    reason.value = ''
    touched.value = false
  }
})

const error = computed(() => (touched.value && reason.value.trim() === '' ? '请填写原因' : ''))

function submit() {
  touched.value = true
  if (reason.value.trim() === '') {
    return
  }
  emit('confirm', reason.value.trim())
}
</script>

<template>
  <el-dialog v-model="visible" :title="props.title" width="480px" :close-on-click-modal="false">
    <p v-if="props.message" class="cl-reason-dialog__message">
      {{ props.message }}
    </p>
    <el-input
      v-model="reason"
      type="textarea"
      :rows="3"
      maxlength="200"
      show-word-limit
      :placeholder="props.placeholder"
      @blur="touched = true"
    />
    <p v-if="error" class="cl-reason-dialog__error">
      {{ error }}
    </p>

    <template #footer>
      <el-button @click="visible = false">
        取消
      </el-button>
      <el-button type="danger" :loading="props.loading" @click="submit">
        {{ props.confirmText }}
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped lang="scss">
.cl-reason-dialog__message {
  @apply mb-3 text-sm text-gray-600;
}

.cl-reason-dialog__error {
  @apply m-0 mt-1 text-xs;

  color: var(--el-color-danger);
}
</style>
