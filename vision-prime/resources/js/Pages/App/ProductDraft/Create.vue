<script setup lang="ts">
import { computed } from 'vue'
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

const currentStandard = computed<StandardPreview | null>(() => {
  if (!p.standards || !f.subtype) return null
  return p.standards[f.subtype] ?? null
})

const titleLength = computed(() => f.title.length)
const titleWarning = computed(() => {
  if (titleLength.value > 60) return 'عنوان بیش از ۶۰ کاراکتر است'
  if (titleLength.value > 0 && titleLength.value < 30) return 'عنوان کوتاه است'
  return null
})

const elementLabels: Record<string, string> = {
  h2_structure: 'زیرعنوان‌ها (H2)',
  list: 'لیست ویژگی‌ها',
  specs: 'مشخصات فنی',
  table: 'جدول مقایسه',
  cta: 'دعوت به اقدام',
  internal_links: 'لینک‌های داخلی',
  social_proof: 'نظرات مشتریان',
}

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
      description="پیشنویس توضیح محصول (کوتاه/بلند، مشخصات فنی یا مقایسه‌ای) با اسکیمای Product و قیمت/موجودی واقعی از ووکامرس تولید می‌شود."
    />

    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">
      {{ page.props.flash.status }}
    </VAlert>
    <VAlert v-if="page.props.flash?.error" tone="danger" class="mt-6">
      {{ page.props.flash.error }}
    </VAlert>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <!-- فرم اصلی -->
      <VCard title="مشخصات محصول">
        <form class="space-y-5" @submit.prevent="submit">
          <VSelect
            v-model="f.site_id"
            label="سایت"
            :options="siteOptions"
            placeholder="سایت را انتخاب کنید"
            required
            hint="محتوا بر اساس دادهٔ سرچ کنسول و ووکامرس همین سایت ساخته می‌شود."
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
            hint="فقط پروفایل‌های نوع product (ووکامرس) نمایش داده می‌شوند."
            :error="f.errors.url_profile_id"
          />

          <VInput
            v-model="f.title"
            label="عنوان محصول"
            placeholder="مثال: هدفون بی‌سیم پرو نسل ۲۰۲۶"
            hint="اگر خالی بماند از عنوان فعلی محصول استفاده می‌شود."
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
            placeholder="انتخاب زیرنوع"
            hint="توضیح کوتاه (utch description)، توضیح بلند، مشخصات فنی یا مقایسه‌ای."
            :error="f.errors.subtype"
          />

          <div class="border-line flex items-center justify-between gap-3 border-t pt-5">
            <p class="text-ink-muted text-xs leading-6">
              ✅ اسکیمای Product با قیمت/موجودی واقعی · ✅ مشخصات فنی جدولی · ✅ لینک داخلی
            </p>
            <VButton type="submit" :loading="f.processing">
              <VIcon name="sparkles" size="sm" class="ms-1" />
              تولید پیشنویس محصول
            </VButton>
          </div>
        </form>
      </VCard>

      <!-- پیش‌نمایش -->
      <div class="space-y-6">
        <VCard v-if="currentStandard" title="📐 استاندارد مؤثر">
          <div class="space-y-3">
            <div>
              <p class="text-ink-strong text-sm font-semibold">بازهٔ کلمات</p>
              <VBadge tone="info">{{ currentStandard.word_min }} — {{ currentStandard.word_max ?? '∞' }} کلمه</VBadge>
            </div>
            <div>
              <p class="text-ink-strong text-sm font-semibold">لحن</p>
              <VBadge tone="success">{{ currentStandard.tone }}</VBadge>
            </div>
            <div>
              <p class="text-ink-strong text-sm font-semibold">نوع اسکیما</p>
              <VBadge tone="info">{{ currentStandard.schema_type }}</VBadge>
            </div>
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

        <VCard title="🛒 دادهٔ ووکامرس" description="قیمت، موجودی و مشخصات محصول از ووکامرس خوانده شده و در اسکیما و محتوا استفاده می‌شود.">
          <p class="text-ink-muted text-sm leading-7">
            اسکیمای Product شامل قیمت واقعی (regular/sale price)، وضعیت موجودی و لینک خرید خواهد بود.
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
