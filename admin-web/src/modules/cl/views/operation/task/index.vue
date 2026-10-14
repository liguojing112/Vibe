<script setup lang="ts">
import CatalogPage from '../CatalogPage.vue'

defineOptions({ name: 'cl:operation:task' })
</script>

<template>
  <CatalogPage
    entity="task"
    title="每日任务"
    subtitle="类型只能是签到 / 完善照片 / 完善简介 / 发消息这四种（06 第 7 节）。男女各配一组，奖励男发金币、女发积分。"
    :fields="[
      { key: 'type',
        label: '类型',
        type: 'select',
        width: 150,
        options: [
          { label: '每日签到', value: 'SIGN_IN' },
          { label: '完善照片', value: 'PROFILE_PHOTO' },
          { label: '完善简介', value: 'PROFILE_BIO' },
          { label: '发消息', value: 'SEND_MESSAGE' },
        ] },
      { key: 'target', label: '目标次数', type: 'number', min: 1 },
      { key: 'reward', label: '奖励', type: 'number', min: 0 },
      { key: 'gender',
        label: '适用性别',
        type: 'select',
        width: 110,
        options: [
          { label: '男号', value: 1 },
          { label: '女号', value: 2 },
        ] },
      { key: 'period',
        label: '周期',
        type: 'select',
        width: 100,
        options: [
          { label: '每天', value: 1 },
          { label: '一次性', value: 2 },
        ] },
    ]"
  />
</template>
