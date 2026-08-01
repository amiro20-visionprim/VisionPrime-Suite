<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'

const controls = [
  ['اتصال سایت', 'HMAC، secret اختصاصی هر سایت، timestamp، nonce و جلوگیری از replay.'],
  [
    'عملیات تغییر',
    'Commandهای whitelist شده، expiration، idempotency، approval و rollback snapshot.',
  ],
  ['داده‌های خارجی', 'رمزگذاری tokenهای OAuth و نگه‌داری متمرکز کلیدهای AI فقط در Laravel.'],
  ['پاسخ‌گویی', 'Audit trail برای تغییرات حساس، تأییدها، اتصال‌ها و چرخه اجرای command.'],
]
</script>

<template>
  <Head title="امنیت" />
  <MarketingLayout>
    <MarketingPageHero
      title="اتوماسیون مفید، فقط زمانی ارزشمند است که قابل کنترل و قابل پاسخ‌گویی باشد."
      description="Vision Prime امنیت را به‌عنوان یک تنظیم جانبی نمی‌بیند؛ امنیت بخشی از طراحی جریان داده، AI و تغییرات وردپرس است."
    />
    <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
      <div class="grid gap-5 md:grid-cols-2">
        <VCard v-for="control in controls" :key="control[0]" :title="control[0]"
          ><p class="text-ink-muted leading-7">{{ control[1] }}</p></VCard
        >
      </div>
      <div class="mt-10">
        <VButton href="/demo">بررسی مدل امنیتی در دمو</VButton>
      </div>
    </section>
  </MarketingLayout>
</template>
