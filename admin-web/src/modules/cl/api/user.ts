import type { ResponseStruct } from '#/global'

/**
 * 用户详情（docs/05 第 6 节后台接口、docs/07 第 3.2 节）。
 *
 * 抽屉要的东西比列表多：资料、相册、钱包、等级、实名、会员、封禁记录、最近流水。
 */
export interface ClUserDetailVo {
  user: {
    id: number
    account?: string
    phone?: string
    nickname: string
    avatar?: string | null
    gender: number
    city?: string
    birthday?: string | null
    height?: number | null
    job?: string | null
    education?: string | null
    bio?: string | null
    status: number
    source?: string | null
    batchNo?: string | null
    createdAt?: string
    lastActiveAt?: string | null
  }
  photos: string[]
  coin: { balance: number }
  point: { balance: number, frozen: number, totalEarned: number }
  level: { level: number, locked: boolean }
  realName: { status: number, realName?: string | null, idCardMask?: string | null }
  vip: { expireAt?: string | null }
  bans: Array<{ reason: string, bannedAt?: string | null, bannedUntil?: string | null }>
  /** 金币与积分流水，按时间倒序，最多 20 条。 */
  logs: Array<{
    id: number
    type: string
    title: string
    change: number
    createdAt: string
  }>
}

export function detail(id: number): Promise<ResponseStruct<ClUserDetailVo>> {
  return useHttp().get(`/admin/cl/user/${id}`)
}
