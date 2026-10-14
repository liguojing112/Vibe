import type { PageList, ResponseStruct } from '#/global'

/**
 * 后台「运营配置」（docs/07 第 3.15~3.23 节）。
 *
 * 十一页分三类：
 * - **配置分组**（计费/充值档位/协议/功能开关）：读写 `cl_config` 的某个 group；
 * - **目录表**（礼物/会员套餐/任务/标签/城市）：标准增删改查；
 * - **只读**（会员开通记录）与**动作**（系统通知）。
 */

export interface ClConfigItem {
  key: string
  value: string
  value_type: string
  label: string
  remark: string
}

export interface ClLevelRow {
  level: number
  name: string
  threshold_points: number
  message_price: number
  message_share_rate: number
  voice_price: number
  video_price: number
}

/** 目录表一行（礼物/套餐/标签/城市共用；任务字段更多）。 */
export interface ClCatalogRow {
  id: number
  name: string
  icon?: string
  description?: string
  sort: number
  enabled: number
  price_coins?: number
  days?: number
  type?: string
  target?: number
  reward?: number
  gender?: number
  period?: number
}

export interface ClVipOrderRow {
  id: number
  order_no: string
  user_id: number
  user_nickname: string
  user_avatar?: string | null
  plan_name: string
  days: number
  price_coins: number
  source: number
  expire_before?: string | null
  expire_after?: string | null
  operator_name: string
  remark: string
  created_at?: string | null
}

// ---------- 配置分组 ----------

export function configGroups(): Promise<ResponseStruct<{ all: Record<string, string>, billing: string[] }>> {
  return useHttp().get('/admin/cl/config/groups')
}

export function configGroup(group: string): Promise<ResponseStruct<{ group: string, items: ClConfigItem[] }>> {
  return useHttp().get(`/admin/cl/config/${group}`)
}

/** 保存一组配置：`values` 是 `key => 值`（**key 带点，服务端按 key 校验**）。 */
export function saveConfigGroup(group: string, values: Record<string, string>): Promise<ResponseStruct<object>> {
  return useHttp().put(`/admin/cl/config/${group}`, { values })
}

export function levels(): Promise<ResponseStruct<ClLevelRow[]>> {
  return useHttp().get('/admin/cl/config/levels')
}

export function saveLevels(items: ClLevelRow[]): Promise<ResponseStruct<object>> {
  return useHttp().put('/admin/cl/config/levels', { items })
}

// ---------- 目录表 ----------

type CatalogName = 'gift' | 'vip-plan' | 'task' | 'tag' | 'city'

export function catalogList<T = ClCatalogRow>(name: CatalogName): Promise<ResponseStruct<T[]>> {
  return useHttp().get(`/admin/cl/${name}/list`)
}

export function catalogSave(name: CatalogName, data: Record<string, any>): Promise<ResponseStruct<object>> {
  return useHttp().post(`/admin/cl/${name}/save`, data)
}

export function catalogDelete(name: CatalogName, ids: number[]): Promise<ResponseStruct<{ deleted: number }>> {
  return useHttp().post(`/admin/cl/${name}/delete`, { ids })
}

// ---------- 会员开通记录 / 系统通知 ----------

export function vipOrders(data: { user_id?: number, source?: number, page?: number, page_size?: number }): Promise<ResponseStruct<PageList<ClVipOrderRow>>> {
  return useHttp().get('/admin/cl/vip-order/list', { params: data })
}

export function publishNotice(data: {
  title: string
  content?: string
  user_ids?: number[]
}): Promise<ResponseStruct<{ mode: string, sent?: number, missing?: number[] }>> {
  return useHttp().post('/admin/cl/notice/publish', data)
}

export function publishedNotices(data: { page?: number, page_size?: number }): Promise<ResponseStruct<PageList<{ id: number, user_id: number, title: string, content: string, biz_type: string, created_at?: string }>>> {
  return useHttp().get('/admin/cl/notice/list', { params: data })
}
