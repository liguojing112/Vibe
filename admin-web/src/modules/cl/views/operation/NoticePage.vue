<script setup lang="tsx">
import * as api from '../../api/operation'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 系统通知（docs/07 第 3.20 节）。
 *
 * 全员公告（不传 `user_ids`）和定向发送共用一个接口 —— 界面上本来就是「发给谁」的一个选择。
 * 定向发送时**不存在的 ID 会被跳过并报出来**，不会整个失败。
 */
defineOptions({ name: 'cl:operation:notice' })

const msg = useMessage()
const form = reactive({ title: '', content: '', mode: 'all' as 'all' | 'direct', userIds: '' })
const publishing = ref(false)

const list = ref<Array<{ id: number, user_id: number, title: string, content: string, biz_type: string, created_at?: string }>>([])
const loading = ref(false)
const page = ref(1)
const total = ref(0)
const PAGE_SIZE = 20

async function load() {
  loading.value = true
  try {
    const res = await api.publishedNotices({ page: page.value, page_size: PAGE_SIZE })
    if (res.code === ResultCode.SUCCESS) {
      list.value = res.data.list
      total.value = res.data.total
    }
  }
  finally {
    loading.value = false
  }
}

async function publish() {
  if (form.title.trim() === '') {
    msg.error('必须填标题')
    return
  }

  const userIds = form.userIds
    .split(/[\s,，]+/)
    .map(item => Number(item.trim()))
    .filter(item => Number.isInteger(item) && item > 0)

  if (form.mode === 'direct' && userIds.length === 0) {
    msg.error('定向发送至少要填一个用户 ID')
    return
  }

  publishing.value = true
  try {
    const res = await api.publishNotice({
      title: form.title.trim(),
      content: form.content.trim(),
      user_ids: form.mode === 'direct' ? userIds : undefined,
    })
    if (res.code !== ResultCode.SUCCESS) {
      msg.error(res.message)
      return
    }

    if (res.data.mode === 'announce') {
      msg.success('公告已发布，所有用户都能看到')
    }
    else if ((res.data.missing ?? []).length > 0) {
      msg.warning(`已发给 ${res.data.sent} 人，跳过找不到的 ${(res.data.missing ?? []).length} 个 ID`)
    }
    else {
      msg.success(`已发给 ${res.data.sent} 人`)
    }

    form.title = ''
    form.content = ''
    form.userIds = ''
    await load()
  }
  finally {
    publishing.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <el-card shadow="never" class="m-3">
      <template #header>
        <div class="text-base font-medium">
          发布通知
        </div>
        <div class="mt-1 text-xs text-gray-500">
          公告会进每个用户的「系统通知」；定向发送只发给填了 ID 的那些人。
        </div>
      </template>

      <el-form label-width="90px" style="max-width: 720px;">
        <el-form-item label="发给">
          <el-radio-group v-model="form.mode">
            <el-radio value="all">
              全员公告
            </el-radio>
            <el-radio value="direct">
              指定用户
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="form.mode === 'direct'" label="用户 ID">
          <el-input v-model="form.userIds" placeholder="多个 ID 用空格或逗号分开，例如 111 112 113" />
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="form.title" maxlength="64" show-word-limit />
        </el-form-item>
        <el-form-item label="正文">
          <el-input v-model="form.content" type="textarea" :rows="4" maxlength="500" show-word-limit />
        </el-form-item>
        <el-form-item>
          <el-button v-auth="['cl:operation:notice']" type="primary" :loading="publishing" @click="publish">
            发布
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card v-loading="loading" shadow="never" class="m-3">
      <template #header>
        <div class="text-base font-medium">
          已发布（{{ total }}）
        </div>
        <div class="mt-1 text-xs text-gray-500">
          只列从这里发出去的；用户自己收到的提现/实名/等级通知不在这张表里。
        </div>
      </template>

      <el-table :data="list" border>
        <el-table-column label="发给" width="120">
          <template #default="{ row }">
            {{ row.user_id === 0 ? '全员' : `ID ${row.user_id}` }}
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" width="200" />
        <el-table-column prop="content" label="正文" show-overflow-tooltip />
        <el-table-column prop="created_at" label="时间" width="180" />
      </el-table>

      <el-pagination
        v-if="total > PAGE_SIZE"
        class="mt-3 justify-end"
        layout="prev, pager, next"
        :total="total"
        :page-size="PAGE_SIZE"
        :current-page="page"
        @current-change="(value: number) => { page = value; load() }"
      />
    </el-card>
  </div>
</template>
