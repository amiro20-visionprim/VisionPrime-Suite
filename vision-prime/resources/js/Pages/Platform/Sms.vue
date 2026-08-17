<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface Provider {
  key: string
  label: string
}

interface SmsLog {
  id: number
  driver: string
  to: string
  message: string
  status: string
  error: string | null
  created_at: string
}

defineProps<{
  providers: Provider[]
  logs: SmsLog[]
}>()

const to = ref('')
const message = ref('')
const driver = ref('kavenegar')
const sending = ref(false)

function send(): void {
  if (sending.value || !to.value || !message.value) return
  sending.value = true
  router.post(
    '/platform/sms',
    { to: to.value, message: message.value, driver: driver.value },
    { preserveScroll: true, onFinish: () => (sending.value = false) },
  )
}

const statusTone = (status: string): 'success' | 'danger' | 'info' | 'neutral' | 'warning' =>
  status === 'sent' ? 'success' : 'danger'
</script>

<template>
  <Head title="پنل پیامک" />
  <PlatformLayout>
    <VPageHeader
      title="پنل پیامکی"
      description="ارسال پیامک به مشتریان و همکاران، و مشاهدهٔ تاریخچهٔ همهٔ پیامک‌های ارسال‌شده."
    />

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <!-- ارسال پیامک -->
      <VCard>
        <div class="p-6">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">ارسال پیامک جدید</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            شماره را با ۰۹ وارد کنید؛ در حالت بدون کلید، پیامک شبیه‌سازی و در تاریخچه ثبت می‌شود.
          </p>

          <form class="mt-6 space-y-5" @submit.prevent="send">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                شمارهٔ موبایل گیرنده
              </label>
              <input
                v-model="to"
                type="tel"
                dir="ltr"
                placeholder="0912xxxxxxx"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:ring-indigo-900"
              />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                متن پیامک
              </label>
              <textarea
                v-model="message"
                rows="4"
                placeholder="متن پیام..."
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:ring-indigo-900"
              ></textarea>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                پنل ارسال‌دهنده
              </label>
              <select
                v-model="driver"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
              >
                <option v-for="p in providers" :key="p.key" :value="p.key">{{ p.label }}</option>
              </select>
            </div>

            <VButton type="submit" variant="primary" :loading="sending" class="w-full">
              ارسال پیامک
            </VButton>
          </form>
        </div>
      </VCard>

      <!-- تاریخچه -->
      <VCard>
        <div class="p-6">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">تاریخچهٔ پیامک‌ها</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">۱۰۰ پیامک آخر</p>

          <div class="mt-4 max-h-[480px] space-y-2 overflow-y-auto pl-1">
            <div
              v-for="log in logs"
              :key="log.id"
              class="rounded-xl border border-gray-200 p-3 dark:border-gray-800"
            >
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <span dir="ltr" class="text-sm font-medium text-gray-900 dark:text-white">{{ log.to }}</span>
                  <VBadge :tone="statusTone(log.status)">
                    {{ log.status === 'sent' ? 'ارسال شد' : 'ناموفق' }}
                  </VBadge>
                </div>
                <span class="text-xs text-gray-400">{{ log.created_at }}</span>
              </div>
              <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ log.message }}</p>
              <p v-if="log.error" class="mt-1 text-xs text-rose-500">{{ log.error }}</p>
            </div>

            <div v-if="logs.length === 0" class="py-10 text-center text-sm text-gray-400">
              هنوز پیامکی ارسال نشده است.
            </div>
          </div>
        </div>
      </VCard>
    </div>
  </PlatformLayout>
</template>
