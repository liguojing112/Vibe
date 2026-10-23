<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClMomentCommentRow, ClMomentRow } from '../../../api/moment'
import * as api from '../../../api/moment'
import ClImagePreview from '../../../components/ClImagePreview.vue'
import ClUserCell from '../../../components/ClUserCell.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * 朋友圈管理（内容管理）。
 *
 * 动态是**先发后审**的，所以这里主要做两件事：隐藏违规内容（可恢复）、必要时删除。
 * 隐藏只是 `status=2`，App 端立刻看不到，数据还在。
 */
defineOptions({ name: 'cl:content:moment' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '朋友圈动态',
    subTitle: () => '隐藏后 App 端立刻看不到，可以恢复；删除不可逆。',
  },
  searchOptions: { show: true },
  requestOptions: {
    api: (params: Record<string, any>) => api.momentList(params),
  },
})

// ---------- 评论 ----------
const commentsVisible = ref(false)
const commentsLoading = ref(false)
const comments = ref<ClMomentCommentRow[]>([])
const commentsTarget = ref<ClMomentRow | null>(null)

async function openComments(row: ClMomentRow) {
  commentsTarget.value = row
  commentsVisible.value = true
  commentsLoading.value = true
  try {
    const res = await api.momentComments(row.id, { page: 1, page_size: 100 })
    comments.value = res.code === ResultCode.SUCCESS ? res.data.list : []
  }
  finally {
    commentsLoading.value = false
  }
}

// ---------- 隐藏 / 恢复 ----------
async function toggleStatus(row: ClMomentRow) {
  const next = row.status === 2 ? 1 : 2
  const res = await api.setMomentStatus(row.id, next)
  if (res.code === ResultCode.SUCCESS) {
    msg.success(next === 2 ? '已隐藏' : '已恢复')
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

// ---------- 删除 ----------
async function remove(row: ClMomentRow) {
  const ok = await ElMessageBox.confirm(
    '删除后这条动态和它的评论区都不会再显示，且不可恢复。确定删除？',
    '删除动态',
    { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' },
  ).catch(() => false)
  if (!ok) {
    return
  }
  const res = await api.deleteMoment(row.id)
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
    { label: '用户 ID', prop: 'user_id', render: 'input', renderProps: { placeholder: '精确匹配' } },
    {
      label: '状态',
      prop: 'status',
      render: 'select',
      renderProps: {
        clearable: true,
        options: [
          { label: '正常', value: 1 },
          { label: '已隐藏', value: 2 },
        ],
      },
    },
  ],
  tableColumns: [
    { label: 'ID', prop: 'id', width: '80px' },
    {
      label: '作者',
      prop: 'nickname',
      width: '170px',
      cellRender: ({ row }) => <ClUserCell user={{ id: row.user_id, nickname: row.nickname, avatar: '', gender: 0 }} clickable={false} />,
    },
    { label: '内容', prop: 'content', showOverflowTooltip: true },
    {
      label: '图片',
      prop: 'images',
      width: '120px',
      cellRender: ({ row }) => (
        row.images?.length
          ? <ClImagePreview src={row.images} size={44} />
          : <span class="text-gray-400">—</span>
      ),
    },
    { label: '赞', prop: 'like_count', width: '70px' },
    {
      label: '评论',
      prop: 'comment_count',
      width: '90px',
      cellRender: ({ row }) => (
        row.comment_count > 0
          ? <el-link type="primary" onClick={() => openComments(row)}>{row.comment_count}</el-link>
          : <span class="text-gray-400">0</span>
      ),
    },
    {
      label: '状态',
      prop: 'status',
      width: '100px',
      cellRender: ({ row }) => (
        row.status === 2
          ? <el-tag type="info">已隐藏</el-tag>
          : <el-tag type="success">正常</el-tag>
      ),
    },
    { label: '发布时间', prop: 'created_at', width: '170px' },
    {
      type: 'operation',
      label: '操作',
      width: '170px',
      operationConfigure: {
        actions: [
          {
            name: 'comments',
            text: '评论',
            icon: 'ri:chat-3-line',
            show: () => hasAuth('cl:content:moment:comment'),
            onClick: ({ row }) => openComments(row),
          },
          {
            name: 'toggle',
            text: '隐藏/恢复',
            icon: 'ri:eye-off-line',
            show: () => hasAuth('cl:content:moment:hide'),
            onClick: ({ row }) => toggleStatus(row),
          },
          {
            name: 'delete',
            text: '删除',
            icon: 'ri:delete-bin-line',
            show: () => hasAuth('cl:content:moment:delete'),
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
    <MaProTable ref="proTableRef" :options="options" :schema="schema" />

    <el-dialog v-model="commentsVisible" title="评论" width="560px">
      <div v-loading="commentsLoading" class="max-h-96 overflow-auto">
        <el-empty v-if="!comments.length" description="还没有评论" />
        <div v-for="item in comments" :key="item.id" class="mb-3">
          <div class="text-sm text-gray-500">
            {{ item.nickname }} · {{ item.created_at }}
          </div>
          <div class="mt-1">
            {{ item.content }}
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="commentsVisible = false">
          关闭
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>
