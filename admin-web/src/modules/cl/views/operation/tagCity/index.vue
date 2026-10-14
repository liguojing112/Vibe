<script setup lang="ts">
import CatalogPage from '../CatalogPage.vue'

defineOptions({ name: 'cl:operation:tag-city' })
</script>

<template>
  <div>
    <CatalogPage
      entity="tag"
      title="兴趣标签"
      subtitle="资料页可以选这些标签，停用后新用户选不到，已选的不受影响。"
      :fields="[]"
    />
    <CatalogPage
      entity="city"
      title="城市"
      subtitle="发现页的「同城」按这个判断。"
      :fields="[]"
    />
  </div>
</template>
