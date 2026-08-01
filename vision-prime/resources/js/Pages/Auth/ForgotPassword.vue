<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3'

import AuthLayout from '@/layouts/AuthLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'
import type { AppPageProps } from '@/types/app'

const form = useForm({ email: '' })
const page = usePage<AppPageProps & { flash?: { status?: string } }>()

function submit(): void {
  form.post('/forgot-password')
}
</script>

<template>
  <AuthLayout
    title="بازیابی رمز عبور"
    description="ایمیل کاری خود را وارد کنید تا لینک امن تغییر رمز عبور را دریافت کنید."
  >
    <VAlert v-if="page.props.flash?.status" class="mb-5" tone="success">{{
      page.props.flash.status
    }}</VAlert>
    <form class="space-y-5" @submit.prevent="submit">
      <VInput
        v-model="form.email"
        label="ایمیل کاری"
        type="email"
        dir="ltr"
        autocomplete="email"
        required
        placeholder="name@company.com"
        :error="form.errors.email"
      />
      <VButton class="w-full" type="submit" :loading="form.processing">ارسال لینک بازیابی</VButton>
      <Link
        href="/login"
        class="text-brand-700 hover:text-brand-900 block text-center text-sm font-semibold"
        >بازگشت به ورود</Link
      >
    </form>
  </AuthLayout>
</template>
