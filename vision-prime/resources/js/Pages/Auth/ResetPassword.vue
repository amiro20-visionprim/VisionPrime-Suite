<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

import AuthLayout from '@/layouts/AuthLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'

const props = defineProps<{ email: string; token: string }>()
const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

function submit(): void {
  form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <AuthLayout
    title="انتخاب رمز عبور جدید"
    description="رمز جدید باید حداقل ۱۲ کاراکتر و شامل حروف بزرگ، کوچک و عدد باشد."
  >
    <form class="space-y-5" @submit.prevent="submit">
      <VInput
        v-model="form.email"
        label="ایمیل کاری"
        type="email"
        dir="ltr"
        autocomplete="email"
        required
        :error="form.errors.email"
      />
      <VInput
        v-model="form.password"
        label="رمز عبور جدید"
        type="password"
        autocomplete="new-password"
        required
        :error="form.errors.password"
      />
      <VInput
        v-model="form.password_confirmation"
        label="تکرار رمز عبور جدید"
        type="password"
        autocomplete="new-password"
        required
      />
      <VButton class="w-full" type="submit" :loading="form.processing">ثبت رمز عبور جدید</VButton>
    </form>
  </AuthLayout>
</template>
