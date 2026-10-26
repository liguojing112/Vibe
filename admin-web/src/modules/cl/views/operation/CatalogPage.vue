<script setup lang="tsx">
import type { ClCatalogRow } from '../../api/operation'
import * as api from '../../api/operation'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 目录表的通用增删改查页（docs/07 第 3.17、3.22 节）：礼物 / 会员套餐 / 每日任务 / 标签 / 城市。
 *
 * 五张表形状一样（名字 + 几个业务字段 + 排序 + 启停），所以一个组件带五个页面，
 * `fields` 描述各自那几个业务字段。**校验在服务端**（任务类型、天数、门槛），
 * 这里只负责把值传对。
 */
export interface CatalogField {
  key: string
  label: string
  type: 'text' | 'number' | 'select'
  options?: Array<{ label: string, value: any }>
  min?: number
  width?: number
  /** 列表里怎么显示（默认直接显示值）。 */
  hint?: string
}

const props = defineProps<{
  entity: 'gift' | 'vip-plan' | 'task' | 'tag' | 'city'
  title: string
  subtitle?: string
  fields: CatalogField[]
  /** 业务字段之外还要不要「图标」这一列（礼物用 emoji 或图片地址）。 */
  hasIcon?: boolean
}>()

const msg = useMessage()
const loading = ref(false)
const rows = ref<ClCatalogRow[]>([])
const selections = ref<ClCatalogRow[]>([])

const dialogVisible = ref(false)
const editing = ref<ClCatalogRow | null>(null)
const form = reactive<Record<string, any>>({})
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api.catalogList(props.entity)
    rows.value = res.code === ResultCode.SUCCESS ? (res.data as ClCatalogRow[]) : []
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.keys(form).forEach(key => delete form[key])
  props.fields.forEach((field) => {
    form[field.key] = field.type === 'number' ? 0 : (field.options?.[0]?.value ?? '')
  })
  form.sort = (rows.value.length + 1) * 10
  if (props.hasIcon) {
    form.icon = ''
  }
  dialogVisible.value = true
}

function openEdit(row: ClCatalogRow) {
  editing.value = row
  Object.keys(form).forEach(key => delete form[key])
  props.fields.forEach((field) => {
    form[field.key] = (row as any)[field.key] ?? (field.type === 'number' ? 0 : '')
  })
  form.sort = row.sort
  if (props.hasIcon) {
    form.icon = row.icon ?? ''
  }
  dialogVisible.value = true
}

async function submit() {
  saving.value = true
  try {
    const payload: Record<string, any> = { ...form, enabled: editing.value?.enabled ?? 1 }
    if (editing.value) {
      payload.id = editing.value.id
    }

    const res = await api.catalogSave(props.entity, payload)
    if (res.code === ResultCode.SUCCESS) {
      msg.success(editing.value ? '已保存' : '已新增')
      dialogVisible.value = false
      await load()
    }
    else {
      msg.error(res.message)
    }
  }
  finally {
    saving.value = false
  }
}

async function remove(ids: number[]) {
  msg.confirm(`确定要删除这 ${ids.length} 条吗？正在被历史记录引用的不受影响（单据里存的是名字）。`).then(async () => {
    const res = await api.catalogDelete(props.entity, ids)
    if (res.code === ResultCode.SUCCESS) {
      msg.success(`已删除 ${res.data.deleted} 条`)
      await load()
    }
    else {
      msg.error(res.message)
    }
  })
}

/** 启停直接切（比进弹窗改快，运营最常做的就是上下架）。 */
async function toggleEnabled(row: ClCatalogRow) {
  const payload: Record<string, any> = { id: row.id, enabled: row.enabled === 1 ? 0 : 1 }
  props.fields.forEach((field) => {
    payload[field.key] = (row as any)[field.key]
  })
  if (props.hasIcon) {
    payload.icon = row.icon ?? ''
  }

  const res = await api.catalogSave(props.entity, payload)
  if (res.code === ResultCode.SUCCESS) {
    msg.success(payload.enabled === 1 ? '已启用' : '已停用')
    await load()
  }
  else {
    msg.error(res.message)
  }
}

