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
  f.post('/app/ai-drafts/product', {
    preserveScroll: true,
    onSuccess: () => {
      f.reset('title', 'subtype')
    },
  })
}
</script>

<template>
  <Head title="تولید پیشنویس محصول" />
  <AppLayout>
    <VPageHeader
      title="تولید پیشنویس محصول با هوش مصنوعی"
      description="با انتخاب سایت و صفحهٔ محصول، پیشنویس توضیح محصول (کوتاه/بلند، مشخصات فنی یا مقایسه‌ای) با اسکیمای Product و استاندارد مؤثر زیرنوع تولید و وارد صف بازبینی انسانی می‌شود."
    />

    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">
      {{ page.props.flash.status }}
    </VAlert>
    <VAlert v-if="page.props.flash?.error" tone="danger" class="mt-6">
      {{ page.props.flash.error }}
    </VAlert>

    <VCard class="mt-6" title="مشخصات محصول">
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
          label="صفحهٔ محصول (URL)"
          :options="profileOptions"
          placeholder="صفحهٔ محصول را انتخاب کنید"
          required
          :disabled="!f.site_id"
          hint="فقط پروفایل‌های با نوع محتوا «product» (ووکامرس) نمایش داده می‌شوند."
          :error="f.errors.url_profile_id"
        />

        <VInput
          v-model="f.title"
          label="عنوان محصول (اختیاری)"
          placeholder="مثال: هدفون بی‌سیم پرو نسل ۲۰۲۶"
          hint="اگر خالی بماند، از کوئری هدف صفحه یا آدرس آن استخراج می‌شود."
          :error="f.errors.title"
        />

        <VSelect
          v-model="f.subtype"
          label="زیرنوع محتوا (اختیاری)"
          :options="subtypeOptions"
          placeholder="تشخیص خودکار از روی عنوان/کوئری"
          hint="استاندارد مؤثر (بازهٔ کلمه، ساختار، عناصر الزامی) دقیقاً بر اساس همین زیرنوع اعمال می‌شود."
          :error="f.errors.subtype"
        />

        <div class="border-line flex items-center justify-between gap-3 border-t pt-5">
          <p class="text-ink-muted text-xs leading-6">
            پیشنویس محصول با اسکیمای Product و تصویر شاخص مربعی تولید و وارد صف «بررسی و تأییدها» می‌شود؛ تا تأیید انسانی اجرا نمی‌شود.
          </p>
          <VButton type="submit" :loading="f.processing">تولید پیشنویس محصول</VButton>
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
