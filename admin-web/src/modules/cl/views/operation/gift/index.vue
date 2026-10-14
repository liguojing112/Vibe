<script setup lang="ts">
import CatalogPage from '../CatalogPage.vue'

defineOptions({ name: 'cl:operation:gift' })
</script>

<template>
  <CatalogPage
    entity="gift"
    title="礼物管理"
    subtitle="图标可以填 emoji（如 🌹），也可以填上传后的图片地址。停用只是不让新的选，历史记录不受影响。"
    has-icon
    :fields="[
      { key: 'price_coins', label: '价格(金币)', type: 'number', min: 0 },
    ]"
  />
</template>
