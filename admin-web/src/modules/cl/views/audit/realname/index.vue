<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClRealNameRow } from '../../../api/audit'
import * as api from '../../../api/audit'
import ClReasonDialog from '../../../components/ClReasonDialog.vue'
import ClStatusTag from '../../../components/ClStatusTag.vue'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * 实名审核（docs/07 第 3.5 节）。
 *
 * 列表里身份证**永远是打码的**；要看明文得点「查看」，那一下服务端会记一条流水，
 * 而且那个按钮的权限码（`cl:audit:realname-plain`）连审核员都没有，只有超管有。
 */
defineOptions({ name: 'cl:audit:realname' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

const TABS = [
  { label: '审核中', value: 1 },
  { label: '已通过', value: 2 },
  { label: '已驳回', value: 3 },
]
const status = ref(1)

function onTabChange() {
  proTableRef.value?.setRequestParams({ status: status.value }, true)
}

// ---------- 驳回 ----------
const rejectVisible = ref(false)
const rejectTarget = ref<ClRealNameRow | null>(null)
const rejectReason = ref('')
const rejecting = ref(false)
const COMMON_REASONS = ['证件照片模糊', '姓名与证件不一致', '身份证已过期', '信息不完整']

function openReject(row: ClRealNameRow) {
  rejectTarget.value = row
  rejectReason.value = ''
  rejectVisible.value = true
}

async function submitReject(reason: string) {
  rejecting.value = true
  try {
    afterAction(await api.realNameReject(rejectTarget.value!.id, reason), '已驳回')
    rejectVisible.value = false
  }
  finally {
    rejecting.value = false
  }
}

// ---------- 通过 ----------
async function approve(row: ClRealNameRow) {
  afterAction(await api.realNameApprove(row.id), '已通过')
}

// ---------- 看明文 ----------
const plainVisible = ref(false)
const plain = ref<{ real_name: string, id_card: string } | null>(null)

async function viewPlain(row: ClRealNameRow) {
  const res = await api.realNamePlain(row.id)
  if (res.code !== ResultCode.SUCCESS) {
    msg.error(res.message)
    return
  }
  plain.value = res.data
  plainVisible.value = true
}

function afterAction(res: any, successText: string) {
  if (res.code === ResultCode.SUCCESS) {
    msg.success(successText)
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '实名审核',
    subTitle: () => '同一张身份证被多个号使用只提示、不拦截；看明文会记一条操作流水。',
  },
  searchOptions: { show: false },
  requestOptions: {
    api: (params: Record<string, any>) => api.realNameList({ ...params, status: status.value }),
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: [],
  tableColumns: [
    {
      label: '用户',
      prop: 'user_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.user_id, nickname: row.user_nickname, avatar: row.user_avatar, gender: row.user_gender }} clickable={false} />,
    },
    { label: '真实姓名', prop: 'real_name', width: '110px' },
    {
      label: '身份证号',
      prop: 'id_card_mask',
      width: '230px',
      cellRender: ({ row }) => (
        <span class="flex items-center gap-2">
          <span class="tabular-nums">{row.id_card_mask}</span>
          {hasAuth('cl:audit:realname-plain') && (
            <el-link type="primary" onClick={() => viewPlain(row)}>
              查看
            </el-link>
          )}
        </span>
      ),
    },
    {
      label: '同证件其他账号',
      prop: 'duplicate_count',
      width: '140px',
      cellRender: ({ row }) => (
        row.duplicate_count > 0
          ? <ClStatusTag status="PENDING" text={`还有 ${row.duplicate_count} 个号`} />
          : <span class="text-gray-400">—</span>
      ),
    },
    {
      label: '状态',
      prop: 'status',
      width: '100px',
      cellRender: ({ row }) => <ClStatusTag status={statusToken(row.status)} />,
    },
    { label: '提交时间', prop: 'created_at', width: '170px' },
    {
      type: 'operation',
      label: '操作',
      width: '150px',
      operationConfigure: {
        actions: [
          {
            name: 'approve',
            text: '通过',
            icon: 'ri:check-line',
            show: ({ row }) => row.status === 1 && hasAuth('cl:audit:realname'),
            onClick: ({ row }) => approve(row),
          },
          {
            name: 'reject',
            text: '驳回',
            icon: 'ri:close-line',
            show: ({ row }) => row.status === 1 && hasAuth('cl:audit:realname'),
            onClick: ({ row }) => openReject(row),
          },
        ],
      },
    },
  ],
})

function statusToken(value: number): string {
  return value === 2 ? 'PASSED' : value === 3 ? 'REJECTED' : 'AUDITING'
}
</script>

<template>
  <div class="mine-layout">
    <el-tabs v-model="status" class="px-3" @tab-change="onTabChange">
      <el-tab-pane v-for="tab in TABS" :key="tab.value" :label="tab.label" :name="tab.value" />
    </el-tabs>

    <MaProTable ref="proTableRef" :options="options" :schema="schema" />

    <ClReasonDialog
      v-model="rejectVisible"
      title="驳回实名认证"
      :message="`确定要驳回「${rejectTarget?.user_nickname ?? ''}」的实名认证吗？`"
      confirm-text="确认驳回"
      :loading="rejecting"
      :default-reason="rejectReason"
      @confirm="submitReject"
    >
      <el-form label-width="70px" class="mb-2">
        <el-form-item label="常用原因">
          <el-tag
            v-for="reason in COMMON_REASONS"
            :key="reason"
            class="mr-1 cursor-pointer"
            :type="rejectReason === reason ? 'primary' : 'info'"
            @click="rejectReason = reason"
          >
            {{ reason }}
          </el-tag>
        </el-form-item>
      </el-form>
    </ClReasonDialog>

    <el-dialog v-model="plainVisible" title="身份证明文" width="460px">
      <el-alert
        type="warning"
        :closable="false"
        show-icon
        title="这次查看已经记入操作流水（谁、什么时候、看了谁）。"
        class="mb-3"
      />
      <el-descriptions :column="1" border size="small">
        <el-descriptions-item label="真实姓名">
          {{ plain?.real_name }}
        </el-descriptions-item>
        <el-descriptions-item label="身份证号">
          <span class="tabular-nums">{{ plain?.id_card }}</span>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>
