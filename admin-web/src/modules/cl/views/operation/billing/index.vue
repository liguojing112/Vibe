<script setup lang="ts">
import ConfigGroupPage from '../ConfigGroupPage.vue'

defineOptions({ name: 'cl:operation:billing' })
</script>

<template>
  <ConfigGroupPage
    :groups="['billing', 'share', 'chat', 'vip', 'withdraw']"
    title="计费与分成"
    subtitle="免费条数、汇率、分成比例、提现规则都在这儿。提现那组文档没给单独页面，所以并到这一页。"
    show-example
  />
</template>
