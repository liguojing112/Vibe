<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClProfileAuditRow } from '../../../api/audit'
import * as api from '../../../api/audit'
import ClImagePreview from '../../../components/ClImagePreview.vue'
import ClReasonDialog from '../../../components/ClReasonDialog.vue'
import ClStatusTag from '../../../components/ClStatusTag.vue'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * 资料审核（docs/07 第 3.4 节）。
 *
 * 三个 Tab 对应 `cl_profile_audit.status`；**通过才动 `cl_user`**，
 * 审核期间用户看到的还是旧资料。相册那一条是「换掉整批照片」而不是改字段。
 */
defineOptions({ name: 'cl:audit:profile' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<ClProfileAuditRow[]>([])

const TABS = [
  { label: '待审核', value: 0 },
  { label: '已通过', value: 1 },
  { label: '已驳回', value: 2 },
]
const status = ref(0)

function onTabChange() {
  proTableRef.value?.setRequestParams({ status: status.value }, true)
}

// ---------- 驳回（单条 / 批量）----------
const rejectVisible = ref(false)
const rejectReason = ref('')
const rejecting = ref(false)
const rejectTarget = ref<ClProfileAuditRow | null>(null)
const rejectBatch = ref(false)

const COMMON_REASONS = ['图片不清晰', '资料不真实', '含违规内容', '涉及广告或引流']

function openReject(row: ClProfileAuditRow) {
  rejectBatch.value = false
  rejectTarget.value = row
  rejectReason.value = ''
  rejectVisible.value = true
}

function openBatchReject() {
  rejectBatch.value = true
  rejectTarget.value = null
  rejectReason.value = ''
  rejectVisible.value = true
}

async function submitReject(reason: string) {
  rejecting.value = true
  try {
    const res = rejectBatch.value
      ? await api.profileBatch({ ids: selections.value.map(row => row.id), action: 'reject', reason })
      : await api.profileReject(rejectTarget.value!.id, reason)
    afterAction(res, '已驳回')
    rejectVisible.value = false
  }
  finally {
    rejecting.value = false
  }
}

// ---------- 通过 ----------
async function approve(row: ClProfileAuditRow) {
  afterAction(await api.profileApprove(row.id), '已通过')
}

async function batchApprove() {
  if (selections.value.length === 0) {
    return
  }
  afterAction(
    await api.profileBatch({ ids: selections.value.map(row => row.id), action: 'approve' }),
    '批量通过完成',
  )
}

function afterAction(res: any, successText: string) {
  if (res.code !== ResultCode.SUCCESS) {
    msg.error(res.message)
    return
  }
  // 批量接口会回「哪几条没成」（可能被别人先审了），说一声而不是假装全成功
  const failed: number[] = res.data?.failed ?? []
  if (failed.length > 0) {
    msg.warning(`${successText}，其中 ${failed.length} 条没处理成功（可能已被审过）`)
  }
  else {
    msg.success(successText)
  }
  proTableRef.value?.refresh()
}

// ---------- 表格 ----------
const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '资料审核',
    subTitle: () => '通过后才更新用户资料；驳回只通知，不动的还是原来那份。',
  },
  searchOptions: { show: false },
  requestOptions: {
    api: (params: Record<string, any>) => api.profileList({ ...params, status: status.value }),
  },
  tableOptions: {
    on: {
      onSelectionChange: (rows: ClProfileAuditRow[]) => selections.value = rows,
    },
  },
})

/** 值那一列：图片字段显示缩略图，文字字段直接显示。 */
function valueCell(row: ClProfileAuditRow, side: 'old' | 'new') {
  const isPhoto = row.field === 'photo'
  const isImage = isPhoto || row.field === 'avatar'

  if (isPhoto) {
    const photos = (side === 'old' ? row.old_photos : row.new_photos) ?? []
    return photos.length
      ? <div class="flex flex-wrap gap-1">{photos.map(photo => <ClImagePreview src={photo.url} size={56} />)}</div>
      : <span class="text-gray-400">—</span>
  }

  const value = (side === 'old' ? row.old_value : row.new_value) ?? ''
  if (value === '') {
    return <span class="text-gray-400">—</span>
  }

  return isImage ? <ClImagePreview src={value} size={56} /> : <span>{value}</span>
}

const schema = ref<MaProTableSchema>({
  searchItems: [],
  tableColumns: [
    { type: 'selection', label: '选择' },
    {
      label: '用户',
      prop: 'user_nickname',
      width: '180px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.user_id, nickname: row.user_nickname, avatar: row.user_avatar, gender: row.user_gender }} clickable={false} />,
    },
    { label: '字段', prop: 'field_label', width: '90px' },
    {
      label: '旧值',
      prop: 'old_value',
      width: '200px',
      cellRender: ({ row }) => valueCell(row, 'old'),
    },
    {
      label: '新值',
      prop: 'new_value',
      cellRender: ({ row }) => valueCell(row, 'new'),
    },
    { label: '提交时间', prop: 'created_at', width: '170px' },
    {
      label: '状态',
      prop: 'status',
      width: '100px',
      cellRender: ({ row }) => <ClStatusTag status={statusToken(row.status)} text={row.reject_reason || undefined} />,
    },
    {
      type: 'operation',
      label: '操作',
      width: '160px',
      operationConfigure: {
        actions: [
          {
            name: 'approve',
            text: '通过',
            icon: 'ri:check-line',
            show: ({ row }) => row.status === 0 && hasAuth('cl:audit:profile'),
            onClick: ({ row }) => approve(row),
          },
          {
            name: 'reject',
            text: '驳回',
            icon: 'ri:close-line',
            show: ({ row }) => row.status === 0 && hasAuth('cl:audit:profile'),
            onClick: ({ row }) => openReject(row),
          },
        ],
      },
    },
  ],
})

function statusToken(value: number): string {
  return value === 1 ? 'APPROVED' : value === 2 ? 'REJECTED' : 'PENDING'
}
</script>

<template>
  <div class="mine-layout">
    <el-tabs v-model="status" class="px-3" @tab-change="onTabChange">
      <el-tab-pane v-for="tab in TABS" :key="tab.value" :label="tab.label" :name="tab.value" />
    </el-tabs>

    <MaProTable ref="proTableRef" :options="options" :schema="schema">
      <template #toolbarLeft>
        <el-button
          v-auth="['cl:audit:profile']"
          type="primary"
          plain
          :disabled="selections.length === 0"
          @click="batchApprove"
        >
          批量通过
        </el-button>
        <el-button
          v-auth="['cl:audit:profile']"
          type="danger"
          plain
          :disabled="selections.length === 0"
          @click="openBatchReject"
        >
          批量驳回
        </el-button>
      </template>
    </MaProTable>

    <ClReasonDialog
      v-model="rejectVisible"
      :title="rejectBatch ? '批量驳回' : '驳回资料'"
      :message="rejectBatch
        ? `确定要驳回选中的 ${selections.length} 条吗？会被逐条通知用户。`
        : `确定要驳回「${rejectTarget?.user_nickname ?? ''}」的${rejectTarget?.field_label ?? ''}修改吗？`"
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
  </div>
</template>
