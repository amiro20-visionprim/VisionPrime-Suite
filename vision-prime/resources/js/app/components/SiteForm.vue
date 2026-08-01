<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'
import VSelect, { type SelectOption } from '@/shared/ui/VSelect.vue'
interface Site {
  id?: number
  name: string
  canonicalUrl: string
  projectId: number
  locale: string
  timezone: string
  businessImportance: number
}
const p = defineProps<{
  site?: Site
  projects: { id: number; name: string; clientName: string }[]
}>()
const f = useForm({
  project_id: String(p.site?.projectId ?? ''),
  name: p.site?.name ?? '',
  canonical_url: p.site?.canonicalUrl ?? '',
  locale: p.site?.locale ?? 'fa',
  timezone: p.site?.timezone ?? 'Asia/Tehran',
  business_importance: String(p.site?.businessImportance ?? 3),
})
const projects: SelectOption[] = p.projects.map((x) => ({
  value: String(x.id),
  label: `${x.clientName} — ${x.name}`,
}))
function submit() {
  if (p.site?.id) {
    f.put(`/app/sites/${p.site.id}`)
    return
  }

  f.post('/app/sites')
}
</script>
<template>
  <form class="space-y-5" @submit.prevent="submit">
    <VSelect
      v-model="f.project_id"
      label="پروژه"
      :options="projects"
      :error="f.errors.project_id"
      required
    /><VInput v-model="f.name" label="نام سایت" :error="f.errors.name" required /><VInput
      v-model="f.canonical_url"
      label="Canonical URL"
      dir="ltr"
      placeholder="https://example.ir"
      :error="f.errors.canonical_url"
      required
    />
    <div class="grid gap-4 sm:grid-cols-3">
      <VSelect
        v-model="f.locale"
        label="زبان"
        :options="[
          { label: 'فارسی', value: 'fa' },
          { label: 'English', value: 'en' },
        ]"
      /><VInput
        v-model="f.timezone"
        label="Timezone"
        dir="ltr"
        :error="f.errors.timezone"
      /><VSelect
        v-model="f.business_importance"
        label="اهمیت تجاری"
        :options="[
          { label: '۱ — پایین', value: '1' },
          { label: '۲', value: '2' },
          { label: '۳ — متوسط', value: '3' },
          { label: '۴', value: '4' },
          { label: '۵ — بالا', value: '5' },
        ]"
      />
    </div>
    <div class="flex gap-3">
      <VButton type="submit" :loading="f.processing">{{ p.site ? 'ذخیره' : 'ایجاد سایت' }}</VButton
      ><VButton href="/app/sites" variant="secondary">انصراف</VButton>
    </div>
  </form>
</template>
