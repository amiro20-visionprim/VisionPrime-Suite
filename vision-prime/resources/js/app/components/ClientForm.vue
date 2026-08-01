<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'

interface ClientFormData {
  id?: number
  name: string
  contactName?: string | null
  contactEmail?: string | null
  contactPhone?: string | null
}

const props = withDefaults(
  defineProps<{
    client?: ClientFormData | null
  }>(),
  { client: null },
)

const form = useForm({
  name: props.client?.name ?? '',
  contact_name: props.client?.contactName ?? '',
  contact_email: props.client?.contactEmail ?? '',
  contact_phone: props.client?.contactPhone ?? '',
})

function submit(): void {
  if (props.client?.id) {
    form.put(`/app/clients/${props.client.id}`)
    return
  }

  form.post('/app/clients')
}
</script>

<template>
  <form class="space-y-7" @submit.prevent="submit">
    <section class="space-y-5">
      <div>
        <h2 class="text-ink-strong font-bold">اطلاعات اصلی</h2>
        <p class="text-ink-muted mt-1 text-sm leading-6">
          نام مشتری در تمام پروژه‌ها، گزارش‌ها و پرتال مشتری نمایش داده می‌شود.
        </p>
      </div>
      <VInput
        v-model="form.name"
        label="نام مشتری یا کسب‌وکار"
        required
        placeholder="مثلاً کلینیک آفتاب"
        :error="form.errors.name"
      />
    </section>
    <section class="border-line border-t pt-7">
      <div>
        <h2 class="text-ink-strong font-bold">اطلاعات تماس</h2>
        <p class="text-ink-muted mt-1 text-sm leading-6">
          اختیاری است و برای مدیریت ارتباط تیم شما با مشتری استفاده می‌شود.
        </p>
      </div>
      <div class="mt-5 grid gap-5 sm:grid-cols-2">
        <VInput
          v-model="form.contact_name"
          label="نام شخص تماس"
          placeholder="نام و نام خانوادگی"
          :error="form.errors.contact_name"
        /><VInput
          v-model="form.contact_email"
          label="ایمیل تماس"
          type="email"
          dir="ltr"
          placeholder="contact@example.ir"
          :error="form.errors.contact_email"
        /><VInput
          v-model="form.contact_phone"
          class="sm:col-span-2"
          label="شماره تماس"
          dir="ltr"
          placeholder="0912..."
          :error="form.errors.contact_phone"
        />
      </div>
    </section>
    <div class="border-line flex flex-wrap gap-3 border-t pt-6">
      <VButton type="submit" :loading="form.processing">{{
        client ? 'ذخیره تغییرات' : 'ایجاد مشتری'
      }}</VButton
      ><VButton href="/app/clients" variant="secondary">انصراف</VButton>
    </div>
  </form>
</template>
