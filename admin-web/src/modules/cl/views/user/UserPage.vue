<script setup lang="ts">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { ClUserRow } from '../../api/user'
import * as api from '../../api/user'
import * as financeApi from '../../api/finance'
import ClReasonDialog from '../../components/ClReasonDialog.vue'
import ClUserDrawer from '../../components/ClUserDrawer.vue'
import getTableColumns from './data/columns'
import getSearchItems from './data/search'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

/**
 * 后台用户管理（docs/07 第 3.2 节）。
 *
 * 男用户页和女用户页**是同一个组件**，只有 `gender` 一个参数不同 ——
 * 两个页面 90% 是一样的，分开写迟早会走岔（加了字段只改一边）。
 */
const props = defineProps<{ gender: number }>()

const female = computed(() => props.gender === 2)
const msg = useMessage()
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<ClUserRow[]>([])

// ---------- 抽屉 ----------
const drawerVisible = ref(false)
const drawerUserId = ref<number | null>(null)

function openDetail(row: ClUserRow) {
  drawerUserId.value = row.id ?? null
  drawerVisible.value = true
}

// ---------- 编辑资料 ----------
const editVisible = ref(false)
const editTarget = ref<ClUserRow | null>(null)
const editForm = reactive({ nickname: '', avatar: '', bio: '' })
const editLoading = ref(false)

function openEdit(row: ClUserRow) {
  editTarget.value = row
  editForm.nickname = row.nickname ?? ''
  editForm.avatar = row.avatar ?? ''
  editForm.bio = ''
  editVisible.value = true
}

async function submitEdit() {
  if (!editTarget.value?.id) {
    return
  }
  editLoading.value = true
  try {
    const res = await api.updateProfile(editTarget.value.id, {
      nickname: editForm.nickname || undefined,
      avatar: editForm.avatar || undefined,
      bio: editForm.bio || undefined,
    })
    afterAction(res, '资料已修改')
    editVisible.value = false
  }
  finally {
    editLoading.value = false
  }
}

// ---------- 重置密码（明文只显示这一次）----------
const passwordVisible = ref(false)
const newPassword = ref('')

async function resetPassword(row: ClUserRow) {
  if (!row.id) {
    return
  }
  const res = await api.resetPassword(row.id)
  if (res.code === ResultCode.SUCCESS) {
    newPassword.value = res.data.password
    passwordVisible.value = true
  }
  else {
    msg.error(res.message)
  }
}

// ---------- 封禁 / 解封 ----------
const banVisible = ref(false)
const unbanVisible = ref(false)
const banTarget = ref<ClUserRow | null>(null)
const banDays = ref(7)
const banLoading = ref(false)

const BAN_DAYS = [
  { label: '1 天', value: 1 },
  { label: '3 天', value: 3 },
  { label: '7 天', value: 7 },
  { label: '30 天', value: 30 },
  { label: '永久', value: 0 },
]

function openBan(row: ClUserRow) {
  banTarget.value = row
  banDays.value = 7
  banVisible.value = true
}

function openUnban(row: ClUserRow) {
  banTarget.value = row
  unbanVisible.value = true
}

async function submitBan(reason: string) {
  if (!banTarget.value?.id) {
    return
  }
  banLoading.value = true
  try {
    afterAction(await api.ban(banTarget.value.id, reason, banDays.value), '已封禁')
    banVisible.value = false
  }
  finally {
    banLoading.value = false
  }
}

async function submitUnban(reason: string) {
  if (!banTarget.value?.id) {
    return
  }
  banLoading.value = true
  try {
    afterAction(await api.unban(banTarget.value.id, reason), '已解封')
    unbanVisible.value = false
  }
  finally {
    banLoading.value = false
  }
}

// ---------- 调整等级 ----------
const levelVisible = ref(false)
const levelTarget = ref<ClUserRow | null>(null)
const levelValue = ref(1)
const levelLocked = ref(false)
const levelLoading = ref(false)

function openLevel(row: ClUserRow) {
  levelTarget.value = row
  levelValue.value = row.level ?? 1
  levelLocked.value = row.level_locked === 1
  levelVisible.value = true
}

