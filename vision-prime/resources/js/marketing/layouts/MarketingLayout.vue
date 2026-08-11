<script setup lang="ts">
import AssistantWidget from '@/marketing/components/AssistantWidget.vue'
import MarketingFooter from '@/marketing/components/MarketingFooter.vue'
import MarketingHeader from '@/marketing/components/MarketingHeader.vue'
</script>

<template>
  <div class="bg-canvas min-h-screen" dir="rtl">
    <MarketingHeader />
    <slot />
    <MarketingFooter />
    <AssistantWidget />
  </div>
</template>
