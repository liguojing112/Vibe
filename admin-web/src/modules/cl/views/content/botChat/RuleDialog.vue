<script setup lang="ts">
import type {
  ClBotChatPersona,
  ClBotChatRuleDetail,
  ClBotChatSavePayload,
  ClBotChatUserOption,
} from '../../../api/botChat'
import * as api from '../../../api/botChat'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 新建 / 编辑互聊规则（四步）。
 *
 * 所有节奏参数都在这里配，代码里不写死：轮数、间隔、时段、每日收益上限、上下文条数。
 * 男号人设写进 `cl_ai_persona`（这张表男女通用），不选中的男号不会被动到。
 */
const props = defineProps<{
  visible: boolean
  ruleId: number | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'saved': []
}>()

const dialogVisible = computed({
  get: () => props.visible,
  set: (value: boolean) => emit('update:visible', value),
})

const msg = useMessage()

const step = ref(0)
const loading = ref(false)
const saving = ref(false)

const emptyPersona = (): ClBotChatPersona => ({ persona: '', tone: '', taboos: '', examples: '' })

const form = reactive<ClBotChatSavePayload>({
  name: '',
  enabled: 1,
  male_user_ids: [],
  pool_male_count: 0,
  female_user_ids: [],
  window_start: 0,
  window_end: 0,
  round_limit_per_pair: 20,
  interval_seconds: 30,
  daily_amount_limit_fen: 1500,
  context_messages: 10,
  male_personas: {},
})

/** 每日上限按「元」编辑，提交时换回分。 */
const limitYuan = computed<number>({
  get: () => Number((form.daily_amount_limit_fen / 100).toFixed(2)),
  set: (value) => {
    form.daily_amount_limit_fen = Math.max(1, Math.round((Number(value) || 0) * 100))
  },
})

const maleOptions = ref<ClBotChatUserOption[]>([])
const femaleOptions = ref<ClBotChatUserOption[]>([])
const maleSearching = ref(false)
const femaleSearching = ref(false)
const openedPanels = ref<number[]>([])

const isEdit = computed(() => props.ruleId !== null)
const title = computed(() => (isEdit.value ? '编辑互聊规则' : '新建互聊规则'))

/** 搜索结果是增量的，已选中的用户不能被后一次搜索冲掉（否则标签会变成一串 id）。 */
function mergeOptions(current: ClBotChatUserOption[], incoming: ClBotChatUserOption[]): ClBotChatUserOption[] {
  const map = new Map(current.map(item => [item.user_id, item]))
  for (const item of incoming) {
    map.set(item.user_id, item)
  }
  return [...map.values()]
}

async function searchMales(keyword: string) {
  maleSearching.value = true
  try {
    const res = await api.botChatSearchMale(keyword)
    if (res.code === ResultCode.SUCCESS) {
      maleOptions.value = mergeOptions(maleOptions.value, res.data)
    }
  }
  finally {
    maleSearching.value = false
  }
}

async function searchFemales(keyword: string) {
  femaleSearching.value = true
  try {
    const res = await api.botChatSearchFemale(keyword)
    if (res.code === ResultCode.SUCCESS) {
      femaleOptions.value = mergeOptions(femaleOptions.value, res.data)
    }
  }
  finally {
    femaleSearching.value = false
  }
}

/** 选中男号后按需补一份空人设；取消选中的直接丢掉，避免保存一堆孤儿配置。 */
function syncPersonas() {
  const next: Record<number, ClBotChatPersona> = {}
  for (const id of form.male_user_ids) {
    next[id] = form.male_personas[id] ?? emptyPersona()
  }
  form.male_personas = next
}

function reset() {
  step.value = 0
  openedPanels.value = []
  Object.assign(form, {
    name: '',
    enabled: 1,
    male_user_ids: [],
    pool_male_count: 0,
    female_user_ids: [],
    window_start: 0,
    window_end: 0,
    round_limit_per_pair: 20,
    interval_seconds: 30,
    daily_amount_limit_fen: 1500,
    context_messages: 10,
    male_personas: {},
  })
  maleOptions.value = []
  femaleOptions.value = []
}

async function load(id: number) {
  loading.value = true
  try {
    const res = await api.botChatDetail(id)
    if (res.code !== ResultCode.SUCCESS) {
      msg.error(res.message)
      return
    }
    const detail: ClBotChatRuleDetail = res.data
    maleOptions.value = detail.males.map(item => ({
      user_id: item.user_id,
      nickname: item.nickname,
      account: item.account,
      avatar: item.avatar,
    }))
    femaleOptions.value = detail.females.map(item => ({
      user_id: item.user_id,
      nickname: item.nickname,
      account: item.account,
      avatar: item.avatar,
    }))
    form.name = detail.name
    form.enabled = detail.enabled
    form.male_user_ids = [...detail.male_user_ids]
    form.pool_male_count = detail.pool_male_count
    form.female_user_ids = detail.females.map(item => item.user_id)
    form.window_start = detail.window_start
    form.window_end = detail.window_end
    form.round_limit_per_pair = detail.round_limit_per_pair
    form.interval_seconds = detail.interval_seconds
    form.daily_amount_limit_fen = detail.daily_amount_limit_fen
    form.context_messages = detail.context_messages
    const personas: Record<number, ClBotChatPersona> = {}
    for (const male of detail.males) {
      personas[male.user_id] = {
        persona: male.persona,
        tone: male.tone,
        taboos: male.taboos,
        examples: male.examples,
      }
    }
    form.male_personas = personas
  }
  finally {
    loading.value = false
  }
}

