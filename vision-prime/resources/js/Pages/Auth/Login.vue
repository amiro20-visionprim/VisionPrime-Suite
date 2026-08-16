<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3'

import AuthLayout from '@/layouts/AuthLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'
import type { AppPageProps } from '@/types/app'

interface LoginForm {
  email: string
  password: string
  remember: boolean
}

const form = useForm<LoginForm>({ email: '', password: '', remember: false })
const page = usePage<AppPageProps & { flash?: { status?: string } }>()

function submit(): void {
  form.post('/login', { onFinish: () => form.reset('password') })
}
</script>

<template>
  <AuthLayout
    title="ورود به سوئیت"
    description="برای دسترسی به فضای کاری خود، اطلاعات ورود را وارد کنید."
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
      <VInput
        v-model="form.password"
        label="رمز عبور"
        type="password"
        autocomplete="current-password"
        required
        :error="form.errors.password"
      />
      <div class="flex items-center justify-between gap-3">
        <label class="text-ink inline-flex items-center gap-2 text-sm">
          <input
            v-model="form.remember"
            type="checkbox"
            class="border-line text-brand-700 focus:ring-brand-600 size-4 rounded"
          />
          مرا به خاطر بسپار
        </label>
        <Link
          href="/forgot-password"
          class="text-brand-700 hover:text-brand-900 text-sm font-semibold"
          >بازیابی رمز عبور</Link
        >
      </div>
      <VButton class="w-full" type="submit" :loading="form.processing">ورود به فضای کاری</VButton>
      <p class="text-ink-muted text-center text-sm">
        حساب ندارید؟
        <Link href="/register" class="text-brand-700 hover:text-brand-900 font-semibold"
          >ساخت حساب</Link
        >
      </p>
    </form>
  </AuthLayout>
</template>
