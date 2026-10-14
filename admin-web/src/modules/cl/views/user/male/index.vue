<script setup lang="ts">
import UserPage from '../UserPage.vue'

defineOptions({ name: 'cl:user:male' })
</script>

<template>
  <UserPage :gender="1" />
</template>
