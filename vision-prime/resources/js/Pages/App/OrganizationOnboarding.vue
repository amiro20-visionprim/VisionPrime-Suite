<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'

const form = useForm({ name: '' })

function submit(): void {
  form.post('/app/onboarding')
}
</script>

<template>
  <Head title="ساخت فضای کاری" />
  <main class="bg-canvas min-h-screen px-5 py-10 sm:px-8 lg:py-16" dir="rtl">
    <div class="mx-auto max-w-xl">
      <div class="mb-8 text-center">
        <div
          class="rounded-card bg-brand-700 mx-auto flex size-11 items-center justify-center text-sm font-bold text-white"
        >
          VP
        </div>
        <h1 class="text-page-title font-display text-ink-strong mt-5 font-bold">
          فضای کاری آژانس خود را بسازید
        </h1>
        <p class="text-ink-muted mt-3 leading-7">
          این فضا مرز داده، اعضای تیم، مشتریان، پروژه‌ها و سایت‌های شما خواهد بود.
        </p>
      </div>
      <VCard
        title="اطلاعات سازمان"
        description="بعداً می‌توانید نام و تنظیمات سازمان را تغییر دهید."
        ><form class="space-y-6" @submit.prevent="submit">
          <VInput
            v-model="form.name"
            label="نام آژانس یا سازمان"
            placeholder="مثلاً آژانس رشد آبی"
            required
            :error="form.errors.name"
            hint="این نام برای اعضای تیم شما نمایش داده می‌شود."
          /><VButton class="w-full" type="submit" size="lg" :loading="form.processing"
            >ساخت فضای کاری</VButton
          >
        </form></VCard
      >
    </div>
  </main>
</template>