/** 图标列：图片地址显示缩略图，emoji / 文本原样显示。 */
function isImageIcon(icon?: string | null): boolean {
  return !!icon && /^(?:https?:\/\/|\/)/.test(icon)
}

onMounted(load)
</script>

<template>
  <div class="mine-layout">
    <el-card shadow="never" class="m-3">
      <template #header>
        <div class="text-base font-medium">
          {{ props.title }}
        </div>
        <div v-if="props.subtitle" class="mt-1 text-xs text-gray-500">
          {{ props.subtitle }}
        </div>
      </template>

      <div class="mb-3 flex gap-2">
        <el-button v-auth="[`cl:operation:${props.entity === 'vip-plan' ? 'vip' : props.entity === 'tag' || props.entity === 'city' ? 'tag-city' : props.entity}`]" type="primary" @click="openCreate">
          新增
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="selections.length === 0"
          @click="remove(selections.map(row => row.id))"
        >
          批量删除
        </el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="rows"
        border
        @selection-change="(selected: ClCatalogRow[]) => selections = selected"
      >
        <el-table-column type="selection" width="50" />
        <el-table-column v-if="props.hasIcon" label="图标" width="90">
          <template #default="{ row }">
            <el-image
              v-if="isImageIcon(row.icon)"
              :src="row.icon"
              :preview-src-list="[row.icon]"
              preview-teleported
              fit="cover"
              class="h-10 w-10 rounded"
            />
            <span v-else class="text-xl">{{ row.icon }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" width="160" />
        <el-table-column
          v-for="field in props.fields"
          :key="field.key"
          :label="field.label"
          :width="field.width ?? 120"
        >
          <template #default="{ row }">
            <span>{{ field.options?.find(o => o.value === row[field.key])?.label ?? row[field.key] }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="90" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.enabled === 1 ? 'success' : 'info'" size="small" disable-transitions>
              {{ row.enabled === 1 ? '启用中' : '已停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180">
          <template #default="{ row }">
            <el-link type="primary" @click="openEdit(row as any)">
              编辑
            </el-link>
            <el-link class="ml-3" type="warning" @click="toggleEnabled(row as any)">
              {{ row.enabled === 1 ? '停用' : '启用' }}
            </el-link>
            <el-link class="ml-3" type="danger" @click="remove([row.id])">
              删除
            </el-link>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="editing ? `编辑${props.title}` : `新增${props.title}`" width="520px">
      <el-form label-width="90px">
        <el-form-item label="名称">
          <el-input v-model="form.name" maxlength="64" />
        </el-form-item>
        <el-form-item v-if="props.hasIcon" label="图标">
          <div class="w-full flex items-center gap-2">
            <el-input v-model="form.icon" maxlength="255" placeholder="emoji（如 ⭐）或图片地址" />
            <el-image
              v-if="isImageIcon(form.icon)"
              :src="form.icon"
              :preview-src-list="[form.icon]"
              preview-teleported
              fit="cover"
              class="h-10 w-10 shrink-0 rounded"
            />
            <span v-else class="shrink-0 text-xl">{{ form.icon }}</span>
          </div>
        </el-form-item>
        <el-form-item v-for="field in props.fields" :key="field.key" :label="field.label">
          <el-input-number
            v-if="field.type === 'number'"
            v-model="form[field.key]"
            :min="field.min ?? 0"
            controls-position="right"
          />
          <el-select v-else-if="field.type === 'select'" v-model="form[field.key]">
            <el-option v-for="option in field.options" :key="option.value" :label="option.label" :value="option.value" />
          </el-select>
          <el-input v-else v-model="form[field.key]" maxlength="255" :placeholder="field.hint" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :max="9999" controls-position="right" />
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
