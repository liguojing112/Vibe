<script setup lang="ts">
import WalletLogPage from '../WalletLogPage.vue'

defineOptions({ name: 'cl:finance:point-log' })
</script>

<template>
  <WalletLogPage :points="true" />
</template>
