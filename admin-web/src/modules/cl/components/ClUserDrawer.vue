<script setup lang="ts">
import type { ClUserDetailVo } from '../api/user'
import { detail } from '../api/user'
import { realNameToken, sourceText } from '../status'
import { computed, ref, watch } from 'vue'
import ClAmount from './ClAmount.vue'
import ClImagePreview from './ClImagePreview.vue'
import ClStatusTag from './ClStatusTag.vue'

defineOptions({ name: 'ClUserDrawer' })

const props = withDefaults(defineProps<{
  modelValue: boolean
  /** 要看的用户 id；为空就不发请求。 */
  userId?: number | null
}>(), {
  userId: null,
})

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const loading = ref(false)
const data = ref<ClUserDetailVo | null>(null)

const visible = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const user = computed(() => data.value?.user)

const genderText = computed(() => (user.value?.gender === 1 ? '男' : user.value?.gender === 2 ? '女' : '—'))

const photos = computed(() => data.value?.photos ?? [])
const logs = computed(() => data.value?.logs ?? [])

watch([() => props.modelValue, () => props.userId], async ([open, id]) => {
  if (!open || !id) {
    return
  }
  loading.value = true
  try {
    const res = await detail(id)
    data.value = res.data
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <el-drawer v-model="visible" title="用户详情" size="620px">
    <div v-loading="loading">
      <template v-if="user">
        <div class="cl-user-drawer__head">
          <el-avatar :size="56" :src="user.avatar ?? undefined">
            {{ user.nickname.slice(0, 1) }}
          </el-avatar>
          <div>
            <div class="cl-user-drawer__name">
              {{ user.nickname }}
              <ClStatusTag :status="user.status === 1 ? 'NORMAL' : user.status === 2 ? 'BANNED' : 'CANCELLED'" />
            </div>
            <div class="cl-user-drawer__sub">
              ID：{{ user.id }} · {{ genderText }} · {{ user.city || '—' }}
            </div>
          </div>
        </div>

        <el-descriptions title="基本资料" :column="2" border size="small">
          <el-descriptions-item label="账号">
            {{ user.account || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="手机号">
            {{ user.phone || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="生日">
            {{ user.birthday || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="身高">
            {{ user.height ?? '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="职业">
            {{ user.job || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="学历">
            {{ user.education || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="注册来源">
            {{ sourceText(user.source) }}
          </el-descriptions-item>
          <el-descriptions-item label="批次号">
            {{ user.batchNo || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="注册时间">
            {{ user.createdAt || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="最后活跃">
            {{ user.lastActiveAt || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="自我介绍" :span="2">
            {{ user.bio || '—' }}
          </el-descriptions-item>
        </el-descriptions>

        <el-descriptions title="钱包" :column="2" border size="small">
          <el-descriptions-item label="金币余额">
            <ClAmount :value="data?.coin.balance ?? 0" type="coin" />
          </el-descriptions-item>
          <el-descriptions-item label="积分余额">
            <ClAmount :value="data?.point.balance ?? 0" type="point" />
          </el-descriptions-item>
          <el-descriptions-item label="冻结积分">
            <ClAmount :value="data?.point.frozen ?? 0" type="point" />
          </el-descriptions-item>
          <el-descriptions-item label="累计收益">
            <ClAmount :value="data?.point.totalEarned ?? 0" type="point" />
          </el-descriptions-item>
        </el-descriptions>

        <el-descriptions title="等级 / 实名 / 会员" :column="2" border size="small">
          <el-descriptions-item label="等级">
            Lv{{ data?.level.level ?? 1 }}
            <span v-if="data?.level.locked" class="cl-user-drawer__lock">（已锁定）</span>
          </el-descriptions-item>
          <el-descriptions-item label="会员到期">
            {{ data?.vip.expireAt || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="实名状态">
            <ClStatusTag :status="realNameToken(data?.realName.status ?? 0)" />
          </el-descriptions-item>
          <el-descriptions-item label="身份证号">
            {{ data?.realName.idCardMask || '—' }}
          </el-descriptions-item>
        </el-descriptions>

        <div class="cl-user-drawer__block">
          <div class="cl-user-drawer__block-title">
            相册（{{ photos.length }}）
          </div>
          <div v-if="photos.length" class="cl-user-drawer__photos">
            <ClImagePreview v-for="(photo, index) in photos" :key="index" :src="photo" :size="80" />
          </div>
          <div v-else class="cl-user-drawer__empty">
            没有相册
          </div>
        </div>

        <div class="cl-user-drawer__block">
          <div class="cl-user-drawer__block-title">
            封禁记录
          </div>
          <el-table v-if="data?.bans?.length" :data="data.bans" size="small" border>
            <el-table-column prop="reason" label="原因" show-overflow-tooltip />
            <el-table-column prop="bannedAt" label="封禁时间" width="170" />
            <el-table-column prop="bannedUntil" label="解封时间" width="170" />
          </el-table>
          <div v-else class="cl-user-drawer__empty">
            没有被封禁过
          </div>
        </div>

        <div class="cl-user-drawer__block">
          <div class="cl-user-drawer__block-title">
            最近 20 条流水
          </div>
          <el-table v-if="logs.length" :data="logs" size="small" border>
            <el-table-column prop="created_at" label="时间" width="170" />
            <el-table-column prop="title" label="说明" show-overflow-tooltip />
            <el-table-column label="变动" width="120" align="right">
              <template #default="{ row }">
                <ClAmount :value="row.change" :type="row.type === 'coin' ? 'coin' : 'point'" />
              </template>
            </el-table-column>
          </el-table>
          <div v-else class="cl-user-drawer__empty">
            没有流水
          </div>
        </div>
      </template>
    </div>
  </el-drawer>
</template>

<style scoped lang="scss">
.cl-user-drawer__head {
  @apply mb-4 flex items-center gap-3;
}

.cl-user-drawer__name {
  @apply flex items-center gap-2 text-base font-semibold;
}

.cl-user-drawer__sub {
  @apply text-xs text-gray-400;
}

.cl-user-drawer__lock {
  @apply text-xs text-gray-400;
}

.cl-user-drawer__block {
  @apply mt-5;
}

.cl-user-drawer__block-title {
  @apply mb-2 text-sm font-medium text-gray-700;
}

.cl-user-drawer__photos {
  @apply flex flex-wrap gap-2;
}

.cl-user-drawer__empty {
  @apply text-xs text-gray-400;
}
</style>
