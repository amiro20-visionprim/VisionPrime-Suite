<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface Draft {
  id: number
  title: string
  slug: string
  content: string
  meta_title: string
  meta_description: string
  schemas: unknown[]
  quality_score: number
  subtype: string
  model_used: string
  status: string
  created_at: string
  site: { id: number; name: string }
}

const props = defineProps<{
  draft: Draft
  subtypes: Record<string, string>
}>()

const page = usePage<{ flash?: { status?: string } }>()

const f = useForm({
  title: props.draft.title,
  content: props.draft.content,
  meta_title: props.draft.meta_title || '',
  meta_description: props.draft.meta_description || '',
  status: props.draft.status,
})

const wordCount = computed(() => {
  const plain = f.content.replace(/<[^>]+>/g, ' ').trim()
  return plain ? plain.split(/\s+/).filter(w => w.length > 0).length : 0
})

function save() {
  f.put(`/app/ai-drafts/${props.draft.id}`, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`ویرایش: ${draft.title}`" />
  <AppLayout>
    <VPageHeader :title="`ویرایش مقاله`" :description="`سایت: ${draft.site?.name} | مدل: ${draft.model_used}`" />

    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">{{ page.props.flash.status }}</VAlert>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
      <div class="space-y-6">
        <VCard title="ویرایش محتوا">
          <div class="space-y-4">
            <div>
              <label class="text-ink-strong text-sm font-semibold">عنوان</label>
              <input v-model="f.title" type="text" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="text-ink-strong text-sm font-semibold">محتوای HTML</label>
              <textarea v-model="f.content" rows="20" dir="auto" class="border-line mt-1 w-full rounded-xl border p-4 text-sm leading-7 font-mono" />
            </div>
          </div>
        </VCard>

        <VCard title="Meta">
          <div class="space-y-4">
            <div>
              <label class="text-ink-strong text-sm font-semibold">Meta Title</label>
              <input v-model="f.meta_title" dir="auto" maxlength="70" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
              <span class="text-ink-muted text-xs">{{ f.meta_title.length }}/60</span>
            </div>
            <div>
              <label class="text-ink-strong text-sm font-semibold">Meta Description</label>
              <textarea v-model="f.meta_description" dir="auto" rows="3" maxlength="200" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
              <span class="text-ink-muted text-xs">{{ f.meta_description.length }}/160</span>
            </div>
          </div>
        </VCard>
      </div>

      <div class="space-y-6">
        <VCard title="وضعیت">
          <VSelect v-model="f.status" label="وضعیت" :options="[{label:'پیش‌نویس',value:'draft'},{label:'در حال بررسی',value:'review'},{label:'منتشر شده',value:'published'},{label:'بایگانی',value:'archived'}]" />
        </VCard>

        <VCard title="خلاصه">
          <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-ink-muted">کلمات:</span><span class="font-medium">{{ wordCount }}</span></div>
            <div class="flex justify-between"><span class="text-ink-muted">امتیاز:</span><span class="font-medium">{{ draft.quality_score }}/100</span></div>
            <div class="flex justify-between"><span class="text-ink-muted">زیرنوع:</span><span class="font-medium">{{ subtypes[draft.subtype] || draft.subtype }}</span></div>
            <div class="flex justify-between"><span class="text-ink-muted">تاریخ:</span><span class="font-medium">{{ new Date(draft.created_at).toLocaleDateString('fa-IR') }}</span></div>
          </div>
        </VCard>

        <VButton @click="save" :loading="f.processing" variant="primary" size="lg" class="w-full">ذخیره تغییرات</VButton>
        <VButton @click="$inertia.get('/app/ai-drafts')" variant="secondary" class="w-full">بازگشت به لیست</VButton>
      </div>
    </div>
  </AppLayout>
</template>
