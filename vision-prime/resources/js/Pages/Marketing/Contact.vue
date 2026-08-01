<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
</script>

<template>
  <Head title="تماس با ما" />
  <MarketingLayout>
    <MarketingPageHero
      title="برای بررسی Vision Prime با تیم ما در تماس باشید."
      description="اگر نیاز شما مربوط به استقرار، پورتال مشتری، اتوماسیون یا اتصال WordPress است، از مسیر دمو شروع کنید."
    >
      <template #actions><VButton href="/demo" size="lg">درخواست دمو</VButton></template>
    </MarketingPageHero>
  </MarketingLayout>
</template>
