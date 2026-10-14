/**
 * 「畅撩交友」后台的公共类型。
 *
 * 各页面的列表 VO 就地定义在自己的 `api/*.ts` 里（和 base 模块一个写法），
 * 这里只放**多个页面共用**的那几个。
 */

/** 用户格子里最少要知道的东西：头像、昵称、ID、性别。 */
export interface ClUserLike {
  id?: number
  nickname?: string
  avatar?: string | null
  /** 1 男 / 2 女（和 `cl_user.gender` 一致）。 */
  gender?: number | null
}

/** 一条运营配置（对应 `cl_config` 一行）。 */
export interface ClConfigItem {
  key: string
  value: string
  /** int / string / bool / json —— 决定表单渲染成什么控件。 */
  value_type?: string
  group?: string
  label?: string
  remark?: string
}
