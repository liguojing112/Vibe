import type { ResponseStruct } from '#/global'

/**
 * 后台「数据看板」（docs/07 第 3.1 节）。
 *
 * 一个请求拿全部：卡片一多就会看到它们一块一块地跳出来，所以后端一次算完再返回。
 */

/** 卡片的原始值。金额一律是**分**，展示时再换算。 */
export interface ClDashboardCards {
  new_male: number
  new_female: number
  active_users: number
  paid_messages: number
  gift_coins: number
  recharge_coins: number
  recharge_amount_fen: number
  platform_income_fen: number
  pending_profile: number
  pending_realname: number
  pending_reports: number
  pending_messages: number
  pending_withdraw_count: number
  pending_withdraw_fen: number
}

/** 近 30 天的四个序列（`dates` 是横轴，其余和它一一对应）。 */
export interface ClDashboardChart {
  dates: string[]
  new_male: number[]
  new_female: number[]
  recharge_amount_fen: number[]
  platform_income_fen: number[]
}

export interface ClDashboardOverview {
  /** 卡片口径的「今天」（服务端本地日期）。 */
  date: string
  days: number
  cards: ClDashboardCards
  chart: ClDashboardChart
}

export function overview(): Promise<ResponseStruct<ClDashboardOverview>> {
  return useHttp().get('/admin/cl/dashboard/overview')
}