watch(
  () => [props.visible, props.ruleId] as const,
  async ([visible, id]) => {
    if (!visible) {
      return
    }
    reset()
    if (id !== null) {
      await load(id)
    }
  },
  { immediate: true },
)

watch(() => form.male_user_ids, syncPersonas, { deep: true })

function nextStep() {
  if (step.value === 0 && form.name.trim() === '') {
    msg.error('请填写规则名称')
    return
  }
  if (step.value === 1 && form.male_user_ids.length === 0 && form.pool_male_count <= 0) {
    msg.error('至少指定一个男号，或设置从号池补足数量')
    return
  }
  if (step.value === 2 && form.female_user_ids.length === 0) {
    msg.error('至少指定一个目标女号')
    return
  }
  step.value = Math.min(3, step.value + 1)
}

async function submit() {
  if (form.name.trim() === '') {
    msg.error('请填写规则名称')
    step.value = 0
    return
  }
  if (form.male_user_ids.length === 0 && form.pool_male_count <= 0) {
    msg.error('至少指定一个男号，或设置从号池补足数量')
    step.value = 1
    return
  }
  if (form.female_user_ids.length === 0) {
    msg.error('至少指定一个目标女号')
    step.value = 2
    return
  }

  saving.value = true
  try {
    const res = await api.botChatSave({ ...form, id: props.ruleId ?? undefined })
    if (res.code === ResultCode.SUCCESS) {
      msg.success('已保存')
      emit('saved')
      emit('update:visible', false)
    }
    else {
      msg.error(res.message)
    }
  }
  finally {
    saving.value = false
  }
}

function userLabel(option?: ClBotChatUserOption): string {
  if (!option) {
    return '—'
  }
  return `${option.nickname}（${option.account}）`
}

function maleLabel(id: number): string {
  return userLabel(maleOptions.value.find(item => item.user_id === id))
}

const HOURS = Array.from({ length: 24 }, (_, hour) => hour)
</script>

