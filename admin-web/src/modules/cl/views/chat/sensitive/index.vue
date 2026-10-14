<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClSensitiveWordRow } from '../../../api/chat'
import * as api from '../../../api/chat'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'

/**
 * 敏感词库（docs/07 第 3.8 节）。
 *
 * 改完**服务端会把缓存清掉**，所以加词之后 App 那边立刻生效，不用等 10 分钟 TTL。
 */
defineOptions({ name: 'cl:chat:sensitive' })

const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<ClSensitiveWordRow[]>([])

// ---------- 新增 / 编辑 ----------
const editVisible = ref(false)
const editTarget = ref<ClSensitiveWordRow | null>(null)
const editForm = reactive({ word: '', enabled: true })
const editLoading = ref(false)

function openCreate() {
  editTarget.value = null
  editForm.word = ''
  editForm.enabled = true
  editVisible.value = true
}

function openEdit(row: ClSensitiveWordRow) {
  editTarget.value = row
  editForm.word = row.word
  editForm.enabled = row.enabled
  editVisible.value = true
}

async function submitEdit() {
  if (editForm.word.trim() === '') {
    msg.error('敏感词不能为空')
    return
  }
  editLoading.value = true
  try {
    const res = editTarget.value
      ? await api.sensitiveUpdate(editTarget.value.id, editForm.word.trim(), editForm.enabled)
      : await api.sensitiveCreate(editForm.word.trim())
    after(res, editTarget.value ? '已保存' : '已新增')
    if (res.code === ResultCode.SUCCESS) {
      editVisible.value = false
    }
  }
  finally {
    editLoading.value = false
  }
}

// ---------- 删除 ----------
// 删词是个「再加回来就行」的操作，所以用普通二次确认 —— 不问原因、也不假装写了流水
async function remove(ids: number[], tip: string) {
  msg.confirm(tip).then(async () => {
    const res = await api.sensitiveDelete(ids)
    after(res, `已删除 ${res.data?.deleted ?? 0} 个词`)
  })
}

// ---------- 批量导入 ----------
const importVisible = ref(false)
const importText = ref('')
const importing = ref(false)

async function submitImport() {
  importing.value = true
  try {
    const res = await api.sensitiveImport(importText.value)
    if (res.code !== ResultCode.SUCCESS) {
      msg.error(res.message)
      return
    }
    const { added, skipped } = res.data
    if (skipped.length > 0) {
      msg.warning(`导入 ${added} 个，跳过 ${skipped.length} 个（重复或太长）`)
    }
    else {
      msg.success(`导入 ${added} 个`)
    }
    importVisible.value = false
    importText.value = ''
    proTableRef.value?.refresh()
  }
  finally {
    importing.value = false
  }
}

function after(res: any, successText: string) {
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
    mainTitle: () => '敏感词库',
    subTitle: () => '命中只标记、不拦截（消息照常送达，进「待审消息」人工判断）。改完立刻生效。',
  },
  searchOptions: { fold: true },
  requestOptions: {
    api: (params: Record<string, any>) => api.sensitiveList(params),
  },
  tableOptions: {
    on: {
      onSelectionChange: (rows: ClSensitiveWordRow[]) => selections.value = rows,
    },
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: [
    { label: '敏感词', prop: 'keyword', render: 'input', renderProps: { clearable: true } },
  ],
  tableColumns: [
    { type: 'selection', label: '选择' },
    { label: '敏感词', prop: 'word' },
    {
      label: '状态',
      prop: 'enabled',
      width: '110px',
      cellRender: ({ row }) => (
        <el-tag type={row.enabled ? 'success' : 'info'} size="small" disable-transitions>
          {row.enabled ? '启用中' : '已停用'}
        </el-tag>
      ),
    },
    { label: '添加时间', prop: 'created_at', width: '170px' },
    {
      type: 'operation',
      label: '操作',
      width: '140px',
      operationConfigure: {
        actions: [
          {
            name: 'edit',
            text: '编辑',
            icon: 'ri:edit-line',
            show: () => hasAuth('cl:chat:sensitive'),
            onClick: ({ row }) => openEdit(row),
          },
          {
            name: 'delete',
            text: '删除',
            icon: 'ri:delete-bin-line',
            show: () => hasAuth('cl:chat:sensitive'),
            onClick: ({ row }) => remove([row.id], `确定要删除「${row.word}」吗？`),
          },
        ],
      },
    },
  ],
})
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema">
      <template #toolbarLeft>
        <el-button v-auth="['cl:chat:sensitive']" type="primary" @click="openCreate">
          新增敏感词
        </el-button>
        <el-button v-auth="['cl:chat:sensitive']" plain @click="importVisible = true">
          批量导入
        </el-button>
        <el-button
          v-auth="['cl:chat:sensitive']"
          type="danger"
          plain
          :disabled="selections.length === 0"
          @click="remove(selections.map(row => row.id), `确定要删除选中的 ${selections.length} 个词吗？`)"
        >
          批量删除
        </el-button>
      </template>
    </MaProTable>

    <el-dialog v-model="editVisible" :title="editTarget ? '编辑敏感词' : '新增敏感词'" width="420px">
      <el-form label-width="70px">
        <el-form-item label="敏感词">
          <el-input v-model="editForm.word" maxlength="64" />
        </el-form-item>
        <el-form-item v-if="editTarget" label="状态">
          <el-switch v-model="editForm.enabled" active-text="启用" inactive-text="停用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">
          取消
        </el-button>
        <el-button type="primary" :loading="editLoading" @click="submitEdit">
          保存
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="importVisible" title="批量导入" width="520px">
      <el-alert type="info" :closable="false" class="mb-3" title="一行一个词；已经存在的和超过 64 个字的会自动跳过。" />
      <el-input v-model="importText" type="textarea" :rows="10" placeholder="政治敏感词&#10;赌博&#10;诈骗" />
      <template #footer>
        <el-button @click="importVisible = false">
          取消
        </el-button>
        <el-button type="primary" :loading="importing" @click="submitImport">
          开始导入
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>
