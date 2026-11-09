<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClBotChatRuleRow } from '../../../api/botChat'
import * as api from '../../../api/botChat'
import ClAmount from '../../../components/ClAmount.vue'
import LogDrawer from './LogDrawer.vue'
import RuleDialog from './RuleDialog.vue'
import TargetDrawer from './TargetDrawer.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'
import { ElMessageBox } from 'element-plus'

/**
 * AI 互聊：机器人男号给指定女号发消息。
 *
 * 规则只配「谁发给谁、什么节奏、上限多少」，真正的发送由服务端每分钟驱动。
 * **男号发消息不扣金币；女号的收益只做统计，不进钱包，也不影响等级和提现。**
 *
 * 右上角的总开关是最后一道闸：关掉后所有规则都不再产生新消息。
 */
defineOptions({ name: 'cl:content:bot' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

const globalEnabled = ref(false)
const globalSaving = ref(false)

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)

const targetVisible = ref(false)
const logVisible = ref(false)
const currentRule = ref<ClBotChatRuleRow | null>(null)

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => 'AI 互聊',
    subTitle: () => '机器人男号按规则给指定女号发消息；女号开了 AI 代聊就双向互聊，没开就只发开场白。',
  },
  searchOptions: { show: true },
  requestOptions: {
    api: async (params: Record<string, any>) => {
      const res = await api.botChatList(params)
      if (res.code === ResultCode.SUCCESS) {
        globalEnabled.value = res.data.global_enabled
      }
      return res
    },
  },
})

function formatWindow(row: ClBotChatRuleRow): string {
  return row.window_start === row.window_end
    ? '全天'
    : `${row.window_start}:00 - ${row.window_end}:00`
}

async function toggleGlobal(next: boolean) {
  globalSaving.value = true
  try {
    const res = await api.botChatGlobalToggle(next ? 1 : 0)
    if (res.code === ResultCode.SUCCESS) {
      globalEnabled.value = next
      msg.success(next ? '已打开总开关' : '已关闭总开关')
    }
    else {
      msg.error(res.message)
    }
  }
  finally {
    globalSaving.value = false
  }
}

function onGlobalChange(value: boolean | string | number) {
  toggleGlobal(Boolean(value))
}

function openCreate() {
  editingId.value = null
  dialogVisible.value = true
}

function openEdit(row: ClBotChatRuleRow) {
  editingId.value = row.id
  dialogVisible.value = true
}

function openTargets(row: ClBotChatRuleRow) {
  currentRule.value = row
  targetVisible.value = true
}

function openLogs(row: ClBotChatRuleRow) {
  currentRule.value = row
  logVisible.value = true
}