<template>
  <el-dialog
    v-model="dialogVisible"
    :title="title"
    width="880px"
    top="6vh"
    :close-on-click-modal="false"
    destroy-on-close
  >
    <div v-loading="loading">
      <el-steps :active="step" finish-status="success" simple class="mb-4">
        <el-step title="基本信息" />
        <el-step title="男号与人设" />
        <el-step title="目标女号" />
        <el-step title="节奏与限制" />
      </el-steps>

      <!-- ① 基本信息 -->
      <el-form v-if="step === 0" label-width="110px" label-position="right">
        <el-form-item label="规则名称" required>
          <el-input v-model="form.name" maxlength="64" show-word-limit placeholder="例如：把 A 组女号的消息页填满" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" />
          <span class="ml-2 text-xs text-gray-400">还要打开右上角的总开关，任务才会真的开始发</span>
        </el-form-item>
      </el-form>

      <!-- ② 男号与人设 -->
      <el-form v-else-if="step === 1" label-width="110px" label-position="right">
        <el-form-item label="指定男号">
          <el-select
            v-model="form.male_user_ids"
            multiple
            filterable
            remote
            reserve-keyword
            :remote-method="searchMales"
            :loading="maleSearching"
            placeholder="输入昵称 / 账号 / ID 搜索"
            style="width: 100%;"
          >
            <el-option
              v-for="item in maleOptions"
              :key="item.user_id"
              :label="userLabel(item)"
              :value="item.user_id"
            />
          </el-select>
          <div class="mt-1 text-xs text-gray-400">
            已选 {{ form.male_user_ids.length }} 个。只有状态正常的男号能选进来。
          </div>
        </el-form-item>

        <el-form-item label="号池补足">
          <el-input-number v-model="form.pool_male_count" :min="0" :max="500" controls-position="right" />
          <span class="ml-2 text-xs text-gray-400">
            再从机器人号池（批量开号生成的男号）随机补这么多个，0 = 不补
          </span>
        </el-form-item>

        <el-form-item label="男号人设">
          <div v-if="form.male_user_ids.length === 0" class="text-sm text-gray-400">
            先选男号，选完就能在这里逐个配人设。
          </div>
          <el-collapse v-else v-model="openedPanels" class="w-full">
            <el-collapse-item
              v-for="id in form.male_user_ids"
              :key="id"
              :name="id"
              :title="maleLabel(id)"
            >
              <el-form label-width="90px" label-position="left">
                <el-form-item label="人设">
                  <el-input
                    v-model="form.male_personas[id].persona"
                    type="textarea"
                    :rows="3"
                    maxlength="1000"
                    show-word-limit
                    placeholder="性格、生活状态、职业背景。写得越具体越像真人"
                  />
                </el-form-item>
                <el-form-item label="说话风格">
                  <el-input
                    v-model="form.male_personas[id].tone"
                    type="textarea"
                    :rows="2"
                    maxlength="300"
                    show-word-limit
                    placeholder="例如：句子短，偶尔带点语气词"
                  />
                </el-form-item>
                <el-form-item label="不聊的话题">
                  <el-input
                    v-model="form.male_personas[id].taboos"
                    maxlength="300"
                    placeholder="被提到时轻轻带过，例如：政治、借钱"
                  />
                </el-form-item>
                <el-form-item label="对话示例">
                  <el-input
                    v-model="form.male_personas[id].examples"
                    type="textarea"
                    :rows="5"
                    maxlength="2000"
                    show-word-limit
                    placeholder="我：在上班呢 好困&#10;她：这么惨&#10;---&#10;我：周末准备干嘛"
                  />
                  <div class="mt-1 text-xs text-gray-400">
                    写 3~5 组对话，用 <code>---</code> 分隔。模型会照着这里的语气和长度说话。
                  </div>
                </el-form-item>
              </el-form>
            </el-collapse-item>
          </el-collapse>
        </el-form-item>
      </el-form>

      <!-- ③ 目标女号 -->
      <el-form v-else-if="step === 2" label-width="110px" label-position="right">
        <el-form-item label="目标女号">
          <el-select
            v-model="form.female_user_ids"
            multiple
            filterable
            remote
            reserve-keyword
            :remote-method="searchFemales"
            :loading="femaleSearching"
            placeholder="输入昵称 / 账号 / ID 搜索"
            style="width: 100%;"
          >
            <el-option
              v-for="item in femaleOptions"
              :key="item.user_id"
              :label="userLabel(item)"
              :value="item.user_id"
            />
          </el-select>
          <div class="mt-1 text-xs text-gray-400">
            已选 {{ form.female_user_ids.length }} 个。这里随时可以加人、减人，保存后下一轮生效。
          </div>
        </el-form-item>

        <el-form-item label="已选女号">
          <div class="flex flex-wrap gap-2">
            <el-tag
              v-for="id in form.female_user_ids"
              :key="id"
              closable
              @close="form.female_user_ids = form.female_user_ids.filter(item => item !== id)"
            >
              {{ userLabel(femaleOptions.find(item => item.user_id === id)) }}
            </el-tag>
            <span v-if="form.female_user_ids.length === 0" class="text-sm text-gray-400">还没有选女号</span>
          </div>
        </el-form-item>
      </el-form>

      <!-- ④ 节奏与限制 -->
      <el-form v-else label-width="140px" label-position="right">
        <el-form-item label="每对每天轮数">
          <el-input-number v-model="form.round_limit_per_pair" :min="1" :max="2000" controls-position="right" />
          <span class="ml-2 text-xs text-gray-400">同一个男号对同一个女号，一天最多聊几个来回</span>
        </el-form-item>

        <el-form-item label="发送间隔">
          <el-input-number v-model="form.interval_seconds" :min="0" :max="86400" controls-position="right" />
          <span class="ml-2 text-xs text-gray-400">秒。同一对之间至少隔这么久</span>
        </el-form-item>

        <el-form-item label="每日时段">
          <el-select v-model="form.window_start" style="width: 100px;">
            <el-option v-for="hour in HOURS" :key="hour" :label="`${hour}:00`" :value="hour" />
          </el-select>
          <span class="mx-2">到</span>
          <el-select v-model="form.window_end" style="width: 100px;">
            <el-option v-for="hour in HOURS" :key="hour" :label="`${hour}:00`" :value="hour" />
          </el-select>
          <span class="ml-2 text-xs text-gray-400">两个选一样表示全天</span>
        </el-form-item>

        <el-form-item label="每号每日上限">
          <el-input-number v-model="limitYuan" :min="0.01" :max="10000" :precision="2" :step="1" controls-position="right" />
          <span class="ml-2 text-xs text-gray-400">
            元。女号当天累计到这个数就停，第二天自动重置
          </span>
        </el-form-item>

        <el-form-item label="AI 上下文条数">
          <el-input-number v-model="form.context_messages" :min="2" :max="50" controls-position="right" />
          <span class="ml-2 text-xs text-gray-400">生成接话时带最近多少条历史</span>
        </el-form-item>

        <el-alert
          type="info"
          :closable="false"
          show-icon
          title="男号发消息不扣金币；女号的收益只做统计，不进钱包，也不影响等级和提现。"
        />
      </el-form>
    </div>

    <template #footer>
      <el-button :disabled="step === 0" @click="step -= 1">
        上一步
      </el-button>
      <el-button v-if="step < 3" type="primary" @click="nextStep">
        下一步
      </el-button>
      <el-button v-else v-auth="['cl:content:bot:edit']" type="primary" :loading="saving" @click="submit">
        保存
      </el-button>
    </template>
  </el-dialog>
</template>
