<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

import AuthLayout from '@/layouts/AuthLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VButton from '@/shared/ui/VButton.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import VInput from '@/shared/ui/VInput.vue'
import type { AppPageProps } from '@/types/app'

interface LoginForm {
  email: string
  password: string
  remember: boolean
}

type LoginMode = 'password' | 'otp'

const mode = ref<LoginMode>('password')
const showPassword = ref(false)
const otpSent = ref(false)
const otpCode = ref('')
const otpBusy = ref(false)
const otpMessage = ref('')
const otpError = ref('')

const form = useForm<LoginForm>({ email: '', password: '', remember: false })
const otpForm = useForm<{ phone: string; code: string }>({ phone: '', code: '' })
const page = usePage<AppPageProps & { flash?: { status?: string } }>()

function submit(): void {
  form.post('/login', { onFinish: () => form.reset('password') })
}

function switchMode(next: LoginMode): void {
  mode.value = next
  otpMessage.value = ''
  otpError.value = ''
}

async function requestOtp(): Promise<void> {
  if (!/^0?9[0-9]{9}$/.test(otpForm.phone)) {
    otpError.value = 'شماره تماس معتبر وارد کنید (مثال: 09123456789).'
    return
  }
  otpBusy.value = true
  otpError.value = ''
  otpMessage.value = ''
  try {
    const response = await fetch('/login/otp', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({ phone: otpForm.phone }),
    })
    const data = (await response.json()) as { sent: boolean; message: string; code?: string }
    if (response.ok && data.sent) {
      otpSent.value = true
      otpMessage.value = data.message
      // در حالت sandbox (بدون کلید کاوه‌نگار) کد برای تست نمایش داده می‌شود.
      if (data.code) {
        otpCode.value = data.code
        otpForm.code = data.code
      }
    } else {
      otpError.value = data.message || 'ارسال کد ممکن نشد.'
    }
  } catch {
    otpError.value = 'اتصال برقرار نشد؛ دوباره تلاش کنید.'
  } finally {
    otpBusy.value = false
  }
}

async function submitOtp(): Promise<void> {
  if (otpBusy.value) return
  otpBusy.value = true
  otpError.value = ''
  otpMessage.value = ''
  try {
    const response = await fetch('/login/otp/verify', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({ phone: otpForm.phone, code: otpForm.code }),
    })
    const data = (await response.json()) as { success?: boolean; message?: string; location?: string }
    if (response.redirected && response.url) {
      window.location.href = response.url
      return
    }
    if (data.success) {
      window.location.href = '/app/dashboard'
      return
    }
    otpError.value = data.message || 'کد صحیح نیست.'
  } catch {
    otpError.value = 'اتصال برقرار نشد؛ دوباره تلاش کنید.'
  } finally {
    otpBusy.value = false
  }
}

function csrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : ''
}
</script>

<template>
  <AuthLayout
    title="ورود به سوئیت"
    description="برای دسترسی به فضای کاری خود، با رمز عبور یا کد یکبارمصرف وارد شوید."
  >
    <VAlert v-if="page.props.flash?.status" class="mb-5" tone="success">{{
      page.props.flash.status
    }}</VAlert>

    <!-- Tabs -->
    <div class="border-line mb-6 flex rounded-ui border bg-surface-muted/50 p-1">
      <button
        type="button"
        class="flex-1 rounded-ui px-4 py-2 text-sm font-bold transition"
        :class="
          mode === 'password' ? 'bg-brand-700 text-white shadow-sm' : 'text-ink-muted hover:text-ink-strong'
        "
        @click="switchMode('password')"
      >
        رمز عبور
      </button>
      <button
        type="button"
        class="flex-1 rounded-ui px-4 py-2 text-sm font-bold transition"
        :class="mode === 'otp' ? 'bg-brand-700 text-white shadow-sm' : 'text-ink-muted hover:text-ink-strong'"
        @click="switchMode('otp')"
      >
        کد یکبارمصرف
      </button>
    </div>

    <!-- Password mode -->
    <form v-if="mode === 'password'" class="space-y-5" @submit.prevent="submit">
      <VInput
        v-model="form.email"
        label="ایمیل کاری"
        type="email"
        dir="ltr"
        autocomplete="email"
        required
        placeholder="name@company.com"
        :error="form.errors.email"
      >
        <template #leading><VIcon name="user-check" size="sm" /></template>
      </VInput>
      <VInput
        v-model="form.password"
        label="رمز عبور"
        :type="showPassword ? 'text' : 'password'"
        autocomplete="current-password"
        required
        :error="form.errors.password"
      >
        <template #leading><VIcon name="shield" size="sm" /></template>
        <template #trailing>
          <button
            type="button"
            class="text-ink-muted hover:text-ink-strong transition"
            :aria-label="showPassword ? 'پنهان‌کردن رمز' : 'نمایش رمز'"
            :title="showPassword ? 'پنهان‌کردن رمز' : 'نمایش رمز'"
            @click="showPassword = !showPassword"
          >
            <VIcon :name="showPassword ? 'eye-off' : 'eye'" size="sm" />
          </button>
        </template>
      </VInput>
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

    <!-- OTP mode -->
    <form v-else class="space-y-5" @submit.prevent="otpSent ? submitOtp() : requestOtp()">
      <VInput
        v-model="otpForm.phone"
        label="شماره تماس"
        type="tel"
        dir="ltr"
        autocomplete="tel"
        required
        placeholder="09123456789"
        :error="otpError"
      >
        <template #leading><VIcon name="bell" size="sm" /></template>
      </VInput>

      <div v-if="otpSent">
        <VInput
          v-model="otpForm.code"
          label="کد تأیید"
          type="text"
          dir="ltr"
          inputmode="numeric"
          autocomplete="one-time-code"
          required
          maxlength="6"
          placeholder="••••••"
          :error="otpError"
        />
        <p v-if="otpCode" class="text-ink-muted mt-2 rounded-ui bg-surface-muted border-line border p-2 text-xs">
          حالت آزمایشی — کد: <b dir="ltr">{{ otpCode }}</b>
        </p>
        <p v-if="otpMessage" class="text-success-700 mt-2 text-xs font-semibold">{{ otpMessage }}</p>
      </div>

      <VButton class="w-full" type="submit" :loading="otpBusy">
        {{ otpSent ? 'ورود با کد' : 'ارسال کد' }}
      </VButton>
      <p class="text-ink-muted text-center text-sm">
        کد از طریق پیامک ارسال می‌شود؛ اگر پیامک نرسید، دوباره تلاش کنید.
      </p>
    </form>
  </AuthLayout>
</template>
