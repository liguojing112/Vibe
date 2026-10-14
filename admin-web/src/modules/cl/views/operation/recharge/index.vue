<script setup lang="ts">
import ConfigGroupPage from '../ConfigGroupPage.vue'

defineOptions({ name: 'cl:operation:recharge' })
</script>

<template>
  <ConfigGroupPage
    :groups="['recharge']"
    title="充值档位与客服"
    subtitle="档位是 JSON 数组（金币数），客服信息会显示在 App 的充值页（P22）。"
  />
</template>
