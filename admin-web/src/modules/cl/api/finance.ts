import type { PageList, ResponseStruct } from '#/global'

/**
 * 后台「资金管理」（docs/07 第 3.10~3.14 节）。
 *
 * 六个页面：后台充值、充值记录、金币流水、积分流水、提现审核、平台收入。
 */

export interface ClRechargeRow {
  id: number
  order_no: string
  user_id: number
  user_nickname: string
  user_avatar?: string | null
  coins: number
  /** 实收金额（分）；0 = 赠送 */
  amount_fen: number
  pay_channel: string
  remark: string
  operator_name: string
  created_at?: string | null
}

export interface ClWalletLogRow {
  id: number
  user_id: number
  user_nickname: string
  user_avatar?: string | null
  type: string
  change: number
  balance_after: number
  frozen_change?: number | null
  frozen_after?: number | null
  title: string
  remark: string
  ref_type: string
  ref_id?: number | null
  created_at?: string | null
}

export interface ClWithdrawRow {
  id: number
  order_no: string
  user_id: number
  user_nickname: string
  user_avatar?: string | null
  level: number
  real_name: string
  amount_fen: number
  fee_fen: number
  actual_fen: number
  points: number
  account_type: string
  account_type_text: string
  account_no: string
  account_name: string
  bank_name: string
  status: number
  status_text: string
  reject_reason: string
  pay_proof: string
  created_at?: string | null
}

export interface ClIncomeRow {
  date: string
  message_coins: number
  gift_coins: number
  vip_coins: number
  coins: number
  points: number
  /** 平台毛收入（分）：金币 ÷ 汇率 − 积分 ÷ 汇率 */
  income_fen: number
}

// ---------- 后台充值 ----------

export function recharge(data: {
  user_id: number
  coins: number
  amount_fen?: number
  pay_channel: string
  remark?: string
}): Promise<ResponseStruct<{ order_no: string, coins: number, balance: number }>> {
  return useHttp().post('/admin/cl/finance/recharge', data)
}

/** 汇率（充值的参考金额、平台收入的换算都用它）。 */
export function rate(): Promise<ResponseStruct<{ coin_per_yuan: number, point_per_yuan: number }>> {
  return useHttp().get('/admin/cl/finance/rate')
}

export function rechargeList(data: Record<string, any>): Promise<ResponseStruct<PageList<ClRechargeRow> & { coins: number, amount_fen: number }>> {
  return useHttp().get('/admin/cl/finance/recharge/list', { params: data })
}

// ---------- 流水 ----------

export function coinLogs(data: Record<string, any>): Promise<ResponseStruct<PageList<ClWalletLogRow>>> {
  return useHttp().get('/admin/cl/finance/coin-log', { params: data })
}

export function pointLogs(data: Record<string, any>): Promise<ResponseStruct<PageList<ClWalletLogRow>>> {
  return useHttp().get('/admin/cl/finance/point-log', { params: data })
}

/** 扣金币（超管专有）。 */
export function coinDeduct(data: { user_id: number, amount: number, reason: string }): Promise<ResponseStruct<object>> {
  return useHttp().post('/admin/cl/finance/coin-deduct', data)
}

/** 调整积分（超管专有）：正数补发、负数扣减。 */
export function pointAdjust(data: { user_id: number, amount: number, reason: string }): Promise<ResponseStruct<object>> {
  return useHttp().post('/admin/cl/finance/point-adjust', data)
}

// ---------- 提现审核 ----------

export function withdrawList(data: Record<string, any>): Promise<ResponseStruct<PageList<ClWithdrawRow> & { amount_fen: number, actual_fen: number }>> {
  return useHttp().get('/admin/cl/withdraw/list', { params: data })
}

export function withdrawApprove(id: number): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/withdraw/${id}/approve`)
}

export function withdrawReject(id: number, reason: string): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/withdraw/${id}/reject`, { reason })
}

/** 标记已打款：必须填打款流水号或备注（docs/06 第 9.2 节）。 */
export function withdrawPaid(id: number, payProof: string): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/withdraw/${id}/paid`, { pay_proof: payProof })
}

// ---------- 平台收入 ----------

export function income(data: { from?: string, to?: string }): Promise<ResponseStruct<{
  list: ClIncomeRow[]
  total_coins: number
  total_points: number
  total_income_fen: number
}>> {
  return useHttp().get('/admin/cl/finance/income', { params: data })
}

/** 分 → 「¥12.34」。 */
export function fenText(fen: number): string {
  const negative = fen < 0
  const abs = Math.abs(fen)

  return `${negative ? '-' : ''}¥${Math.floor(abs / 100)}.${String(abs % 100).padStart(2, '0')}`
}
