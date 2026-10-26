<script setup lang="ts">
import ConfigGroupPage from '../ConfigGroupPage.vue'

defineOptions({ name: 'cl:operation:recharge' })
</script>

<template>
  <ConfigGroupPage
    :groups="['recharge']"
    title="充值档位与客服"
    subtitle="档位按金币和价格维护，客服信息会显示在 App 的充值页（P22）。"
  />
</template>
