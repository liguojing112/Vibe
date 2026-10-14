import type { PageList, ResponseStruct } from '#/global'

/**
 * 后台「聊天管理」（docs/07 第 3.6~3.9 节）。
 *
 * 四个页面：会话记录、待审消息、敏感词库、举报处理。
 * 举报那两条也在本文件里 —— 它和前面三个是同一批事，分开一个文件反而要多跳一次。
 */

/** 会话列表一行。 */
export interface ClConversationRow {
  id: number
  male_id: number
  female_id: number
  male_nickname: string
  male_avatar?: string | null
  female_nickname: string
  female_avatar?: string | null
  /** 男方已发条数（`06` 第 3.2 节第 2 步要用的那个数） */
  male_sent_count: number
  last_message_at?: string | null
  created_at?: string | null
}

/** 后台看到的一条消息（带价格和分成，客户端那边看不到这些）。 */
export interface ClMessageRow {
  id: number
  conversation_id: number
  sender_id: number
  receiver_id: number
  sender_nickname: string
  sender_avatar?: string | null
  sender_gender?: number | null
  receiver_nickname: string
  receiver_avatar?: string | null
  receiver_gender?: number | null
  /** 1 文字 2 图片 3 礼物 */
  type: number
  content: string
  price_coins: number
  free_reason?: string | null
  receiver_points: number
  sensitive_hit: number
  /** 0 无需审 1 待审 2 正常 3 违规 */
  review_status: number
  created_at?: string | null
}

export interface ClSensitiveWordRow {
  id: number
  word: string
  enabled: boolean
  created_at?: string | null
}

export interface ClReportRow {
  id: number
  reporter_id: number
  target_id: number
  reporter_nickname: string
  reporter_avatar?: string | null
  reporter_gender?: number | null
  target_nickname: string
  target_avatar?: string | null
  target_gender?: number | null
  reason: string
  reason_label: string
  content: string
  images: string[]
  message_id?: number | null
  status: number
  status_text: string
  handle_result: string
  handled_at?: string | null
  created_at?: string | null
}

// ---------- 会话记录 ----------

export function conversationList(data: {
  user_id?: number
  created_from?: string
  created_to?: string
  page?: number
  page_size?: number
}): Promise<ResponseStruct<PageList<ClConversationRow>>> {
  return useHttp().get('/admin/cl/chat/conversation/list', { params: data })
}

export function conversationMessages(id: number, data: { page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClMessageRow> & { conversation_id: number, male_sent_count: number }>> {
  return useHttp().get(`/admin/cl/chat/conversation/${id}/messages`, { params: data })
}

// ---------- 待审消息 ----------

export function pendingMessages(data: { page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClMessageRow>>> {
  return useHttp().get('/admin/cl/chat/message/pending', { params: data })
}

export function messageContext(id: number): Promise<ResponseStruct<ClMessageRow[]>> {
  return useHttp().get(`/admin/cl/chat/message/${id}/context`)
}

/** 标记消息：2 = 正常，3 = 违规；违规时带上 `ban_days` + `ban_reason` 就顺手封了发送方。 */
export function reviewMessages(data: {
  ids: number[]
  status: number
  ban_days?: number
  ban_reason?: string
}): Promise<ResponseStruct<{ updated: number, banned: boolean }>> {
  return useHttp().post('/admin/cl/chat/message/review', data)
}

// ---------- 敏感词库 ----------

export function sensitiveList(data: { keyword?: string, page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClSensitiveWordRow>>> {
  return useHttp().get('/admin/cl/chat/sensitive/list', { params: data })
}

export function sensitiveCreate(word: string): Promise<ResponseStruct<object>> {
  return useHttp().post('/admin/cl/chat/sensitive', { word })
}

export function sensitiveUpdate(id: number, word: string, enabled: boolean): Promise<ResponseStruct<object>> {
  return useHttp().put(`/admin/cl/chat/sensitive/${id}`, { word, enabled })
}

export function sensitiveDelete(ids: number[]): Promise<ResponseStruct<{ deleted: number }>> {
  return useHttp().post('/admin/cl/chat/sensitive/delete', { ids })
}

/** 批量导入：**一行一个词**，返回导进去几个、跳过哪些（重复/太长）。 */
export function sensitiveImport(text: string): Promise<ResponseStruct<{ added: number, skipped: string[] }>> {
  return useHttp().post('/admin/cl/chat/sensitive/import', { text })
}

// ---------- 举报处理 ----------

export function reportList(data: { status: number, page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClReportRow>>> {
  return useHttp().get('/admin/cl/report/list', { params: data })
}

/** 判定结果。违规时带上 `ban_reason` 就顺手封了被举报人（和用户管理同一个封禁入口）。 */
export function reportHandle(id: number, data: {
  violation: boolean
  remark?: string
  ban_days?: number
  ban_reason?: string
}): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/report/${id}/handle`, data)
}
