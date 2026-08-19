<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import type { AppPageProps } from '@/types/app'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import VIcon from '@/shared/ui/VIcon.vue'

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

interface StandardPreview {
  word_min: number
  word_max: number | null
  min_headings: number
  required_elements: string[]
  tone: string
  schema_type: string
  keyword_density: { min: number; max: number }
  meta_title: { min_length: number; max_length: number }
  meta_description: { min_length: number; max_length: number }
}

const p = defineProps<{
  sites: SiteOption[]
  profiles: ProfileOption[]
  subtypes: Record<string, string>
  standards?: Record<string, StandardPreview>
}>()

const page = usePage<AppPageProps & { flash?: { status?: string; error?: string } }>()

const f = useForm({
  site_id: '',
  url_profile_id: '',
  title: '',
  subtype: '',
  keyword: '',
})

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

// پیش‌نمایش استاندارد بر اساس زیرنوع انتخابی
const currentStandard = computed<StandardPreview | null>(() => {
  if (!p.standards || !f.subtype) return null
  return p.standards[f.subtype] ?? null
})

// تعداد کاراکتر عنوان
const titleLength = computed(() => f.title.length)

// عنوان هشدار اگر طولانی باشد
const titleWarning = computed(() => {
  if (titleLength.value > 60) return 'عنوان بیش از ۶۰ کاراکتر است و در نتایج جستجو بریده می‌شود'
  if (titleLength.value > 0 && titleLength.value < 30) return 'عنوان کوتاه‌تر از ۳۰ کاراکتر است'
  return null
})

const elementLabels: Record<string, string> = {
  h2_structure: 'زیرعنوان‌ها (H2)',
  table_of_contents: 'فهرست مطالب',
  faq: 'سؤالات متداول',
  cta: 'دعوت به اقدام',
  internal_links: 'لینک‌های داخلی',
  steps: 'مراحل گام‌به‌گام',
  list: 'لیست',
  table: 'جدول',
  pros_cons: 'مزایا/معایب',
  rating: 'امتیازدهی',
  specs: 'مشخصات فنی',
  social_proof: 'نظرات مشتریان',
}

function onSiteChange(siteId: string): void {
  f.site_id = siteId
  f.url_profile_id = ''
}

function submit(): void {
  f.post('/app/ai-drafts/article', {
    preserveScroll: true,
    onSuccess: () => {
      f.reset('title', 'subtype', 'keyword')
    },
  })
}
</script>

