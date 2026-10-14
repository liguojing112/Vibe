<script setup lang="ts">
import UserPage from '../UserPage.vue'

defineOptions({ name: 'cl:user:female' })
</script>

<template>
  <UserPage :gender="2" />
</template>
