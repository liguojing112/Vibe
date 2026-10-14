/**
 * 「畅撩交友」后台的状态显示映射。
 *
 * 抽出来的原因：同一个状态在列表页和详情抽屉里都要显示，两边各写一份的话，
 * 加一个状态就会有一边忘了改（这次走查里列表已经翻译成中文、抽屉还在显示 `2`）。
 */

/** `cl_user.status` → `ClStatusTag` 认的语义 token。 */
export function userStatusToken(status: number): string {
  return status === 2 ? 'BANNED' : status === 3 ? 'CANCELLED' : 'NORMAL'
}

/** `cl_user.real_name_status` → 语义 token。 */
export function realNameToken(status: number): string {
  return ['NONE', 'AUDITING', 'PASSED', 'REJECTED'][status] ?? 'NONE'
}

/** `cl_user.source` → 中文。 */
export function sourceText(source: number): string {
  return source === 2 ? '后台开号' : '自主注册'
}
