<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import type { AppPageProps } from '@/types/app'
import VAlert from '@/shared/ui/VAlert.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface SiteOption {
  id: number
  name: string
  canonical_url: string
}

interface ProfileOption {
  id: number
  site_id: number
  canonical_url: string
  content_type: string
}

const p = defineProps<{
  sites: SiteOption[]
  profiles: ProfileOption[]
  subtypes: Record<string, string>
}>()

const page = usePage<AppPageProps & { flash?: { status?: string; error?: string } }>()

const f = useForm({ site_id: '', url_profile_id: '', title: '', subtype: '' })

const availableProfiles = computed(() =>
  f.site_id ? p.profiles.filter((profile) => profile.site_id === Number(f.site_id)) : [],
)

const siteOptions = computed(() =>
  p.sites.map((site) => ({ label: site.name, value: String(site.id) })),
)

const profileOptions = computed(() =>
  availableProfiles.value.map((profile) => ({
    label: profile.canonical_url,
    value: String(profile.id),
  })),
)

const subtypeOptions = computed(() =>
  Object.entries(p.subtypes).map(([value, label]) => ({ label, value })),
)

function onSiteChange(siteId: string): void {
  f.site_id = siteId
  f.url_profile_id = ''
}

function submit(): void {
  f.post('/app/ai-drafts/article', {
    preserveScroll: true,
    onSuccess: () => {
      // پس از تولید موفق، فرم برای ساخت بعدی پاک می‌شود
      f.reset('title', 'subtype')
    },
  })
}
</script>

<template>
  <Head title="تولید مقاله" />
  <AppLayout>
    <VPageHeader
      title="تولید مقاله با هوش مصنوعی"
      description="با انتخاب سایت و صفحهٔ هدف، پیشنویس مقاله با دادهٔ جستجوی همان صفحه (کوئری هدف، متریک و تازگی GSC) و استاندارد مؤثر زیرنوع تولید و وارد صف بازبینی انسانی می‌شود."
    />

    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">
      {{ page.props.flash.status }}
    </VAlert>
    <VAlert v-if="page.props.flash?.error" tone="danger" class="mt-6">
      {{ page.props.flash.error }}
    </VAlert>

    <VCard class="mt-6" title="مشخصات مقاله">
      <form class="space-y-5" @submit.prevent="submit">
        <VSelect
          v-model="f.site_id"
          label="سایت"
          :options="siteOptions"
          placeholder="سایت را انتخاب کنید"
          required
          hint="محتوای تولیدشده بر اساس دادهٔ سرچ کنسول همین سایت ساخته می‌شود."
          :error="f.errors.site_id"
          @update:model-value="onSiteChange"
        />

        <VSelect
          v-model="f.url_profile_id"
          label="صفحهٔ هدف (URL)"
          :options="profileOptions"
          placeholder="صفحهٔ هدف را انتخاب کنید"
          required
          :disabled="!f.site_id"
          hint="مقاله برای این صفحه و با کوئری هدفِ ثبت‌شدهٔ آن تولید می‌شود."
          :error="f.errors.url_profile_id"
        />

        <VInput
          v-model="f.title"
          label="عنوان مقاله (اختیاری)"
          placeholder="مثال: راهنمای جامع سئو برای سایت‌های خدماتی"
          hint="اگر خالی بماند، از کوئری هدف صفحه یا آدرس آن استخراج می‌شود."
          :error="f.errors.title"
        />

        <VSelect
          v-model="f.subtype"
          label="زیرنوع محتوا (اختیاری)"
          :options="subtypeOptions"
          placeholder="تشخیص خودکار از روی عنوان/کوئری"
          hint="اگر انتخاب نشود، سیستم زیرنوع را از عنوان و کوئری هدف تشخیص می‌دهد. استاندارد مؤثر (بازهٔ کلمه، ساختار، عناصر الزامی) دقیقاً بر اساس همین زیرنوع اعمال می‌شود."
          :error="f.errors.subtype"
        />

        <div class="border-line flex items-center justify-between gap-3 border-t pt-5">
          <p class="text-ink-muted text-xs leading-6">
            پیشنویس تولیدشده وارد صف «بررسی و تأییدها» می‌شود و تا تأیید انسانی اجرا نمی‌شود.
          </p>
          <VButton type="submit" :loading="f.processing">تولید پیشنویس مقاله</VButton>
        </div>
      </form>
    </VCard>

    <div class="mt-6">
      <Link href="/app/reviews" class="text-brand-700 text-sm font-medium"
        >← مشاهدهٔ صف بررسی و تأییدها</Link
      >
    </div>
  </AppLayout>
</template>
