<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'

const props = defineProps<{
  sites: { id: number; name: string }[]
  members: { id: number; name: string }[]
}>()

const siteOptions = props.sites.map((site) => ({
  label: site.name,
  value: String(site.id),
}))
const memberOptions = props.members.map((member) => ({
  label: member.name,
  value: String(member.id),
}))
const priorityOptions = [
  { label: 'اولویت کم', value: 'low' },
  { label: 'اولویت متوسط', value: 'medium' },
  { label: 'اولویت بالا', value: 'high' },
]

const form = useForm({
  site_id: '',
  title: '',
  body: '',
  priority: 'medium',
  owner_id: '',
  due_at: '',
})

function submit(): void {
  form.post('/app/recommendations', { preserveScroll: true })
}
</script>

<template>
  <Head title="پیشنهاد جدید" />
  <AppLayout>
    <VPageHeader
      title="پیشنهاد جدید"
      description="یک اقدام مشخص با مالک و مهلت تعیین‌شده ثبت کنید تا قابل پیگیری باشد."
      :breadcrumbs="[
        { label: 'پیشنهادها', href: '/app/recommendations' },
        { label: 'پیشنهاد جدید' },
      ]"
    />
    <VCard class="mt-8 max-w-3xl" title="جزئیات پیشنهاد">
      <form class="space-y-5" @submit.prevent="submit">
        <VSelect
          v-model="form.site_id"
          label="سایت مرتبط"
          :options="siteOptions"
          required
          :error="form.errors.site_id"
        />
        <VInput
          v-model="form.title"
          label="عنوان پیشنهاد"
          required
          placeholder="مثلاً بازنویسی متا توضیح صفحه خدمات"
          :error="form.errors.title"
        />
        <VTextarea
          v-model="form.body"
          label="توضیحات و دلیل اقدام"
          :error="form.errors.body"
          hint="مشخص کنید این اقدام چطور به رشد کمک می‌کند و چه نتیجه‌ای دارد."
        />
        <div class="grid gap-5 sm:grid-cols-2">
          <VSelect
            v-model="form.priority"
            label="اولویت"
            :options="priorityOptions"
            required
            :error="form.errors.priority"
          />
          <VSelect
            v-model="form.owner_id"
            label="مالک (مسئول انجام)"
            :options="memberOptions"
            hint="در صورت انتخاب نشدن، بدون مالک ثبت می‌شود."
            :error="form.errors.owner_id"
          />
        </div>
        <VInput
          v-model="form.due_at"
          label="مهلت انجام"
          type="date"
          :error="form.errors.due_at"
        />
        <div class="flex items-center justify-end gap-3 border-t pt-5">
          <VButton href="/app/recommendations" variant="secondary">انصراف</VButton>
          <VButton type="submit" :loading="form.processing">ثبت پیشنهاد</VButton>
        </div>
      </form>
    </VCard>
  </AppLayout>
</template>