<template>
  <Head title="تولید مقاله" />
  <AppLayout>
    <VPageHeader
      title="تولید مقاله با هوش مصنوعی"
      description="با انتخاب سایت و صفحهٔ هدف، پیشنویس مقالهٔ سئو شده با تمام استانداردهای RankMath/Yoast تولید و وارد صف بازبینی انسانی می‌شود."
    />

    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">
      {{ page.props.flash.status }}
    </VAlert>
    <VAlert v-if="page.props.flash?.error" tone="danger" class="mt-6">
      {{ page.props.flash.error }}
    </VAlert>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <!-- فرم اصلی -->
      <VCard title="مشخصات مقاله">
        <form class="space-y-5" @submit.prevent="submit">
          <VSelect
            v-model="f.site_id"
            label="سایت"
            :options="siteOptions"
            placeholder="سایت را انتخاب کنید"
            required
            hint="محتوا بر اساس دادهٔ سرچ کنسول همین سایت ساخته می‌شود."
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
            hint="کوئری هدف و متریک GSC از همین صفحه خوانده می‌شود."
            :error="f.errors.url_profile_id"
          />

          <VInput
            v-model="f.keyword"
            label="کلمهٔ کلیدی هدف"
            placeholder="مثال: سئو سایت فروشگاهی"
            hint="کلمهٔ کلیدی اصلی که مقاله برای آن بهینه می‌شود. اگر خالی بماند از کوئری هدف صفحه استفاده می‌شود."
            :error="f.errors.keyword"
          />

          <VInput
            v-model="f.title"
            label="عنوان مقاله"
            placeholder="مثال: راهنمای جامع سئو برای سایت‌های خدماتی"
            hint="عنوان h1 مقاله. اگر خالی بماند از کوئری هدف ساخته می‌شود."
            :error="f.errors.title"
          />

          <div class="flex items-center gap-3">
            <span class="text-ink-muted text-xs">طول عنوان:</span>
            <VBadge :tone="titleWarning ? 'danger' : titleLength > 30 && titleLength <= 60 ? 'success' : 'neutral'">
              {{ titleLength }}/۶۰
            </VBadge>
            <span v-if="titleWarning" class="text-danger-600 text-xs">{{ titleWarning }}</span>
          </div>

          <VSelect
            v-model="f.subtype"
            label="زیرنوع محتوا"
            :options="subtypeOptions"
            placeholder="تشخیص خودکار از روی عنوان/کوئری"
            hint="زیرنوع تعیین می‌کند استاندارد مؤثر (بازهٔ کلمه، ساختار، عناصر الزامی) چه باشد."
            :error="f.errors.subtype"
          />

          <div class="border-line flex items-center justify-between gap-3 border-t pt-5">
            <p class="text-ink-muted text-xs leading-6">
              ✅ پیشنویس وارد صف «بررسی و تأییدها» می‌شود · ✅ لینک‌های داخلی خودکار · ✅ اسکیمای Schema.org
            </p>
            <VButton type="submit" :loading="f.processing">
              <VIcon name="sparkles" size="sm" class="ms-1" />
              تولید پیشنویس
            </VButton>
          </div>
        </form>
      </VCard>

      <!-- پیش‌نمایش استاندارد -->
      <div class="space-y-6">
        <VCard v-if="currentStandard" title="📐 استاندارد مؤثر" description="مشخصات محتوایی که اعمال می‌شود.">
          <div class="space-y-4">
            <!-- بازهٔ کلمه -->
            <div>
              <p class="text-ink-strong mb-1 text-sm font-semibold">بازهٔ کلمات</p>
              <div class="flex items-center gap-2">
                <VBadge tone="info">{{ currentStandard.word_min }} — {{ currentStandard.word_max ?? '∞' }} کلمه</VBadge>
              </div>
            </div>

            <!-- زیرعنوان‌ها -->
            <div>
              <p class="text-ink-strong mb-1 text-sm font-semibold">زیرعنوان‌ها</p>
              <VBadge tone="info">حداقل {{ currentStandard.min_headings }} عدد h2</VBadge>
            </div>

            <!-- لحن -->
            <div>
              <p class="text-ink-strong mb-1 text-sm font-semibold">لحن</p>
              <VBadge tone="success">{{ currentStandard.tone }}</VBadge>
            </div>

            <!-- اسکیما -->
            <div>
              <p class="text-ink-strong mb-1 text-sm font-semibold">نوع اسکیما</p>
              <VBadge tone="info">{{ currentStandard.schema_type }}</VBadge>
            </div>

            <!-- تراکم کلیدواژه -->
            <div>
              <p class="text-ink-strong mb-1 text-sm font-semibold">تراکم کلیدواژه</p>
              <VBadge tone="success">{{ currentStandard.keyword_density?.min ?? 0.8 }}٪ — {{ currentStandard.keyword_density?.max ?? 2.5 }}٪</VBadge>
            </div>

            <!-- Meta -->
            <div>
              <p class="text-ink-strong mb-1 text-sm font-semibold">Meta Title</p>
              <VBadge tone="neutral">{{ currentStandard.meta_title?.min_length ?? 30 }} — {{ currentStandard.meta_title?.max_length ?? 60 }} کاراکتر</VBadge>
            </div>

            <div>
              <p class="text-ink-strong mb-1 text-sm font-semibold">Meta Description</p>
              <VBadge tone="neutral">{{ currentStandard.meta_description?.min_length ?? 120 }} — {{ currentStandard.meta_description?.max_length ?? 160 }} کاراکتر</VBadge>
            </div>

            <!-- عناصر الزامی -->
            <div>
              <p class="text-ink-strong mb-2 text-sm font-semibold">عناصر الزامی</p>
              <div class="flex flex-wrap gap-2">
                <VBadge v-for="el in currentStandard.required_elements" :key="el" tone="success">
                  {{ elementLabels[el] ?? el }}
                </VBadge>
              </div>
            </div>
          </div>
        </VCard>

        <VCard v-else title="📐 استاندارد مؤثر" description="زیرنوع را انتخاب کنید تا استاندارد نمایش داده شود.">
          <p class="text-ink-muted text-sm leading-7">
            پس از انتخاب زیرنوع محتوا، تمام فیلدهای استاندارد SEO (بازهٔ کلمه، تراکم کلیدواژه، عناصر الزامی، نوع اسکیما و...) اینجا نمایش داده می‌شود.
          </p>
        </VCard>

        <VCard title="🔗 لینک‌های داخلی" description="سیستم به‌صورت خودکار لینک‌های مرتبط را پیشنهاد و در محتوا قرار می‌دهد.">
          <p class="text-ink-muted text-sm leading-7">
            بر اساس شباهت موضوعی صفحات موجود سایت، حداقل ۲ لینک داخلی مرتبط در محتوا قرار داده می‌شود.
          </p>
        </VCard>

        <VCard title="📊 اسکیمای Schema.org" description="اسکیمای مناسب (Article, FAQ, HowTo و...) به‌صورت خودکار تولید می‌شود.">
          <p class="text-ink-muted text-sm leading-7">
            JSON-LD اسکیما بر اساس زیرنوع و محتوا تولید و در صفحه قرار داده می‌شود.
          </p>
        </VCard>
      </div>
    </div>

    <div class="mt-6">
      <Link href="/app/reviews" class="text-brand-700 text-sm font-medium">
        ← مشاهدهٔ صف بررسی و تأییدها
      </Link>
    </div>
  </AppLayout>
</template>
