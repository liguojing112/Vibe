<script setup lang="ts">
import type { ClUserLike } from '../types'
import { computed } from 'vue'

defineOptions({ name: 'ClUserCell' })

const props = defineProps<{
  user?: ClUserLike | null
  /** 点一下打开用户详情抽屉（docs/07 第 2 节）。不需要就传 false。 */
  clickable?: boolean
}>()

const emit = defineEmits<{ click: [user: ClUserLike] }>()

const male = computed(() => props.user?.gender === 1)
const female = computed(() => props.user?.gender === 2)

function onClick() {
  if (props.clickable !== false && props.user) {
    emit('click', props.user)
  }
}
</script>

<template>
  <div
    v-if="props.user"
    class="cl-user-cell"
    :class="{ 'cl-user-cell--clickable': props.clickable !== false }"
    @click="onClick"
  >
    <el-avatar :size="32" :src="props.user.avatar ?? undefined">
      {{ (props.user.nickname ?? '?').slice(0, 1) }}
    </el-avatar>
    <div class="cl-user-cell__main">
      <div class="cl-user-cell__name">
        <span class="truncate">{{ props.user.nickname ?? '—' }}</span>
        <ma-svg-icon
          v-if="male || female"
          :name="male ? 'ri:men-line' : 'ri:women-line'"
          :color="male ? '#409eff' : '#f472b6'"
          :size="14"
        />
      </div>
      <div class="cl-user-cell__id">
        ID：{{ props.user.id ?? '—' }}
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.cl-user-cell {
  @apply flex items-center gap-2;
}

.cl-user-cell--clickable {
  @apply cursor-pointer;

  &:hover .cl-user-cell__name {
    color: var(--el-color-primary);
  }
}

.cl-user-cell__main {
  @apply min-w-0 leading-tight;
}

.cl-user-cell__name {
  @apply flex items-center gap-1 text-sm text-gray-700;
}

.cl-user-cell__id {
  @apply text-xs text-gray-400;
}
</style>
