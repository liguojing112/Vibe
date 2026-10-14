<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'ClImagePreview' })

const props = defineProps<{
  /** 一张图或一组图；一组时点开可以左右翻。 */
  src?: string | string[] | null
  /** 缩略图边长（px）。 */
  size?: number
  /** 没有图时显示的占位文字。 */
  emptyText?: string
}>()

const list = computed<string[]>(() => {
  const value = props.src
  if (!value) {
    return []
  }
  return (Array.isArray(value) ? value : [value]).filter((item): item is string => !!item)
})

const box = computed(() => ({ width: `${props.size ?? 64}px`, height: `${props.size ?? 64}px` }))
</script>

<template>
  <div class="cl-image-preview" :style="box">
    <el-image
      v-if="list.length > 0"
      :src="list[0]"
      :preview-src-list="list"
      :initial-index="0"
      fit="cover"
      preview-teleported
      class="cl-image-preview__img"
    >
      <template #error>
        <div class="cl-image-preview__empty">
          加载失败
        </div>
      </template>
    </el-image>
    <div v-else class="cl-image-preview__empty">
      {{ props.emptyText ?? '无图' }}
    </div>
  </div>
</template>

<style scoped lang="scss">
.cl-image-preview {
  @apply overflow-hidden rounded;
  @apply border border-solid border-gray-200;
}

.cl-image-preview__img {
  @apply h-full w-full;
}

.cl-image-preview__empty {
  @apply flex h-full w-full items-center justify-center text-xs text-gray-400;

  background-color: var(--el-fill-color-lighter);
}
</style>
