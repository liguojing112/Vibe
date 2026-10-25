<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClAiDetail, ClAiRow } from '../../../api/ai'
import * as api from '../../../api/ai'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * AI 代聊（只对女用户开放）。
 *
 * 男用户给这个女号发消息后，服务端按人设调用模型，延迟几秒以她的身份回过去。
 * 关掉开关就恢复成完全由真人（运营）接管。
 */
defineOptions({ name: 'cl:content:ai' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

const PROACTIVE_LABEL: Record<number, string> = {
  0: '不主动',
  1: '低',
  2: '中',
  3: '高',
}

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => 'AI 代聊',
    subTitle: () => '男用户发来消息后，服务端按人设自动回复；也能定时主动搭话。只对女用户开放。',
  },
  searchOptions: { show: true },
  requestOptions: {
    api: (params: Record<string, any>) => api.aiList(params),
  },
})

// ---------- 编辑 ----------
const dialogVisible = ref(false)
const saving = ref(false)
const current = ref<ClAiDetail | null>(null)

async function openEdit(row: ClAiRow) {
  const res = await api.aiDetail(row.user_id)
  if (res.code !== ResultCode.SUCCESS) {
    msg.error(res.message)
    return
  }
  current.value = res.data
  dialogVisible.value = true
}

async function submit() {
  if (!current.value) {
    return
  }
  saving.value = true
  try {
    const res = await api.aiSave(current.value.user_id, current.value)
    if (res.code === ResultCode.SUCCESS) {
      msg.success('已保存')
      dialogVisible.value = false
      proTableRef.value?.refresh()
    }
    else {
      msg.error(res.message)
    }
  }
  finally {
    saving.value = false
  }
}

// ---------- 开关 ----------
async function toggle(row: ClAiRow) {
  const next = row.enabled === 1 ? 0 : 1
  const res = await api.aiToggle(row.user_id, next)
  if (res.code === ResultCode.SUCCESS) {
    msg.success(next === 1 ? '已开启自动回复' : '已关闭自动回复')
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

const schema = ref<MaProTableSchema>({
  searchItems: [
    { label: '昵称/账号', prop: 'keyword', render: 'input', renderProps: { placeholder: '支持昵称、账号、ID' } },
    {
      label: 'AI 状态',
      prop: 'enabled',
      render: 'select',
      renderProps: {
        clearable: true,
        options: [
          { label: '已开启', value: 1 },
          { label: '已关闭', value: 0 },
        ],
      },
    },
  ],
  tableColumns: [
    {
      label: '女用户',
      prop: 'nickname',
      width: '190px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.user_id, nickname: row.nickname, avatar: row.avatar, gender: 2 }} clickable={false} />,
    },
    { label: '城市', prop: 'city', width: '90px' },
    {
      label: 'AI 状态',
      prop: 'enabled',
      width: '110px',
      cellRender: ({ row }) => (
        row.enabled === 1
          ? <el-tag type="success">已开启</el-tag>
          : <el-tag type="info">{row.configured ? '已关闭' : '未配置'}</el-tag>
      ),
    },
    {
      label: '主动程度',
      prop: 'proactive',
      width: '90px',
      cellRender: ({ row }) => PROACTIVE_LABEL[row.proactive] ?? '-',
    },
    {
      label: '活跃时段',
      prop: 'active_start',
      width: '110px',
      cellRender: ({ row }) => `${row.active_start}:00 - ${row.active_end}:00`,
    },
    {
      label: '今日回复',
      prop: 'replied_today',
      width: '100px',
      cellRender: ({ row }) => `${row.replied_today} 条`,
    },
    {
      label: '累计 token',
      prop: 'total_tokens',
      width: '110px',
      cellRender: ({ row }) => (row.total_tokens > 0 ? row.total_tokens.toLocaleString() : '-'),
    },
    {
      type: 'operation',
      label: '操作',
      width: '160px',
      operationConfigure: {
        actions: [
          {
            name: 'edit',
            text: '配置',
            icon: 'ri:settings-3-line',
            show: () => hasAuth('cl:content:ai:edit'),
            onClick: ({ row }) => openEdit(row),
          },
          {
            name: 'toggle',
            text: '开关',
            icon: 'ri:toggle-line',
            show: () => hasAuth('cl:content:ai:edit'),
            onClick: ({ row }) => toggle(row),
          },
        ],
      },
    },
  ],
})
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema" />

    <el-dialog v-model="dialogVisible" :title="`AI 代聊 · ${current?.nickname ?? ''}`" width="640px">
      <el-form v-if="current" label-width="110px" label-position="left">
        <el-form-item label="自动回复">
          <el-switch v-model="current.enabled" :active-value="1" :inactive-value="0" />
          <span class="ml-2 text-xs text-gray-400">关闭后对方发消息不会自动回</span>
        </el-form-item>

        <el-form-item label="人设">
          <el-input v-model="current.persona" type="textarea" :rows="3" maxlength="1000" show-word-limit placeholder="性格、生活状态、职业背景等，写得越具体越像真人" />
        </el-form-item>

        <el-form-item label="说话风格">
          <el-input v-model="current.tone" type="textarea" :rows="2" maxlength="300" show-word-limit placeholder="例如：像发微信一样，短句，偶尔带点语气词" />
        </el-form-item>

        <el-form-item label="不聊的话题">
          <el-input v-model="current.taboos" placeholder="被提到时轻轻带过，例如：政治、催婚" />
        </el-form-item>

        <el-form-item label="对话示例">
          <el-input
            v-model="current.examples"
            type="textarea"
            :rows="7"
            maxlength="2000"
            show-word-limit
            placeholder="对方：在干嘛&#10;你：刚下班 累死了&#10;---&#10;对方：吃饭没&#10;你：还没 不想动"
          />
          <div class="text-xs text-gray-400 mt-1">
            写 3~5 组真实对话，用 <code>---</code> 分隔。模型会照着这里的语气、长度和标点回复 —— 比在「说话风格」里写抽象描述管用得多。
          </div>
        </el-form-item>

        <el-form-item label="主动程度">
          <el-radio-group v-model="current.proactive">
            <el-radio :value="0">
              不主动
            </el-radio>
            <el-radio :value="1">
              低
            </el-radio>
            <el-radio :value="2">
              中
            </el-radio>
            <el-radio :value="3">
              高
            </el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="活跃时段">
          <el-select v-model="current.active_start" style="width: 100px">
            <el-option v-for="h in 24" :key="h" :label="`${h - 1}:00`" :value="h - 1" />
          </el-select>
          <span class="mx-2">到</span>
          <el-select v-model="current.active_end" style="width: 100px">
            <el-option v-for="h in 24" :key="h" :label="`${h - 1}:00`" :value="h - 1" />
          </el-select>
          <span class="ml-2 text-xs text-gray-400">相同表示全天</span>
        </el-form-item>

        <el-form-item label="今日回复">
          <span class="text-sm">{{ current.replied_today }} 条</span>
          <span class="ml-2 text-xs text-gray-400">不限条数，只在活跃时段内自动回</span>
        </el-form-item>

        <el-form-item label="累计消耗">
          <span class="text-sm">输入 {{ current.prompt_tokens.toLocaleString() }} / 输出 {{ current.completion_tokens.toLocaleString() }} tokens</span>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">
          取消
        </el-button>
        <el-button type="primary" :loading="saving" @click="submit">
          保存
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>
