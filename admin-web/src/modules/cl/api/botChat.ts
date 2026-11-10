import type { PageList, ResponseStruct } from '#/global'

/**
 * 后台「AI 互聊」：机器人男号给指定女号发消息。
 *
 * 规则只描述「谁发给谁、什么节奏、上限多少」，真正的发送由服务端定时任务驱动。
 * **男号发消息不扣金币；女号的收益只做统计，不进钱包、不影响等级和提现。**
 */

export interface ClBotChatRuleRow {
  id: number
  name: string
  enabled: number
  /** 1 = 定时任务自动发；0 = 只能后台点「发送」。 */
  auto_send: number
  male_count: number
  target_count: number
  round_limit_per_pair: number
  /** 每个男号每天最多发多少条，0 = 不限。 */
  male_daily_limit: number
  interval_seconds: number
  /** 超过这么多秒没说话就算这段对话结束，换下一个男号。 */
  session_gap_seconds: number
  window_start: number
  window_end: number
  daily_amount_limit_fen: number
  points_today: number
  amount_today_fen: number
  limit_reached_count: number
  created_at?: string | null
}

export interface ClBotChatListData extends PageList<ClBotChatRuleRow> {
  /** 全局总开关：关掉后定时任务一条都不发。 */
  global_enabled: boolean
}

export interface ClBotChatMale {
  user_id: number
  nickname: string
  account: string
  avatar: string
  persona: string
  tone: string
  taboos: string
  examples: string
}

export interface ClBotChatFemale {
  user_id: number
  nickname: string
  account: string
  avatar: string
  enabled: number
  virtual_points_today: number
  amount_today_fen: number
  rounds_today: number
  stopped_reason?: string | null
  last_sent_at?: string | null
}

export interface ClBotChatRuleDetail {
  id: number
  name: string
  enabled: number
  auto_send: number
  male_user_ids: number[]
  pool_male_count: number
  window_start: number
  window_end: number
  round_limit_per_pair: number
  male_daily_limit: number
  interval_seconds: number
  session_gap_seconds: number
  daily_amount_limit_fen: number
  context_messages: number
  males: ClBotChatMale[]
  females: ClBotChatFemale[]
}

export interface ClBotChatTargetRow extends ClBotChatFemale {
  name?: string
}

export interface ClBotChatLogRow {
  id: number
  male_id: number
  male_nickname: string
  male_avatar: string
  female_id: number
  female_nickname: string
  female_avatar: string
  content: string
  role: 'male_bot' | 'female_ai'
  virtual_points: number
  status: number
  fail_reason?: string | null
  created_at?: string | null
}

/** 一个人设（男号编辑用）。 */
export interface ClBotChatPersona {
  persona: string
  tone: string
  taboos: string
  examples: string
}

export interface ClBotChatSavePayload {
  id?: number
  name: string
  enabled: number
  auto_send: number
  male_user_ids: number[]
  pool_male_count: number
  female_user_ids: number[]
  window_start: number
  window_end: number
  round_limit_per_pair: number
  male_daily_limit: number
  interval_seconds: number
  session_gap_seconds: number
  daily_amount_limit_fen: number
  context_messages: number
  male_personas: Record<number, ClBotChatPersona>
}

export interface ClBotChatUserOption {
  user_id: number
  nickname: string
  account: string
  avatar: string
  city?: string
}

export function botChatList(data: Record<string, any>): Promise<ResponseStruct<ClBotChatListData>> {
  return useHttp().get('/admin/cl/bot-chat/list', { params: data })
}

export function botChatDetail(id: number): Promise<ResponseStruct<ClBotChatRuleDetail>> {
  return useHttp().get(`/admin/cl/bot-chat/${id}`)
}

export function botChatSave(data: ClBotChatSavePayload): Promise<ResponseStruct<{ id: number }>> {
  return useHttp().post('/admin/cl/bot-chat/save', data)
}

/** 立刻发一轮。**不受时段和自动发送开关限制**，但轮数/间隔/每日上限照旧生效。 */
export function botChatSend(id: number): Promise<ResponseStruct<{ message: string, reason?: string | null }>> {
  return useHttp().post(`/admin/cl/bot-chat/${id}/send`)
}

export function botChatToggle(id: number, enabled: number): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/bot-chat/${id}/toggle`, { enabled })
}

export function botChatGlobalToggle(enabled: number): Promise<ResponseStruct<{ enabled: number }>> {
  return useHttp().post('/admin/cl/bot-chat/global-toggle', { enabled })
}

export function botChatDelete(id: number): Promise<ResponseStruct<object>> {
  return useHttp().delete(`/admin/cl/bot-chat/${id}`)
}

export function botChatTargets(id: number, data: Record<string, any>): Promise<ResponseStruct<PageList<ClBotChatTargetRow>>> {
  return useHttp().get(`/admin/cl/bot-chat/${id}/targets`, { params: data })
}

export function botChatLogs(id: number, data: Record<string, any>): Promise<ResponseStruct<PageList<ClBotChatLogRow>>> {
  return useHttp().get(`/admin/cl/bot-chat/${id}/logs`, { params: data })
}

export function botChatSearchMale(keyword: string): Promise<ResponseStruct<ClBotChatUserOption[]>> {
  return useHttp().get('/admin/cl/bot-chat/search-male', { params: { keyword, limit: 20 } })
}

export function botChatSearchFemale(keyword: string): Promise<ResponseStruct<ClBotChatUserOption[]>> {
  return useHttp().get('/admin/cl/bot-chat/search-female', { params: { keyword, limit: 20 } })
}
