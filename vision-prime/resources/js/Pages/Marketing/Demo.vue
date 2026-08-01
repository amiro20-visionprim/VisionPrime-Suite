<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'

const name = ref('')
const email = ref('')
const company = ref('')
const website = ref('')
const submitted = ref(false)

function submit(): void {
  submitted.value = true
}
</script>

<template>
  <Head title="درخواست دمو" />
  <MarketingLayout>
    <MarketingPageHero
      title="یک دموی متناسب با فرآیند واقعی تیم شما."
      description="با هم مسیر اتصال سایت، تحلیل فرصت، کنترل تغییرات و گزارش‌دهی را برای سناریوی کسب‌وکار شما بررسی می‌کنیم."
    />
    <section
      class="mx-auto grid max-w-7xl gap-8 px-5 py-16 sm:px-8 lg:grid-cols-[0.8fr_1.2fr] lg:px-10 lg:py-20"
    >
      <div>
        <h2 class="text-section-title font-display text-ink-strong font-bold">
          در دمو چه می‌بینید؟
        </h2>
        <ul class="text-ink mt-6 space-y-4 leading-7">
          <li>● مسیر داده تا فرصت رشد</li>
          <li>● سطح‌های کنترل و خودکارسازی هر سایت</li>
          <li>● پرتال مشتری و گزارش مدیریتی</li>
          <li>● مدل استقرار SaaS یا Private Deployment</li>
        </ul>
      </div>
      <VCard title="درخواست دمو" description="اطلاعات شما فقط برای هماهنگی جلسه استفاده می‌شود."
        ><VAlert v-if="submitted" class="mb-5" tone="success" title="درخواست ثبت شد"
          >در این مرحله، فرم به‌صورت نمایشی است؛ اتصال ذخیره‌سازی و ارسال Lead در فاز Conversion
          فعال می‌شود.</VAlert
        >
        <form class="grid gap-5 sm:grid-cols-2" @submit.prevent="submit">
          <VInput v-model="name" label="نام و نام خانوادگی" required /><VInput
            v-model="company"
            label="نام شرکت یا آژانس"
            required
          /><VInput
            v-model="email"
            class="sm:col-span-2"
            label="ایمیل کاری"
            type="email"
            dir="ltr"
            required
          /><VInput
            v-model="website"
            class="sm:col-span-2"
            label="وب‌سایت اصلی"
            type="url"
            dir="ltr"
            placeholder="https://example.ir"
          /><VTextarea class="sm:col-span-2" label="تعداد سایت‌ها یا نیاز اصلی شما" />
          <div class="sm:col-span-2"><VButton type="submit" size="lg">ثبت درخواست</VButton></div>
        </form></VCard
      >
    </section>
  </MarketingLayout>
</template>
