<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import { captureTrafficAttributes, readTrafficAttributes, trackEvent } from '@/lib/analytics'
import VAlert from '@/shared/ui/VAlert.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'
import type { AppPageProps } from '@/types/app'

interface DemoForm {
  name: string
  email: string
  company: string
  website: string
  message: string
  utm_source?: string
  utm_medium?: string
  utm_campaign?: string
  utm_term?: string
  utm_content?: string
  landing_page?: string
}

// Persist incoming UTM/referrer attributes for the session, then hydrate the form.
captureTrafficAttributes()

const form = useForm<DemoForm>({
  name: '',
  email: '',
  company: '',
  website: '',
  message: '',
  ...readTrafficAttributes(),
})

const page = usePage<AppPageProps & { flash?: { status?: string } }>()
const submitted = ref(false)

function submit(): void {
  trackEvent('form_demo_submit', { medium: form.utm_medium ?? 'direct' })
  form.post('/demo', {
    preserveScroll: true,
    onSuccess: () => {
      submitted.value = true
      trackEvent('form_demo_success', {
        medium: form.utm_medium ?? 'direct',
        campaign: form.utm_campaign ?? '',
      })
    },
  })
}

const nextSteps = [
  {
    title: 'بررسی درخواست',
    text: 'تیم ما درخواست شما را در کمتر از ۲۴ ساعت کاری بررسی می‌کند.',
  },
  {
    title: 'هماهنگی جلسه',
    text: 'زمان جلسهٔ ۴۵ دقیقه‌ای را هماهنگ می‌کنیم — آنلاین یا حضوری.',
  },
  {
    title: 'دموی اختصاصی',
    text: 'روی سایت خودتان؛ از اتصال تا فرصت‌ها و کنترل تغییرات.',
  },
]
</script>

<template>
  <Head title="درخواست دمو" />
  <MarketingLayout>
    <MarketingPageHero
      title="یک دموی اختصاصی؛ روی سایت خود شما."
      description="در جلسهٔ ۴۵ دقیقه‌ای، وضعیت سئوی سایت خودتان را با هم بررسی می‌کنیم: اتصال، فرصت‌ها، کنترل تغییرات و گزارش‌دهی — بدون هیچ تعهدی."
    />
    <section
      class="mx-auto grid max-w-7xl gap-8 px-5 py-16 sm:px-8 lg:grid-cols-[0.85fr_1.15fr] lg:px-10 lg:py-20"
    >
      <div>
        <h2 class="text-section-title font-display text-ink-strong font-bold">
          در جلسهٔ دمو چه اتفاقی می‌افتد؟
        </h2>
        <ul class="text-ink mt-6 space-y-4 leading-7">
          <li>
            <span class="text-brand-700 font-bold">۱.</span> اتصال دادهٔ جستجوی سایت شما (سرچ
            کنسول یا ممیزی فنی دامنه)
          </li>
          <li>
            <span class="text-brand-700 font-bold">۲.</span> مشاهدهٔ فرصت‌های واقعی رتبه‌بندی‌شده
            با دادهٔ خودتان
          </li>
          <li>
            <span class="text-brand-700 font-bold">۳.</span> گردش‌کار تأیید تغییرات و پرتال مشتری
            — همان‌طور که مشتری‌های شما می‌بینند
          </li>
          <li>
            <span class="text-brand-700 font-bold">۴.</span> پاسخ به سؤالات شما دربارهٔ قیمت،
            امنیت و زمان پیاده‌سازی
          </li>
        </ul>
        <div class="rounded-panel border-line bg-success-50 mt-8 border p-5">
          <p class="text-success-700 text-sm font-bold">پاسخ‌گویی در کمتر از ۲۴ ساعت کاری</p>
          <p class="text-ink-muted mt-1 text-sm leading-6">
            درخواست شما مستقیم به تیم فروش می‌رسد؛ معمولاً در همان روز کاری پاسخ می‌گیرید.
          </p>
        </div>
      </div>
      <VCard
        v-if="!submitted"
        title="درخواست دموی اختصاصی"
        description="اطلاعات شما فقط برای هماهنگی جلسه استفاده می‌شود و به هیچ‌کس منتقل نمی‌شود."
      >
        <VAlert v-if="page.props.flash?.status" class="mb-5" tone="success">{{
          page.props.flash.status
        }}</VAlert>
        <form class="grid gap-5 sm:grid-cols-2" @submit.prevent="submit">
          <VInput v-model="form.name" label="نام و نام خانوادگی" required :error="form.errors.name" />
          <VInput v-model="form.company" label="نام شرکت یا آژانس" :error="form.errors.company" />
          <VInput
            v-model="form.email"
            class="sm:col-span-2"
            label="ایمیل کاری"
            type="email"
            dir="ltr"
            required
            :error="form.errors.email"
          />
          <VInput
            v-model="form.website"
            class="sm:col-span-2"
            label="وب‌سایت اصلی"
            type="url"
            dir="ltr"
            placeholder="https://example.ir"
            :error="form.errors.website"
          />
          <VTextarea
            v-model="form.message"
            class="sm:col-span-2"
            label="تعداد سایت‌ها یا نیاز اصلی شما"
            :error="form.errors.message"
          />
          <div class="sm:col-span-2">
            <VButton type="submit" size="lg" :loading="form.processing">ثبت درخواست دمو</VButton>
            <p class="text-ink-muted mt-3 text-xs leading-5">
              با ثبت درخواست، با <span class="text-ink font-semibold">سیاست حریم خصوصی</span> موافقت
              می‌کنید.
            </p>
          </div>
        </form>
      </VCard>
      <VCard v-else title="درخواست شما ثبت شد ✅" class="sm:col-span-2"
        ><VAlert class="mb-5" tone="success">{{
          page.props.flash?.status
        }}</VAlert>
        <div class="grid gap-5 sm:grid-cols-3">
          <div v-for="(step, index) in nextSteps" :key="step.title" class="rounded-card border-line bg-surface-muted p-4">
            <p class="text-brand-700 text-sm font-bold">قدم {{ index + 1 }}</p>
            <p class="text-ink-strong mt-2 text-sm font-bold">{{ step.title }}</p>
            <p class="text-ink-muted mt-1 text-sm leading-6">{{ step.text }}</p>
          </div>
        </div>
        <p class="text-ink-muted mt-6 text-sm">
          اگر عجله دارید، همین حالا هم می‌توانید با ما تماس بگیرید: ۰۹۰۲۴۱۵۱۶۳۰
        </p></VCard
      >
    </section>
  </MarketingLayout>
</template>
