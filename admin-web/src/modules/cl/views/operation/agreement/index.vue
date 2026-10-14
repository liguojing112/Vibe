<script setup lang="ts">
import ConfigGroupPage from '../ConfigGroupPage.vue'

defineOptions({ name: 'cl:operation:agreement' })
</script>

<template>
  <ConfigGroupPage
    :groups="['agreement', 'compliance']"
    title="协议与声明"
    subtitle="用户协议、隐私政策、合规声明。合规声明**不能存空**（App 上协议页必须带付费声明，铁律 13）。"
  />
</template>
