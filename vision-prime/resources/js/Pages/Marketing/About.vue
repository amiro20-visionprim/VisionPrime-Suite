<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
</script>

<template>
  <Head title="درباره سوئیت" />
  <MarketingLayout>
    <MarketingPageHero
      title="سوئیت برای تیم‌هایی ساخته می‌شود که SEO را یک عملیات قابل مدیریت می‌دانند."
      description="هدف ما کم‌کردن ابزارهای پراکنده و افزایش شفافیت میان داده، اقدام تیم، تصمیم مشتری و نتیجه قابل مشاهده است."
    />
  </MarketingLayout>
</template>
