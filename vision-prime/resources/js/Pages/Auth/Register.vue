<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

import AuthLayout from '@/layouts/AuthLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import VInput from '@/shared/ui/VInput.vue'

interface RegisterForm {
  name: string
  email: string
  phone: string
  otp_code: string
  password: string
  password_confirmation: string
  terms: boolean
}

const form = useForm<RegisterForm>({
  name: '',
  email: '',
  phone: '',
  otp_code: '',
  password: '',
  password_confirmation: '',
  terms: false,
})

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const otpSent = ref(false)
const otpBusy = ref(false)
const otpMessage = ref('')
const otpError = ref('')
const sandboxCode = ref('')

function submit(): void {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}

async function requestOtp(): Promise<void> {
  if (!/^0?9[0-9]{9}$/.test(form.phone)) {
    otpError.value = 'شماره تماس معتبر وارد کنید (مثال: 09123456789).'
    return
  }
  otpBusy.value = true
  otpError.value = ''
  otpMessage.value = ''
  try {
    const response = await fetch('/register/otp', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({ phone: form.phone }),
    })
    const data = (await response.json()) as { sent: boolean; message: string; code?: string }
    if (response.ok && data.sent) {
      otpSent.value = true
      otpMessage.value = data.message
      if (data.code) {
        sandboxCode.value = data.code
        form.otp_code = data.code
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

function csrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : ''
}
</script>

<template>
  <AuthLayout
    title="ساخت حساب"
    description="در کمتر از یک دقیقه حساب بسازید — شماره تماس خود را با کد تأیید وریفای کنید."
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
      >
        <template #leading><VIcon name="user-check" size="sm" /></template>
      </VInput>
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

      <!-- Phone + OTP verify -->
      <div class="space-y-2">
        <div class="flex items-end gap-2">
          <div class="flex-1">
            <VInput
              v-model="form.phone"
              label="شماره تماس"
              type="tel"
              dir="ltr"
              autocomplete="tel"
              required
              placeholder="09123456789"
              :error="otpError || form.errors.phone"
            >
              <template #leading><VIcon name="phone" size="sm" /></template>
            </VInput>
          </div>
          <VButton
            type="button"
            variant="secondary"
            size="sm"
            class="mb-0.5"
            :loading="otpBusy"
            :disabled="otpSent"
            @click="requestOtp"
          >
            {{ otpSent ? 'ارسال شد ✓' : 'دریافت کد' }}
          </VButton>
        </div>
        <div v-if="otpSent" class="space-y-2">
          <VInput
            v-model="form.otp_code"
            label="کد تأیید پیامکشده"
            type="text"
            dir="ltr"
            inputmode="numeric"
            autocomplete="one-time-code"
            required
            maxlength="6"
            placeholder="••••••"
            :error="form.errors.otp_code"
          />
          <p v-if="sandboxCode" class="text-ink-muted rounded-ui bg-surface-muted border-line border p-2 text-xs">
            حالت آزمایشی — کد: <b dir="ltr">{{ sandboxCode }}</b>
          </p>
          <p v-if="otpMessage" class="text-success-700 text-xs font-semibold">{{ otpMessage }}</p>
        </div>
      </div>

      <VInput
        v-model="form.password"
        label="رمز عبور"
        :type="showPassword ? 'text' : 'password'"
        autocomplete="new-password"
        required
        hint="حداقل ۸ کاراکتر با حروف بزرگ، کوچک و عدد"
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
      <VInput
        v-model="form.password_confirmation"
        label="تکرار رمز عبور"
        :type="showPasswordConfirmation ? 'text' : 'password'"
        autocomplete="new-password"
        required
        :error="form.errors.password_confirmation"
      >
        <template #leading><VIcon name="shield" size="sm" /></template>
        <template #trailing>
          <button
            type="button"
            class="text-ink-muted hover:text-ink-strong transition"
            :aria-label="showPasswordConfirmation ? 'پنهان‌کردن رمز' : 'نمایش رمز'"
            :title="showPasswordConfirmation ? 'پنهان‌کردن رمز' : 'نمایش رمز'"
            @click="showPasswordConfirmation = !showPasswordConfirmation"
          >
            <VIcon :name="showPasswordConfirmation ? 'eye-off' : 'eye'" size="sm" />
          </button>
        </template>
      </VInput>

      <!-- Terms -->
      <label class="flex items-start gap-2.5 text-sm leading-6">
        <input
          v-model="form.terms"
          type="checkbox"
          class="border-line text-brand-700 focus:ring-brand-600 mt-1 size-4 shrink-0 rounded"
        />
        <span class="text-ink-muted">
          ثبت‌نام به منزلهٔ پذیرفتن
          <Link href="/terms" class="text-brand-700 hover:text-brand-900 font-semibold"
            >قوانین و سیاست‌های Vision Prime SUITE</Link
          >
          است.
        </span>
      </label>
      <p v-if="form.errors.terms" class="text-danger-600 text-sm" role="alert">
        {{ form.errors.terms }}
      </p>

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
