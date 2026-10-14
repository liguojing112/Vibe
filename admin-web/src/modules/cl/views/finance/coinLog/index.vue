<script setup lang="ts">
import WalletLogPage from '../WalletLogPage.vue'

defineOptions({ name: 'cl:finance:coin-log' })
</script>

<template>
  <WalletLogPage :points="false" />
</template>
