import type { PageList, ResponseStruct } from '#/global'
import type { ClUserLike } from '../types'

/**
 * 后台用户管理（docs/05 第 6 节 `/admin/cl/user/*`、docs/07 第 3.2 节）。
 *
 * 男用户和女用户**是同一套接口**，靠 `gender` 区分（页面组件也是同一个）。
 */

/** 列表一行的形状（接口直接回的就是这些字段，后端已经 join 好钱包余额）。 */
export interface ClUserRow extends ClUserLike {
  account: string
  phone?: string | null
  city?: string
  status: number
  source: number
  batch_no?: string | null
  level: number
  level_locked: number
  total_earned_points: number
  vip_expire_at?: string | null
  real_name_status: number
  created_at?: string
  last_active_at?: string | null
  coin_balance?: number | null
  coin_total_recharge?: number | null
  point_balance?: number | null
  point_frozen?: number | null
}

/** 抽屉要的东西比列表多：资料、相册、钱包、等级、实名、会员、封禁记录、最近流水。 */
export interface ClUserDetailVo {
  user: {
    id: number
    account: string
    phone: string
    nickname: string
    avatar: string
    gender: number
    city: string
    birthday?: string | null
    height?: number | null
    job: string
    education: string
    bio: string
    status: number
    source: number
    batchNo: string
    createdAt?: string | null
    lastActiveAt?: string | null
  }
  photos: string[]
  coin: { balance: number, totalRecharge: number }
  point: { balance: number, frozen: number, totalEarned: number }
  level: { level: number, locked: boolean }
  realName: { status: number, realName?: string | null, idCardMask?: string | null }
  vip: { expireAt?: string | null }
  bans: Array<{ reason: string, bannedAt?: string | null, bannedUntil?: string | null, operator?: string }>
  /** 金币和积分流水合在一起，按时间倒序，最多 20 条。 */
  logs: Array<{ id: number, type: string, title: string, change: number, created_at?: string | null }>
}

export interface ClUserSearch {
  gender: number
  id?: number
  account?: string
  phone?: string
  nickname?: string
  status?: number
  source?: number
  batch_no?: string
  level?: number
  real_name_status?: number
  is_vip?: number
  created_from?: string
  created_to?: string
  page?: number
  page_size?: number
}

export function page(data: ClUserSearch): Promise<ResponseStruct<PageList<ClUserRow>>> {
  return useHttp().get('/admin/cl/user/list', { params: data })
}

export function detail(id: number): Promise<ResponseStruct<ClUserDetailVo>> {
  return useHttp().get(`/admin/cl/user/${id}`)
}

export function updateProfile(id: number, data: { nickname?: string, avatar?: string, bio?: string }): Promise<ResponseStruct<object>> {
  return useHttp().put(`/admin/cl/user/${id}`, data)
}

/** 重置密码。返回的明文只在这一次响应里出现（docs/07 第 3.2 节）。 */
export function resetPassword(id: number): Promise<ResponseStruct<{ password: string }>> {
  return useHttp().post(`/admin/cl/user/${id}/reset-password`)
}

export function ban(id: number, reason: string, days: number): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/user/${id}/ban`, { reason, days })
}

export function unban(id: number, reason: string): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/user/${id}/unban`, { reason })
}

export function setLevel(id: number, level: number, locked: boolean, reason: string): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/user/${id}/level`, { level, locked, reason })
}

export function grantVip(id: number, data: { plan_id?: number, days?: number, remark?: string }): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/user/${id}/vip-grant`, data)
}

export function changeGender(id: number, gender: number, reason: string): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/user/${id}/gender`, { gender, reason })
}

/**
 * 导出的下载地址要**带上当前搜索条件**，所以这里把同样的参数（去掉分页）拼成 query。
 *
 * 走浏览器直接下载而不是 `useHttp()`：那个会把响应当 JSON 解，二进制文件会被解坏。
 * 后端也要 token，所以先取一次 blob 再触发下载。
 */
export async function exportExcel(data: ClUserSearch): Promise<void> {
  const params = new URLSearchParams()
  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '' && key !== 'page' && key !== 'page_size') {
      params.append(key, String(value))
    }
  })

  const res = await useHttp().request<Blob>({
    url: `/admin/cl/user/export?${params.toString()}`,
    method: 'get',
    responseType: 'blob',
  })

  const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${data.gender === 2 ? '女' : '男'}用户-${Date.now()}.xlsx`
  link.click()
  URL.revokeObjectURL(url)
}

// ---------- 批量开号（docs/07 第 3.3 节） ----------

export interface ClBatchRow {
  id: number
  batch_no: string
  gender: number
  count: number
  account_prefix: string
  operator_name: string
  remark: string
  exported: boolean
  created_at?: string
}

/** 开号的结果。**`accounts[].password` 只在这个响应里出现一次**，服务端不存。 */
export interface ClBatchCreateResult {
  batch_no: string
  gender: number
  count: number
  accounts: Array<{ account: string, nickname: string, password: string }>
}

export function batchCreate(data: {
  gender: number
  count: number
  prefix?: string
  remark?: string
}): Promise<ResponseStruct<ClBatchCreateResult>> {
  return useHttp().post('/admin/cl/user/batch-create', data)
}

export function batchList(data: { page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClBatchRow>>> {
  return useHttp().get('/admin/cl/user/batch/list', { params: data })
}

/** 标记这批明文已经导出过了（批次列表的「是否已导出」）。服务端不会再给一次密码。 */
export function markBatchExported(batchNo: string): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/user/batch/${batchNo}/export`)
}
