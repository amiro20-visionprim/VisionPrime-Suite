<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import AuthLayout from '@/layouts/AuthLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'

const form = useForm<{ code: string }>({ code: '' })

function submit(): void {
  form.post('/platform/mfa/verify', { onFinish: () => form.reset('code') })
}
</script>

<template>
  <Head title="تأیید دومرحله‌ای" />
  <AuthLayout
    title="تأیید دومرحله‌ای"
    description="برای امنیت بیشتر، کد ۶ رقمی اپ احراز هویت خود را وارد کنید."
  >
    <VAlert v-if="form.errors.code" class="mb-5" tone="danger">{{ form.errors.code }}</VAlert>
    <form class="space-y-5" @submit.prevent="submit">
      <VInput
        v-model="form.code"
        label="کد تأیید (Google Authenticator)"
        type="text"
        dir="ltr"
        inputmode="numeric"
        maxlength="6"
        required
        placeholder="123456"
        autofocus
      />
      <VButton type="submit" variant="primary" :loading="form.processing" class="w-full">
        تأیید و ورود
      </VButton>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        اگر کد پشتیبان دارید، همان را وارد کنید (هر کد فقط یک‌بار کار می‌کند).
      </p>
    </form>
  </AuthLayout>
</template>
