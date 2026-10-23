import type { PageList, ResponseStruct } from '#/global'

/**
 * 后台「内容管理 / 朋友圈」。
 *
 * 动态是先发后审的：这里能看到全部（含已隐藏），隐藏只是让 App 端看不到。
 */

export interface ClMomentRow {
  id: number
  user_id: number
  nickname: string
  content: string
  images: string[]
  like_count: number
  comment_count: number
  /** 1 正常 / 2 已隐藏 */
  status: number
  created_at?: string
}

export interface ClMomentCommentRow {
  id: number
  user_id: number
  nickname: string
  content: string
  created_at?: string
}

export function momentList(data: { user_id?: number | string, status?: number, page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClMomentRow>>> {
  return useHttp().get('/admin/cl/moment/list', { params: data })
}

/** 1 = 恢复、2 = 隐藏。 */
export function setMomentStatus(id: number, status: number): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/moment/${id}/status`, { status })
}

export function deleteMoment(id: number): Promise<ResponseStruct<object>> {
  return useHttp().delete(`/admin/cl/moment/${id}`)
}

export function momentComments(id: number, data: { page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClMomentCommentRow>>> {
  return useHttp().get(`/admin/cl/moment/${id}/comment`, { params: data })
}