async function toggle(row: ClBotChatRuleRow) {
  const next = row.enabled === 1 ? 0 : 1
  const res = await api.botChatToggle(row.id, next)
  if (res.code === ResultCode.SUCCESS) {
    msg.success(next === 1 ? '已启动' : '已暂停')
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

async function remove(row: ClBotChatRuleRow) {
  const ok = await ElMessageBox.confirm(
    `删除规则「${row.name}」？历史记录会保留，但不再产生新消息。`,
    '删除确认',
    { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' },
  ).catch(() => false)
  if (!ok) {
    return
  }

  const res = await api.botChatDelete(row.id)
  if (res.code === ResultCode.SUCCESS) {
    msg.success('已删除')
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

const schema = ref<MaProTableSchema>({
  searchItems: [
    { label: '规则名称', prop: 'keyword', render: 'input', renderProps: { placeholder: '支持模糊搜索' } },
    {
      label: '状态',
      prop: 'enabled',
      render: 'select',
      renderProps: {
        clearable: true,
        options: [
          { label: '已启动', value: 1 },
          { label: '已暂停', value: 0 },
        ],
      },
    },
  ],
  tableColumns: [
    {
      label: '规则名称',
      prop: 'name',
      minWidth: '200px',
      cellRender: ({ row }) => <el-link type="primary" onClick={() => openEdit(row)}>{row.name}</el-link>,
    },
    {
      label: '状态',
      prop: 'enabled',
      width: '100px',
      cellRender: ({ row }) => (
        row.enabled === 1
          ? <el-tag type="success">已启动</el-tag>
          : <el-tag type="info">已暂停</el-tag>
      ),
    },
    { label: '男号数', prop: 'male_count', width: '90px' },
    { label: '目标女号', prop: 'target_count', width: '100px' },
    { label: '每对轮数', prop: 'round_limit_per_pair', width: '100px' },
    {
      label: '发送间隔',
      prop: 'interval_seconds',
      width: '100px',
      cellRender: ({ row }) => `${row.interval_seconds} 秒`,
    },
    {
      label: '时段',
      prop: 'window_start',
      width: '130px',
      cellRender: ({ row }) => formatWindow(row),
    },
    {
      label: '今日虚拟收益',
      prop: 'amount_today_fen',
      width: '130px',
      cellRender: ({ row }) => <ClAmount value={row.amount_today_fen} type="fen" />,
    },
    {
      label: '已停女号',
      prop: 'limit_reached_count',
      width: '100px',
      cellRender: ({ row }) => (row.limit_reached_count > 0
        ? (
            <el-tag type="warning" size="small">
              {row.limit_reached_count}
              {' '}
              个
            </el-tag>
          )
        : <span class="text-gray-400">—</span>),
    },
    {
      type: 'operation',
      label: '操作',
      width: '260px',
      fixed: 'right',
      operationConfigure: {
        actions: [
          {
            name: 'edit',
            text: '编辑',
            icon: 'ri:edit-line',
            show: () => hasAuth('cl:content:bot:edit'),
            onClick: ({ row }) => openEdit(row),
          },
          {
            name: 'toggle',
            text: '启动/暂停',
            icon: 'ri:toggle-line',
            show: () => hasAuth('cl:content:bot:edit'),
            onClick: ({ row }) => toggle(row),
          },
          {
            name: 'targets',
            text: '目标',
            icon: 'ri:user-heart-line',
            show: () => hasAuth('cl:content:bot:list'),
            onClick: ({ row }) => openTargets(row),
          },
          {
            name: 'logs',
            text: '记录',
            icon: 'ri:file-list-line',
            show: () => hasAuth('cl:content:bot:list'),
            onClick: ({ row }) => openLogs(row),
          },
          {
            name: 'delete',
            text: '删除',
            icon: 'ri:delete-bin-line',
            show: () => hasAuth('cl:content:bot:edit'),
            onClick: ({ row }) => remove(row),
          },
        ],
      },
    },
  ],
})
</script>

<template>
  <div class="mine-layout">
    <el-card shadow="never" class="mb-3">
      <div class="flex flex-wrap items-center gap-3">
        <el-alert
          type="info"
          :closable="false"
          show-icon
          class="flex-1"
          title="男号发消息免费；女号的收益只做统计，不进钱包，也不影响等级和提现。"
        />
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-600">总开关</span>
          <el-switch
            :model-value="globalEnabled"
            :loading="globalSaving"
            :disabled="!hasAuth('cl:content:bot:edit')"
            @change="onGlobalChange"
          />
          <el-button v-auth="['cl:content:bot:edit']" type="primary" @click="openCreate">
            新建规则
          </el-button>
        </div>
      </div>
    </el-card>

    <MaProTable ref="proTableRef" :options="options" :schema="schema" />

    <RuleDialog v-model:visible="dialogVisible" :rule-id="editingId" @saved="proTableRef?.refresh()" />

    <TargetDrawer
      v-model:visible="targetVisible"
      :rule-id="currentRule?.id ?? null"
      :rule-name="currentRule?.name ?? ''"
    />

    <LogDrawer
      v-model:visible="logVisible"
      :rule-id="currentRule?.id ?? null"
      :rule-name="currentRule?.name ?? ''"
    />
  </div>
</template>
