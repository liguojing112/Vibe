<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClBatchCreateResult, ClBatchRow } from '../../../api/user'
import * as api from '../../../api/user'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import { useRouter } from 'vue-router'

/**
 * 后台批量开号（docs/07 第 3.3 节、docs/04 第 7 节）。
 *
 * **明文密码只在这个页面里活一次**：提交后服务端把账号和明文一起返回，
 * 弹窗里显示 + 当场下载，关掉弹窗就再也拿不到了（服务端一个字节都没存）。
 * 所以那个下载是**在浏览器里用内存里的明文现拼的**，不是去服务端再要一次 ——
 * 服务端也拿不出来。文件用带 BOM 的 CSV，Excel 双击打开中文不乱码。
 *
 * `07` 这一节写的就是「下载 CSV」；用户列表那个导出才是 Excel（服务端 openspout 生成）。
 */
defineOptions({ name: 'cl:user:batch' })

const msg = useMessage()
const router = useRouter()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>

// ---------- 开号表单 ----------
const form = reactive({
  gender: 2,
  count: 10,
  prefix: '',
  remark: '',
})
const submitting = ref(false)

const prefixPlaceholder = computed(() => (form.gender === 2 ? '默认 F' : '默认 M'))

// ---------- 结果弹窗 ----------
const resultVisible = ref(false)
const result = ref<ClBatchCreateResult | null>(null)
const exported = ref(false)
const exportedPasswordOnce = '这个密码只会显示这一次，关掉弹窗就再也看不到（服务端没有存）'

async function submit() {
  submitting.value = true
  try {
    const res = await api.batchCreate({
      gender: form.gender,
      count: form.count,
      prefix: form.prefix || undefined,
      remark: form.remark || undefined,
    })
    if (res.code !== ResultCode.SUCCESS) {
      msg.error(res.message)
      return
    }
    result.value = res.data
    exported.value = false
    resultVisible.value = true
    proTableRef.value?.refresh()
  }
  finally {
    submitting.value = false
  }
}

/** 用内存里的明文现拼一个 CSV（带 BOM，Excel 打开不乱码），再告诉服务端「导过了」。 */
async function download() {
  if (!result.value) {
    return
  }
  const rows = [
    ['账号', '昵称', '初始密码'],
    ...result.value.accounts.map(item => [item.account, item.nickname, item.password]),
  ]
  const csv = rows
    .map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\r\n')

  // 前面加 BOM：不加的话 Excel 会按本地编码打开，中文变乱码
  const bom = String.fromCharCode(0xFEFF)
  const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `批量开号-${result.value.batch_no}.csv`
  link.click()
  URL.revokeObjectURL(url)

  exported.value = true
  await api.markBatchExported(result.value.batch_no)
  proTableRef.value?.refresh()
}

// ---------- 批次列表 ----------
const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => '批量开号',
    subTitle: () => '账号规则：前缀 + 8 位数字；新号没有头像，本人上传并过审后才会出现在发现页。',
  },
  searchOptions: { fold: true, show: false },
  requestOptions: {
    api: (params: Record<string, any>) => api.batchList(params),
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: [],
  tableColumns: [
    {
      label: '批次号',
      prop: 'batch_no',
      width: '220px',
      cellRender: ({ row }) => (
        <el-link type="primary" onClick={() => openBatchAccounts(row)}>
          {row.batch_no}
        </el-link>
      ),
    },
    {
      label: '性别',
      prop: 'gender',
      width: '80px',
      cellRender: ({ row }) => <span>{row.gender === 2 ? '女' : '男'}</span>,
    },
    { label: '数量', prop: 'count', width: '80px' },
    { label: '账号前缀', prop: 'account_prefix', width: '100px' },
    { label: '操作人', prop: 'operator_name', width: '120px' },
    { label: '备注', prop: 'remark' },
    {
      label: '是否已导出',
      prop: 'exported',
      width: '110px',
      cellRender: ({ row }) => (
        <el-tag type={row.exported ? 'success' : 'info'} size="small" disable-transitions>
          {row.exported ? '已导出' : '未导出'}
        </el-tag>
      ),
    },
    { label: '创建时间', prop: 'created_at', width: '170px' },
  ],
})

/** 点批次号：跳到对应的用户列表，并带上批次号筛选（docs/07 第 3.3 节）。 */
function openBatchAccounts(row: ClBatchRow) {
  router.push({
    path: row.gender === 2 ? '/cl/user/female' : '/cl/user/male',
    query: { batch_no: row.batch_no },
  })
}
</script>

<template>
  <div class="mine-layout">
    <el-card shadow="never" class="mb-3">
      <el-alert
        v-if="form.gender === 2"
        type="warning"
        :closable="false"
        show-icon
        class="mb-3"
        title="每个女号只能由一名真实的签约陪聊人员使用，资料和照片必须是本人真实信息。"
      />
      <el-form :model="form" label-width="90px" inline>
        <el-form-item label="性别">
          <el-radio-group v-model="form.gender">
            <el-radio :value="1">
              男号
            </el-radio>
            <el-radio :value="2">
              女号
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="数量">
          <el-input-number v-model="form.count" :min="1" :max="200" controls-position="right" />
        </el-form-item>
        <el-form-item label="账号前缀">
          <el-input v-model="form.prefix" :placeholder="prefixPlaceholder" maxlength="8" style="width: 140px;" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" maxlength="200" placeholder="例如：8 月批次" style="width: 220px;" />
        </el-form-item>
        <el-form-item>
          <el-button v-auth="['cl:user:batch']" type="primary" :loading="submitting" @click="submit">
            生成账号
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <MaProTable ref="proTableRef" :options="options" :schema="schema" />

    <el-dialog
      v-model="resultVisible"
      title="账号已生成"
      width="720px"
      :close-on-click-modal="false"
    >
      <el-alert type="warning" :closable="false" show-icon :title="exportedPasswordOnce" class="mb-3" />
      <div class="mb-2 text-sm text-gray-500">
        批次号：{{ result?.batch_no }} · 共 {{ result?.count }} 个
      </div>
      <el-table :data="result?.accounts ?? []" size="small" border max-height="360">
        <el-table-column prop="account" label="账号" width="150" />
        <el-table-column prop="nickname" label="昵称" width="130" />
        <el-table-column prop="password" label="初始密码" />
      </el-table>

      <template #footer>
        <el-button @click="resultVisible = false">
          关闭
        </el-button>
        <el-button type="primary" :disabled="exported" @click="download">
          {{ exported ? '已下载' : '下载 CSV' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>
