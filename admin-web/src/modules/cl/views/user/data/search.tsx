import type { MaSearchItem } from '@mineadmin/search'

/**
 * 用户列表的搜索项（docs/07 第 3.2 节）。
 *
 * 男女共用一个页面组件，差别只有两三项（女号多等级、实名状态；男号多是否会员），
 * 所以这里收一个 `female` 参数，而不是各写一份。
 */
export default function getSearchItems(female: boolean): MaSearchItem[] {
  return [
    { label: 'ID', prop: 'id', render: 'input', renderProps: { placeholder: '精确匹配' } },
    { label: '账号', prop: 'account', render: 'input' },
    { label: '手机号', prop: 'phone', render: 'input' },
    { label: '昵称', prop: 'nickname', render: 'input' },
    {
      label: '状态',
      prop: 'status',
      render: 'select',
      renderProps: {
        clearable: true,
        options: [
          { label: '正常', value: 1 },
          { label: '已封禁', value: 2 },
          { label: '已注销', value: 3 },
        ],
      },
    },
    {
      label: '注册来源',
      prop: 'source',
      render: 'select',
      renderProps: {
        clearable: true,
        options: [
          { label: '自主注册', value: 1 },
          { label: '后台开号', value: 2 },
        ],
      },
    },
    { label: '批次号', prop: 'batch_no', render: 'input' },
    female
      ? {
          label: '等级',
          prop: 'level',
          render: 'select',
          renderProps: {
            clearable: true,
            options: [1, 2, 3, 4, 5].map(level => ({ label: `Lv${level}`, value: level })),
          },
        }
      : {
          label: '是否会员',
          prop: 'is_vip',
          render: 'select',
          renderProps: {
            clearable: true,
            options: [
              { label: '是', value: 1 },
              { label: '否', value: 0 },
            ],
          },
        },
    ...(female
      ? [{
          label: '实名状态',
          prop: 'real_name_status',
          render: 'select' as const,
          renderProps: {
            clearable: true,
            options: [
              { label: '未认证', value: 0 },
              { label: '审核中', value: 1 },
              { label: '已通过', value: 2 },
              { label: '已驳回', value: 3 },
            ],
          },
        }]
      : []),
    {
      label: '注册时间从',
      prop: 'created_from',
      render: 'date-picker',
      renderProps: { type: 'date', valueFormat: 'YYYY-MM-DD', placeholder: '开始日期' },
    },
    {
      label: '注册时间到',
      prop: 'created_to',
      render: 'date-picker',
      renderProps: { type: 'date', valueFormat: 'YYYY-MM-DD', placeholder: '结束日期' },
    },
  ]
}
