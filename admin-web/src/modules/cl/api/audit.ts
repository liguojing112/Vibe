import type { PageList, ResponseStruct } from '#/global'

/**
 * 后台审核中心（docs/07 第 3.4、3.5 节）。
 *
 * 两个模块各一套：资料审核读 `cl_profile_audit`，实名审核读 `cl_real_name`。
 */

/** 资料审核一行。相册那条的 old/new 会被后端换成 `{id, url}` 数组。 */
export interface ClProfileAuditRow {
  id: number
  user_id: number
  field: string
  field_label: string
  old_value?: string | null
  new_value?: string | null
  old_photos?: Array<{ id: number, url: string }>
  new_photos?: Array<{ id: number, url: string }>
  status: number
  reject_reason?: string | null
  created_at?: string
  user_nickname: string
  user_avatar?: string | null
  user_gender?: number | null
}

export interface ClRealNameRow {
  id: number
  user_id: number
  real_name: string
  /** 永远只有打码号，明文要走单独那个接口（并且会留流水）。 */
  id_card_mask: string
  status: number
  reject_reason?: string | null
  created_at?: string
  /** 除了他还有几个号用了同一张身份证（只提示不拦截）。 */
  duplicate_count: number
  user_nickname: string
  user_avatar?: string | null
  user_gender?: number | null
}

// ---------- 资料审核 ----------

export function profileList(data: { status: number, page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClProfileAuditRow>>> {
  return useHttp().get('/admin/cl/audit/profile/list', { params: data })
}

export function profileApprove(id: number): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/audit/profile/${id}/approve`)
}

export function profileReject(id: number, reason: string): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/audit/profile/${id}/reject`, { reason })
}

/** 批量通过/驳回。返回里带 `failed`（哪些没成），一条失败不影响其它条。 */
export function profileBatch(data: { ids: number[], action: 'approve' | 'reject', reason?: string }): Promise<ResponseStruct<{ success: number, failed: number[] }>> {
  return useHttp().post('/admin/cl/audit/profile/batch', data)
}

// ---------- 实名审核 ----------

export function realNameList(data: { status: number, page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClRealNameRow>>> {
  return useHttp().get('/admin/cl/audit/realname/list', { params: data })
}

export function realNameApprove(id: number): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/audit/realname/${id}/approve`)
}

export function realNameReject(id: number, reason: string): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/audit/realname/${id}/reject`, { reason })
}

/** 看身份证明文。**每看一次服务端都会记一条流水**（权限码也和普通审核分开）。 */
export function realNamePlain(id: number): Promise<ResponseStruct<{ id: number, user_id: number, real_name: string, id_card: string }>> {
  return useHttp().get(`/admin/cl/audit/realname/${id}/plain`)
}
