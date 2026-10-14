import type { MaProTableColumns } from '@mineadmin/pro-table'
import type { ClUserRow } from '../../../api/user'
import ClAmount from '../../../components/ClAmount.vue'
import ClStatusTag from '../../../components/ClStatusTag.vue'
import ClUserCell from '../../../components/ClUserCell.vue'
import { realNameToken, sourceText, userStatusToken } from '../../../status'
import hasAuth from '@/utils/permission/hasAuth.ts'

/** 页面把这些动作注入进来，列定义本身不碰接口。 */
export interface UserTableActions {
  openDetail: (row: ClUserRow) => void
  openEdit: (row: ClUserRow) => void
  openBan: (row: ClUserRow) => void
  openUnban: (row: ClUserRow) => void
  openLevel: (row: ClUserRow) => void
  openVip: (row: ClUserRow) => void
  openGender: (row: ClUserRow) => void
  resetPassword: (row: ClUserRow) => void
  /** 超管的兜底手段（docs/06 第 8 节）：mode 决定扣金币还是调积分。 */
  openAdjust: (row: ClUserRow, mode: 'coin' | 'point') => void
}

export default function getTableColumns(female: boolean, actions: UserTableActions): MaProTableColumns[] {
  const common: MaProTableColumns[] = [
    {
      label: '用户',
      prop: 'nickname',
      width: '200px',
      cellRender: ({ row }) => <ClUserCell user={row} clickable={false} />,
    },
    { label: '账号', prop: 'account' },
    { label: '手机号', prop: 'phone' },
    { label: '城市', prop: 'city' },
    {
      label: '状态',
      prop: 'status',
      width: '100px',
      cellRender: ({ row }) => <ClStatusTag status={userStatusToken(row.status)} />,
    },
    {
      label: '注册来源',
      prop: 'source',
      width: '110px',
      cellRender: ({ row }) => <span>{sourceText(row.source)}</span>,
    },
    { label: '批次号', prop: 'batch_no' },
    { label: '注册时间', prop: 'created_at', width: '170px' },
    { label: '最后活跃', prop: 'last_active_at', width: '170px' },
  ]

  const genderColumns: MaProTableColumns[] = female
    ? [
        {
          label: '等级',
          prop: 'level',
          width: '90px',
          cellRender: ({ row }) => (
            <span>
              Lv
              {row.level}
              {row.level_locked === 1 ? <span class="ml-1 text-xs text-gray-400">(锁)</span> : null}
            </span>
          ),
        },
        {
          label: '积分余额',
          prop: 'point_balance',
          width: '130px',
          cellRender: ({ row }) => <ClAmount value={row.point_balance ?? 0} type="point" />,
        },
        {
          label: '冻结积分',
          prop: 'point_frozen',
          width: '130px',
          cellRender: ({ row }) => <ClAmount value={row.point_frozen ?? 0} type="point" />,
        },
        {
          label: '累计收益',
          prop: 'total_earned_points',
          width: '130px',
          cellRender: ({ row }) => <ClAmount value={row.total_earned_points ?? 0} type="point" />,
        },
        {
          label: '实名状态',
          prop: 'real_name_status',
          width: '110px',
          cellRender: ({ row }) => <ClStatusTag status={realNameToken(row.real_name_status)} />,
        },
      ]
    : [
        {
          label: '金币余额',
          prop: 'coin_balance',
          width: '130px',
          cellRender: ({ row }) => <ClAmount value={row.coin_balance ?? 0} type="coin" />,
        },
        {
          label: '累计充值',
          prop: 'coin_total_recharge',
          width: '130px',
          cellRender: ({ row }) => <ClAmount value={row.coin_total_recharge ?? 0} type="coin" />,
        },
        { label: '会员到期', prop: 'vip_expire_at', width: '170px' },
      ]

  return [
    { type: 'selection', label: '选择' },
    ...common,
    ...genderColumns,
    {
      type: 'operation',
      label: '操作',
      width: '150px',
      operationConfigure: {
        actions: [
          {
            name: 'detail',
            text: '查看',
            icon: 'ri:eye-line',
            show: () => hasAuth('cl:user:detail'),
            onClick: ({ row }) => actions.openDetail(row),
          },
          {
            name: 'edit',
            text: '编辑资料',
            icon: 'ri:edit-line',
            show: () => hasAuth('cl:user:update'),
            onClick: ({ row }) => actions.openEdit(row),
          },
          {
            name: 'resetPassword',
            text: '重置密码',
            icon: 'ri:lock-password-line',
            show: () => hasAuth('cl:user:reset-password'),
            onClick: ({ row }) => actions.resetPassword(row),
          },
          {
            name: 'ban',
            text: '封禁',
            icon: 'ri:forbid-2-line',
            show: ({ row }) => hasAuth('cl:user:ban') && row.status !== 2,
            onClick: ({ row }) => actions.openBan(row),
          },
          {
            name: 'unban',
            text: '解封',
            icon: 'ri:lock-unlock-line',
            show: ({ row }) => hasAuth('cl:user:ban') && row.status === 2,
            onClick: ({ row }) => actions.openUnban(row),
          },
          {
            name: 'level',
            text: '调整等级',
            icon: 'ri:bar-chart-2-line',
            show: () => female && hasAuth('cl:user:level'),
            onClick: ({ row }) => actions.openLevel(row),
          },
          {
            name: 'vip',
            text: '开通会员',
            icon: 'ri:vip-crown-line',
            show: () => !female && hasAuth('cl:user:vip-grant'),
            onClick: ({ row }) => actions.openVip(row),
          },
          {
            name: 'coinDeduct',
            text: '扣金币',
            icon: 'ri:coins-line',
            show: () => !female && hasAuth('cl:finance:coin-deduct'),
            onClick: ({ row }) => actions.openAdjust(row, 'coin'),
          },
          {
            name: 'pointAdjust',
            text: '调整积分',
            icon: 'ri:star-smile-line',
            show: () => female && hasAuth('cl:finance:point-adjust'),
            onClick: ({ row }) => actions.openAdjust(row, 'point'),
          },
          {
            name: 'gender',
            text: '修改性别',
            icon: 'ri:genderless-line',
            show: () => hasAuth('cl:user:gender'),
            onClick: ({ row }) => actions.openGender(row),
          },
        ],
      },
    },
  ]
}