async function submitLevel(reason: string) {
  if (!levelTarget.value?.id) {
    return
  }
  levelLoading.value = true
  try {
    afterAction(await api.setLevel(levelTarget.value.id, levelValue.value, levelLocked.value, reason), '等级已调整')
    levelVisible.value = false
  }
  finally {
    levelLoading.value = false
  }
}

// ---------- 开通会员 ----------
const vipVisible = ref(false)
const vipTarget = ref<ClUserRow | null>(null)
const vipForm = reactive({ days: 30, remark: '' })
const vipLoading = ref(false)

function openVip(row: ClUserRow) {
  vipTarget.value = row
  vipForm.days = 30
  vipForm.remark = ''
  vipVisible.value = true
}

async function submitVip() {
  if (!vipTarget.value?.id) {
    return
  }
  vipLoading.value = true
  try {
    // 套餐下拉要等 T7.7 的运营配置接口；这里先只支持「填天数」
    const res = await api.grantVip(vipTarget.value.id, { days: vipForm.days, remark: vipForm.remark || undefined })
    afterAction(res, '会员已开通')
    vipVisible.value = false
  }
  finally {
    vipLoading.value = false
  }
}

// ---------- 修改性别 ----------
const genderVisible = ref(false)
const genderTarget = ref<ClUserRow | null>(null)
const genderValue = ref(1)
const genderLoading = ref(false)

function openGender(row: ClUserRow) {
  genderTarget.value = row
  genderValue.value = row.gender === 1 ? 2 : 1
  genderVisible.value = true
}

async function submitGender(reason: string) {
  if (!genderTarget.value?.id) {
    return
  }
  genderLoading.value = true
  try {
    afterAction(await api.changeGender(genderTarget.value.id, genderValue.value, reason), '性别已修改')
    genderVisible.value = false
  }
  finally {
    genderLoading.value = false
  }
}

// ---------- 扣金币 / 调整积分（超管，docs/06 第 8 节）----------
const adjustVisible = ref(false)
const adjustTarget = ref<ClUserRow | null>(null)
const adjustMode = ref<'coin' | 'point'>('coin')
const adjustAmount = ref(0)
const adjustReason = ref('')
const adjustLoading = ref(false)

function openAdjust(row: ClUserRow, mode: 'coin' | 'point') {
  adjustTarget.value = row
  adjustMode.value = mode
  adjustAmount.value = 0
  adjustReason.value = ''
  adjustVisible.value = true
}

async function submitAdjust() {
  if (adjustAmount.value === 0) {
    msg.error('数量不能是 0')
    return
  }
  if (adjustReason.value.trim() === '') {
    msg.error('必须填原因')
    return
  }

  adjustLoading.value = true
  try {
    const payload = {
      user_id: adjustTarget.value!.id!,
      amount: adjustAmount.value,
      reason: adjustReason.value.trim(),
    }
    // 扣金币只接受正数（服务端取绝对值）；调积分正数补发、负数扣减
    const res = adjustMode.value === 'coin'
      ? await financeApi.coinDeduct({ ...payload, amount: Math.abs(adjustAmount.value) })
      : await financeApi.pointAdjust(payload)
    afterAction(res, adjustMode.value === 'coin' ? '金币已扣减' : '积分已调整')
    if (res.code === ResultCode.SUCCESS) {
      adjustVisible.value = false
    }
  }
  finally {
    adjustLoading.value = false
  }
}

// ---------- 加款（客服/超管）----------
const grantVisible = ref(false)
const grantTarget = ref<ClUserRow | null>(null)
const grantAmount = ref(0)
const grantAmountFen = ref(0)
const grantRemark = ref('')
const grantLoading = ref(false)

function openGrant(row: ClUserRow) {
  grantTarget.value = row
  grantAmount.value = 0
  grantAmountFen.value = 0
  grantRemark.value = ''
  grantVisible.value = true
}

async function submitGrant() {
  if (!grantTarget.value || grantAmount.value <= 0) {
    msg.error('加款数量必须是正整数')
    return
  }
  if (grantRemark.value.trim() === '') {
    msg.error('必须填写备注')
    return
  }

  grantLoading.value = true
  try {
    const res = await financeApi.grant({
      user_id: grantTarget.value.id!,
      amount: grantAmount.value,
      amount_fen: female.value ? 0 : grantAmountFen.value,
      remark: grantRemark.value.trim(),
    })
    afterAction(res, female.value ? '积分已加款' : '金币已加款')
    if (res.code === ResultCode.SUCCESS) {
      grantVisible.value = false
    }
  }
  finally {
    grantLoading.value = false
  }
}

