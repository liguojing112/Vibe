import type { PageList, ResponseStruct } from '#/global'

/**
 * 后台「AI 代聊」——只对女用户开放。
 *
 * 一个人设对应一个女号：性格、说话风格、忌口、活跃时段、主动程度。
 */

export interface ClAiRow {
  user_id: number
  nickname: string
  account: string
  avatar: string
  city: string
  age?: number | null
  configured: boolean
  enabled: number
  proactive: number
  active_start: number
  active_end: number
  daily_limit: number
  replied_today: number
  total_tokens: number
}

export interface ClAiDetail extends Omit<ClAiRow, 'configured' | 'total_tokens'> {
  job: string
  bio: string
  persona: string
  tone: string
  taboos: string
  prompt_tokens: number
  completion_tokens: number
}

export function aiList(data: { keyword?: string, enabled?: number | string, page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClAiRow>>> {
  return useHttp().get('/admin/cl/ai/list', { params: data })
}

export function aiDetail(id: number): Promise<ResponseStruct<ClAiDetail>> {
  return useHttp().get(`/admin/cl/ai/${id}`)
}

export function aiSave(id: number, data: Partial<ClAiDetail>): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/ai/${id}/save`, data)
}

export function aiToggle(id: number, enabled: number): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/ai/${id}/toggle`, { enabled })
}
