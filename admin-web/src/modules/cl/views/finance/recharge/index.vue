<script setup lang="tsx">
import type { ClUserRow } from '../../../api/user'
import * as api from '../../../api/finance'
import * as userApi from '../../../api/user'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 后台充值（docs/07 第 3.10 节、docs/06 第 8 节）。
 *
 * 只能给**男用户**充（金币是男的用来付私信/礼物的钱，女号充了没处花）——
 * 所以选人那一步就只在男用户里搜。提交前二次确认，把「充谁、多少币、实收多少」再念一遍。
 */
defineOptions({ name: 'cl:finance:recharge' })

const msg = useMessage()

const form = reactive({
  userId: undefined as number | undefined,
  coins: 100,
  amountYuan: 10,
  payChannel: 'WECHAT',
  remark: '',
})

const CHANNELS = [
  { label: '微信', value: 'WECHAT' },
  { label: '支付宝', value: 'ALIPAY' },
  { label: '其他', value: 'OTHER' },
  { label: '赠送（不收钱）', value: 'GIFT' },
]

/** 选中的用户（选完要显示昵称和当前余额）。 */
const picked = ref<ClUserRow | null>(null)

// 远程搜人：只在男用户里搜，ID / 账号 / 手机号 / 昵称都能搜
const searching = ref(false)
const candidates = ref<ClUserRow[]>([])

async function searchUsers(keyword: string) {
  if (keyword.trim() === '') {
    candidates.value = []
    return
  }
  searching.value = true
  try {
    const res = await userApi.page({ gender: 1, keyword: keyword.trim(), page: 1, page_size: 20 })
    candidates.value = res.code === ResultCode.SUCCESS ? res.data.list : []
  }
  finally {
    searching.value = false
  }
}

function onPick(id: number) {
  picked.value = candidates.value.find(item => item.id === id) ?? null
}

/** 按 `coin.per_yuan` 换算的参考金额 —— 汇率从启动配置读（服务端返回的）。 */
const rate = ref(10)

onMounted(async () => {
  const res = await api.rate()
  if (res.code === ResultCode.SUCCESS) {
    rate.value = res.data.coin_per_yuan
  }
})

const referenceYuan = computed(() => (form.coins / Math.max(1, rate.value)).toFixed(2))

const confirmVisible = ref(false)
const submitting = ref(false)

function askConfirm() {
  if (!picked.value) {
    msg.error('先选一个男用户')
    return
  }
  if (form.coins <= 0) {
    msg.error('充值金币数必须是正整数')
    return
  }
  confirmVisible.value = true
}

async function submit() {
  submitting.value = true
  try {
    const res = await api.recharge({
      user_id: picked.value!.id!,
      coins: form.coins,
      amount_fen: Math.round(form.amountYuan * 100),
      pay_channel: form.payChannel,
      remark: form.remark || undefined,
    })
    if (res.code !== ResultCode.SUCCESS) {
      msg.error(res.message)
      return
    }
    msg.success(`充值成功，${picked.value?.nickname} 当前余额 ${res.data.balance} 金币`)
    confirmVisible.value = false
    // 充完刷新余额，接着充下一个不用重新选人
    picked.value = { ...picked.value!, coin_balance: res.data.balance } as ClUserRow
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div>
    <el-card shadow="never" class="m-3">
      <template #header>
        <div class="text-base font-medium">
          后台充值
        </div>
        <div class="mt-1 text-xs text-gray-500">
          只能给男用户充值。客服确认收到钱之后再录，实收填 0 表示赠送。
        </div>
      </template>

      <el-form label-width="100px" style="max-width: 640px;">
        <el-form-item label="用户">
          <el-select
            v-model="form.userId"
            filterable
            remote
            clearable
            reserve-keyword
            placeholder="输入 ID / 账号 / 手机号 / 昵称搜索"
            :remote-method="searchUsers"
            :loading="searching"
            style="width: 100%;"
            @change="(id: number) => onPick(id)"
          >
            <el-option
              v-for="item in candidates"
              :key="item.id"
              :label="`${item.nickname}（ID ${item.id} · ${item.account}）`"
              :value="item.id!"
            />
          </el-select>
        </el-form-item>

        <el-alert
          v-if="picked"
          type="success"
          :closable="false"
          class="mb-3"
          :title="`已选：${picked.nickname} · 当前余额 ${picked.coin_balance ?? 0} 金币`"
        />

        <el-form-item label="充值金币">
          <el-input-number v-model="form.coins" :min="1" :max="10000000" controls-position="right" />
          <span class="ml-2 text-xs text-gray-400">按汇率约 ¥{{ referenceYuan }}</span>
        </el-form-item>

        <el-form-item label="实收金额(元)">
          <el-input-number v-model="form.amountYuan" :min="0" :precision="2" :step="10" controls-position="right" />
        </el-form-item>

        <el-form-item label="支付方式">
          <el-select v-model="form.payChannel" style="width: 200px;">
            <el-option v-for="item in CHANNELS" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>

        <el-form-item label="备注">
          <el-input v-model="form.remark" maxlength="200" placeholder="例如：微信转账 10 元" />
        </el-form-item>

        <el-form-item>
          <el-button v-auth="['cl:finance:recharge']" type="primary" @click="askConfirm">
            提交充值
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-dialog v-model="confirmVisible" title="确认充值" width="420px">
      <el-descriptions :column="1" border size="small">
        <el-descriptions-item label="用户">
          {{ picked?.nickname }}（ID {{ picked?.id }}）
        </el-descriptions-item>
        <el-descriptions-item label="充值金币">
          {{ form.coins }}
        </el-descriptions-item>
        <el-descriptions-item label="实收金额">
          ¥{{ form.amountYuan.toFixed(2) }}
        </el-descriptions-item>
        <el-descriptions-item label="支付方式">
          {{ CHANNELS.find(c => c.value === form.payChannel)?.label }}
        </el-descriptions-item>
      </el-descriptions>

      <template #footer>
        <el-button @click="confirmVisible = false">
          取消
        </el-button>
        <el-button type="primary" :loading="submitting" @click="submit">
          确认充值
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>
