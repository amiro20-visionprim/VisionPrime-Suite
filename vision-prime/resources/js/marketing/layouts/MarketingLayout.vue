<script setup lang="ts">
import AssistantWidget from '@/marketing/components/AssistantWidget.vue'
import MarketingFooter from '@/marketing/components/MarketingFooter.vue'
import MarketingHeader from '@/marketing/components/MarketingHeader.vue'
import { captureTrafficAttributes } from '@/lib/analytics'

// Record the originating landing page (first URL path) for lead attribution;
// Demo.vue later merges the utm_campaign passed by the CTA buttons.
captureTrafficAttributes()
</script>

<template>
  <div class="bg-canvas min-h-screen" dir="rtl">
    <MarketingHeader />
    <slot />
    <MarketingFooter />
    <AssistantWidget />
  </div>
</template>
