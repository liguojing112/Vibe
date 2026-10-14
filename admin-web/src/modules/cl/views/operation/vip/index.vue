<script setup lang="ts">
import CatalogPage from '../CatalogPage.vue'

defineOptions({ name: 'cl:operation:vip' })
</script>

<template>
  <CatalogPage
    entity="vip-plan"
    title="会员套餐"
    subtitle="套餐会出现在 App 的会员中心（P12），价格单位是金币。"
    :fields="[
      { key: 'days', label: '天数', type: 'number', min: 1 },
      { key: 'price_coins', label: '价格(金币)', type: 'number', min: 0 },
    ]"
  />
</template>
