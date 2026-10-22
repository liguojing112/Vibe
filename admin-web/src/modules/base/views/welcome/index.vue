<!--
 - Copyright © 畅撩交友
 -
 - 后台首页（登录后的默认落地页）。
 - 原文件是上游脚手架的演示工作台（假项目、假动态、官方外链），交付时整页替换成本项目自己的入口页。
-->
<script setup lang="ts">
import LogoSvg from '@/assets/images/logo.svg'

defineOptions({ name: 'welcome' })

const router = useRouter()
const title = import.meta.env.VITE_APP_TITLE

const entries = [
  { label: '数据看板', desc: '注册、充值、收益与平台收入概览', path: '/cl/dashboard' },
  { label: '用户管理', desc: '男/女用户、批量开号、封禁与调级', path: '/cl/user' },
  { label: '审核中心', desc: '资料审核与实名认证审核', path: '/cl/audit' },
  { label: '资金管理', desc: '充值记录、金币/积分流水、提现审核', path: '/cl/finance' },
  { label: '运营配置', desc: '价格、任务、会员、协议与敏感词', path: '/cl/operation' },
]

function open(path: string): void {
  router.push(path)
}
</script>

<template>
  <div class="mine-layout">
    <div class="flex items-center gap-x-4 bg-white p-6 dark-bg-dark-8">
      <img :src="LogoSvg" :alt="title" class="h-[64px] w-[64px]">
      <div class="flex flex-col gap-y-1">
        <span class="text-xl font-bold">{{ title }} · 管理后台</span>
        <span class="text-sm text-gray-5">从左侧菜单或下面的入口进入对应功能。</span>
      </div>
    </div>

    <div class="grid grid-cols-1 mt-4 gap-4 lg:grid-cols-3">
      <div
        v-for="item in entries"
        :key="item.path"
        class="mine-card cursor-pointer transition-all hover:shadow-md"
        @click="open(item.path)"
      >
        <div class="text-base font-bold">
          {{ item.label }}
        </div>
        <div class="mt-2 text-sm text-gray-5">
          {{ item.desc }}
        </div>
      </div>
    </div>
  </div>
</template>
