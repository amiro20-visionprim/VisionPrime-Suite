<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'
const p = defineProps<{
  item: { id: number; subject_type: string; subject_id: number; status: string }
  decisions: { id: number; decision: string; note: string | null; decided_at: string }[]
}>()
const f = useForm({ decision: 'approved', note: '' })
function decide() {
  f.post(`/app/reviews/${p.item.id}/decision`)
}
</script>
<template>
  <Head title="جزئیات بررسی" /><AppLayout
    ><VPageHeader title="بررسی خروجی" :description="p.item.subject_type" /><VCard
      class="mt-8"
      title="تصمیم"
      ><form class="space-y-4" @submit.prevent="decide">
        <select v-model="f.decision">
          <option value="approved">تأیید</option>
          <option value="rejected">رد</option>
          <option value="changes_requested">درخواست تغییر</option></select
        ><VTextarea v-model="f.note" label="یادداشت تصمیم" /><VButton type="submit"
          >ثبت تصمیم</VButton
        >
      </form></VCard
    ><VCard class="mt-6" title="تاریخچه تصمیم"
      ><div v-for="d in decisions" :key="d.id" class="border-line border-b py-3">
        <p>{{ d.decision }}</p>
        <p class="text-ink-muted text-sm">{{ d.note }}</p>
      </div></VCard
    ></AppLayout
  >
</template>
