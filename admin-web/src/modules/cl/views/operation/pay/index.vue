<script setup lang="ts">
import ConfigGroupPage from '../ConfigGroupPage.vue'

defineOptions({ name: 'cl:operation:pay' })
</script>

<template>
  <ConfigGroupPage
    :groups="['pay']"
    title="在线支付"
    subtitle="商户 ID 和密钥由客户自己填（只存服务端，密钥留空表示不修改）。填好后打开开关，App 充值页才会出现「立即支付」。"
  />
</template>