// ---------- 导出 ----------
const exporting = ref(false)

async function exportExcel() {
  exporting.value = true
  try {
    await api.exportExcel({
      ...(proTableRef.value?.getSearchForm() ?? {}),
      gender: props.gender,
    } as api.ClUserSearch)
    msg.success('导出完成')
  }
  catch (error: any) {
    msg.alertError(error)
  }
  finally {
    exporting.value = false
  }
}

// ---------- 表格 ----------
const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => (female.value ? '女用户' : '男用户'),
    subTitle: () => (female.value
      ? '收益、等级、实名都在这边看；导出不含密码和身份证。'
      : '消费、充值和会员到期在这边看；导出不含密码和身份证。'),
  },
  tableOptions: {
    on: {
      onSelectionChange: (rows: ClUserRow[]) => selections.value = rows,
    },
  },
  searchOptions: { fold: true },
  searchFormOptions: { labelWidth: '100px' },
  requestOptions: {
    // 男女共用一个接口，靠 gender 区分
    api: (params: Record<string, any>) => api.page({ ...params, gender: props.gender } as api.ClUserSearch),
  },
})

const schema = ref<MaProTableSchema>({
  searchItems: getSearchItems(female.value),
  tableColumns: getTableColumns(female.value, {
    openDetail,
    openEdit,
    openBan,
    openUnban,
    openLevel,
    openVip,
    openGender,
    resetPassword,
    openGrant,
    openAdjust,
  }),
})

function afterAction(res: { code: number, message: string }, successText: string) {
  if (res.code === ResultCode.SUCCESS) {
    msg.success(successText)
    proTableRef.value?.refresh()
  }
  else {
    msg.error(res.message)
  }
}

/**
 * 从「批量开号」页点批次号跳过来时带上 `?batch_no=`，这里把它填进搜索框再查一次
 * （docs/07 第 3.3 节：点击批次号查看批次内账号）。
 */
