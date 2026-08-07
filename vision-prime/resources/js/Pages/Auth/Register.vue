<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'

import AuthLayout from '@/layouts/AuthLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'

interface RegisterForm {
  name: string
  email: string
  password: string
  password_confirmation: string
}

const form = useForm<RegisterForm>({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

function submit(): void {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <AuthLayout
    title="ساخت حساب رایگان"
    description="در کمتر از یک دقیقه حساب بسازید و فضای کاری خود را شروع کنید."
  >
    <form class="space-y-5" @submit.prevent="submit">
      <VInput
        v-model="form.name"
        label="نام و نام خانوادگی"
        type="text"
        autocomplete="name"
        required
        placeholder="مثلاً سارا محمدی"
        :error="form.errors.name"
      />
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
        autocomplete="new-password"
        required
        hint="حداقل ۱۲ کاراکتر با حروف بزرگ، کوچک و عدد"
        :error="form.errors.password"
      />
      <VInput
        v-model="form.password_confirmation"
        label="تکرار رمز عبور"
        type="password"
        autocomplete="new-password"
        required
        :error="form.errors.password_confirmation"
      />
      <VButton class="w-full" type="submit" :loading="form.processing">ساخت حساب و شروع کار</VButton>
      <p class="text-ink-muted text-center text-sm">
        قبلاً حساب ساخته‌اید؟
        <Link href="/login" class="text-brand-700 hover:text-brand-900 font-semibold"
          >ورود به حساب</Link
        >
      </p>
    </form>
  </AuthLayout>
</template>
