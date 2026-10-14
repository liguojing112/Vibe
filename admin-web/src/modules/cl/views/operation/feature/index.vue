<script setup lang="ts">
import ConfigGroupPage from '../ConfigGroupPage.vue'

defineOptions({ name: 'cl:operation:feature' })
</script>

<template>
  <ConfigGroupPage
    :groups="['feature']"
    title="功能开关"
    subtitle="语音/视频开关打开后 App 只显示入口，点拨打会提示「功能暂未开放」——本轮不做音视频。"
  />
</template>
