<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { AppPageProps } from '@/types/app'

const props = defineProps<{
  enabled: boolean
  enabledAt: string | null
  setupSecret: string | null
  setupUri: string | null
  mfaRequired: boolean
  canRequire: boolean
}>()

const page = usePage<
  AppPageProps & { flash?: { mfaBackupCodes?: string[]; status?: string; error?: string } }
>()
const backupCodes = computed<string[] | undefined>(() => page.props.flash?.mfaBackupCodes)
const code = ref('')
const copied = ref(false)

function setup(): void {
  router.post('/platform/mfa/setup', {}, { preserveScroll: true })
}

function enable(): void {
  router.post('/platform/mfa/enable', { code: code.value }, { preserveScroll: true, onSuccess: () => (code.value = '') })
}

function disable(): void {
  router.post('/platform/mfa/disable', { code: code.value }, { preserveScroll: true, onSuccess: () => (code.value = '') })
}

function copyCodes(): void {
  if (!backupCodes.value) return
  void navigator.clipboard.writeText(backupCodes.value.join('\n'))
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function closeCodes(): void {
  // یک‌بار دیگر قابل نمایش نباشد — با رفرش، flash از سشن حذف شده است.
  page.props.flash = { ...page.props.flash, mfaBackupCodes: undefined }
}

function toggleRequire(): void {
  router.post('/platform/mfa/require', { required: !props.mfaRequired }, { preserveScroll: true })
}
</script>

<template>
  <Head title="امنیت و MFA" />
  <PlatformLayout>
    <VPageHeader
      title="امنیت ورود"
      description="احراز هویت دومرحله‌ای (MFA) — لایهٔ امنیتی اضافه برای محافظت از اتاق فرماندهی."
    />

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <!-- وضعیت -->
      <VCard>
        <div class="p-6">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">وضعیت احراز هویت دومرحله‌ای</h3>
          <div class="mt-4 flex items-center gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <span
              class="flex size-10 items-center justify-center rounded-full text-lg"
              :class="enabled ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-amber-100 dark:bg-amber-900/40'"
            >
              {{ enabled ? '🛡️' : '⚠️' }}
            </span>
            <div>
              <p class="text-sm font-bold text-gray-900 dark:text-white">
                {{ enabled ? 'فعال است' : 'غیرفعال است' }}
              </p>
              <p v-if="enabled && enabledAt" class="text-xs text-gray-500 dark:text-gray-400">
                فعال‌شده در {{ enabledAt }}
              </p>
              <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                پیشنهاد می‌شود برای محافظت از دسترسی مدیر ارشد فعال شود.
              </p>
            </div>
          </div>

          <!-- حالت غیرفعال: فعال‌سازی -->
          <div v-if="!enabled" class="mt-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">
              با اپ Google Authenticator (یا Authy) اسکن کنید، سپس کد ۶ رقمی را وارد کنید.
            </p>
            <div v-if="setupSecret" class="mt-4 space-y-4">
              <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">کد مخفی (دستی وارد کنید):</p>
                <p dir="ltr" class="font-mono text-sm tracking-wider text-gray-800 dark:text-gray-200">{{ setupSecret }}</p>
              </div>
              <div class="flex flex-col items-center gap-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">— یا —</p>
                <a
                  v-if="setupUri"
                  :href="`https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(setupUri)}`"
                  target="_blank"
                  class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                  دریافت کد QR برای اسکن
                </a>
              </div>
              <input
                v-model="code"
                type="text"
                dir="ltr"
                inputmode="numeric"
                maxlength="6"
                placeholder="123456"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-center font-mono text-lg tracking-widest text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
              />
              <VButton variant="primary" class="w-full" @click="enable">فعال‌سازی MFA</VButton>
            </div>
            <VButton v-else variant="secondary" class="mt-4 w-full" @click="setup">
              شروع فعال‌سازی
            </VButton>
          </div>

          <!-- حالت فعال: غیرفعال‌سازی -->
          <div v-else class="mt-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">
              برای غیرفعال‌سازی، کد فعلی اپ را وارد کنید.
            </p>
            <div class="mt-4 flex gap-3">
              <input
                v-model="code"
                type="text"
                dir="ltr"
                inputmode="numeric"
                maxlength="6"
                placeholder="123456"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-center font-mono text-lg tracking-widest text-gray-900 outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
              />
              <VButton variant="danger" @click="disable">غیرفعال‌سازی</VButton>
            </div>
          </div>
        </div>
      </VCard>

      <!-- کدهای پشتیبان — فقط یک‌بار بعد از فعال‌سازی -->
      <div v-if="backupCodes" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl border border-amber-200 bg-white p-6 shadow-2xl dark:border-amber-800 dark:bg-gray-900">
          <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-lg dark:bg-amber-900/40">💾</span>
            <div>
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">کدهای پشتیبان خود را ذخیره کنید</h3>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                این کدها فقط <span class="font-bold">یک بار</span> نمایش داده می‌شوند. اگر گوشی را گم کردید با هر کدام فقط یک‌بار می‌توانید وارد شوید.
              </p>
            </div>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
            <code v-for="bc in backupCodes" :key="bc" dir="ltr" class="rounded-lg bg-white px-2 py-1.5 text-center font-mono text-sm font-bold tracking-wider text-gray-800 dark:bg-gray-800 dark:text-gray-100">
              {{ bc }}
            </code>
          </div>
          <div class="mt-5 flex gap-3">
            <VButton variant="primary" class="flex-1" @click="copyCodes">
              {{ copied ? 'کپی شد ✓' : 'کپی همه' }}
            </VButton>
            <VButton variant="secondary" class="flex-1" @click="closeCodes">ذخیره کردم</VButton>
          </div>
        </div>
      </div>

      <!-- راهنما -->
      <VCard>
        <div v-if="canRequire" class="border-b border-gray-100 p-6 pb-5 dark:border-gray-800">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">سیاست پلتفرم</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            MFA برای همه اختیاری است؛ می‌توانید برای مدیران ارشد «الزام» کنید.
          </p>
          <button
            type="button"
            class="mt-4 flex w-full items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 text-start transition-colors hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900"
            @click="toggleRequire"
          >
            <span>
              <span class="block text-sm font-bold text-gray-900 dark:text-white">الزام MFA برای مدیران ارشد</span>
              <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                {{ mfaRequired ? 'روشن — ورود به فرماندهی بدون MFA ممکن نیست.' : 'خاموش (پیش‌فرض) — هر مدیر خودش تصمیم می‌گیرد.' }}
              </span>
            </span>
            <span
              class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
              :class="mfaRequired ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-700'"
            >
              <span
                class="inline-block size-4 translate-x-1 rounded-full bg-white transition-transform"
                :class="mfaRequired ? 'translate-x-6' : ''"
              />
            </span>
          </button>
        </div>
        <div class="p-6">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">چرا مهم است؟</h3>
          <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
            <li class="flex items-start gap-2">
              <span>🛡️</span>
              <span>اتاق فرماندهی پلتفرم بالاترین دسترسی است — حتی با رمز عبور درزکرده، بدون کد دوم کسی وارد نمی‌شود.</span>
            </li>
            <li class="flex items-start gap-2">
              <span>🔑</span>
              <span>کدها هر ۳۰ ثانیه تغییر می‌کنند و فقط روی دستگاه شما نمایش داده می‌شوند.</span>
            </li>
            <li class="flex items-start gap-2">
              <span>💾</span>
              <span>در زمان فعال‌سازی، ۱۰ کد پشتیبان ساخته می‌شود — اگر گوشی را گم کردید با آن‌ها وارد شوید (هر کد یک‌بار).</span>
            </li>
            <li class="flex items-start gap-2">
              <span>📱</span>
              <span>از Google Authenticator، Authy یا هر اپ TOTP استاندارد استفاده کنید.</span>
            </li>
          </ul>
        </div>
      </VCard>
    </div>
  </PlatformLayout>
</template>