onMounted(async () => {
  const batchNo = useRoute().query.batch_no
  if (typeof batchNo !== 'string' || batchNo === '') {
    return
  }

  await nextTick()
  proTableRef.value?.setSearchForm({ batch_no: batchNo })
  proTableRef.value?.search()
})
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema">
      <template #toolbarLeft>
        <el-button
          type="primary"
          plain
          :loading="exporting"
          @click="exportExcel"
        >
          导出 Excel
        </el-button>
      </template>
    </MaProTable>

    <!-- 用户详情抽屉（任何地方点用户都打开它） -->
    <ClUserDrawer v-model="drawerVisible" :user-id="drawerUserId" />

    <!-- 编辑资料：绕过审核，直接改「已通过」的字段 -->
    <el-dialog v-model="editVisible" title="编辑资料" width="480px">
      <el-form label-width="80px">
        <el-form-item label="昵称">
          <el-input v-model="editForm.nickname" maxlength="32" show-word-limit />
        </el-form-item>
        <el-form-item label="头像">
          <el-input v-model="editForm.avatar" placeholder="图片地址" />
        </el-form-item>
        <el-form-item label="自我介绍">
          <el-input v-model="editForm.bio" type="textarea" :rows="3" maxlength="255" show-word-limit />
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

    <!-- 重置密码：明文只出现这一次 -->
    <el-dialog v-model="passwordVisible" title="重置密码" width="460px">
      <el-alert type="warning" :closable="false" show-icon title="这个密码只会显示这一次，请立刻复制给用户。" />
      <div class="mt-3 text-center text-lg font-medium tracking-wider">
        {{ newPassword }}
      </div>
    </el-dialog>

    <ClReasonDialog
      v-model="banVisible"
      title="封禁账号"
      :message="`确定要封禁「${banTarget?.nickname ?? ''}」吗？封禁后他手上的登录状态会立刻失效。`"
      confirm-text="确认封禁"
      :loading="banLoading"
      @confirm="submitBan"
    >
      <el-form label-width="60px" class="mb-2">
        <el-form-item label="时长">
          <el-select v-model="banDays">
            <el-option v-for="item in BAN_DAYS" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
      </el-form>
    </ClReasonDialog>

    <ClReasonDialog
      v-model="unbanVisible"
      title="解封账号"
      :message="`确定要给「${banTarget?.nickname ?? ''}」解封吗？`"
      confirm-text="确认解封"
      :loading="banLoading"
      @confirm="submitUnban"
    />

    <ClReasonDialog
      v-model="levelVisible"
      title="调整等级"
      message="等级决定她的私信单价和分成比例，改错了会直接影响收入。"
      confirm-text="确认调整"
      :loading="levelLoading"
      @confirm="submitLevel"
    >
      <el-form label-width="60px" class="mb-2">
        <el-form-item label="等级">
          <el-select v-model="levelValue">
            <el-option v-for="level in 5" :key="level" :label="`Lv${level}`" :value="level" />
          </el-select>
        </el-form-item>
        <el-form-item label="锁定">
          <el-switch v-model="levelLocked" />
          <span class="ml-2 text-xs text-gray-400">锁定后不再自动升级</span>
        </el-form-item>
      </el-form>
    </ClReasonDialog>

    <el-dialog
      v-model="adjustVisible"
      :title="adjustMode === 'coin' ? '扣减金币' : '调整积分'"
      width="460px"
    >
      <el-alert
        type="warning"
        :closable="false"
        show-icon
        class="mb-3"
        :title="adjustMode === 'coin'
          ? '余额不允许扣成负数；每一笔都会写后台动作流水。'
          : '正数是补发、负数是扣减；补发不计入累计收益，等级不会被顶上去。'"
      />
      <el-form label-width="80px">
        <el-form-item :label="adjustMode === 'coin' ? '扣减数量' : '调整数量'">
          <el-input-number
            v-model="adjustAmount"
            :min="adjustMode === 'coin' ? 1 : -1000000"
            :max="1000000"
            controls-position="right"
          />
        </el-form-item>
        <el-form-item label="原因">
          <el-input v-model="adjustReason" maxlength="200" placeholder="会记入后台动作流水" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustVisible = false">
          取消
        </el-button>
        <el-button type="danger" :loading="adjustLoading" @click="submitAdjust">
          确认
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="grantVisible" :title="`加款 · ${grantTarget?.nickname ?? ''}`" width="460px">
      <el-alert
        type="info"
        :closable="false"
        show-icon
        class="mb-3"
        :title="female
          ? '给女号加积分：写积分补发流水，不计入累计收益。'
          : '给男号加金币：会生成一条后台充值单和金币流水。'"
      />
      <el-form label-width="90px">
        <el-form-item :label="female ? '积分数量' : '金币数量'">
          <el-input-number v-model="grantAmount" :min="1" :max="10000000" controls-position="right" />
        </el-form-item>
        <el-form-item v-if="!female" label="实收金额">
          <el-input-number v-model="grantAmountFen" :min="0" :max="100000000" controls-position="right" />
          <span class="ml-2 text-xs text-gray-400">单位：分；赠送填 0</span>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="grantRemark" maxlength="200" placeholder="必填，会记入后台流水" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="grantVisible = false">
          取消
        </el-button>
        <el-button type="primary" :loading="grantLoading" @click="submitGrant">
          确认加款
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="vipVisible" title="开通会员" width="460px">
      <el-form label-width="80px">
        <el-form-item label="开通天数">
          <el-input-number v-model="vipForm.days" :min="1" :max="3650" controls-position="right" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="vipForm.remark" maxlength="200" placeholder="例如：客服补偿" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="vipVisible = false">
          取消
        </el-button>
        <el-button type="primary" :loading="vipLoading" @click="submitVip">
          确认开通
        </el-button>
      </template>
    </el-dialog>

    <ClReasonDialog
      v-model="genderVisible"
      title="修改性别"
      message="性别关系到计费方向（男付、女收），改错会把钱算反。"
      confirm-text="确认修改"
      :loading="genderLoading"
      @confirm="submitGender"
    >
      <el-form label-width="60px" class="mb-2">
        <el-form-item label="改为">
          <el-radio-group v-model="genderValue">
            <el-radio :value="1">
              男
            </el-radio>
            <el-radio :value="2">
              女
            </el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
    </ClReasonDialog>
  </div>
</template>
