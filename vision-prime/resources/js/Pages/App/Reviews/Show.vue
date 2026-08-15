<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import type { PublishImpactReport } from '@/types/automation'
import VTrendChart from '@/shared/ui/VTrendChart.vue'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import { decisionLabels, labelOf, reviewStatusLabels, reviewSubjectLabels } from '@/lib/labels'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'

interface ReviewItem {
  id: number
  subject_type: string
  subject_id: number
  status: string
  created_at: string
}

interface ReviewDecision {
  id: number
  decision: string
  note: string | null
  decided_at: string
}

interface Draft {
  id: number
  kind: string
  text: string
  model: string
  source: string
  status: string
  createdAt: string
}

interface MoneyPageAuditSubject {
  kind: 'money_page_audit'
  audit: { id: number; score: number; summary: Record<string, unknown> | null; auditedAt: string } | null
  issues: { key: string; severity: string; explanation: string }[]
  url: string | null
  urlProfileId: number | null
  drafts: Draft[]
}

interface ArticleStructure {
  headings: { level: number; text: string }[]
  word_count: number
  elements: Record<string, boolean>
}

interface FeaturedImageSuggestion {
  alt: string
  suggested_width: number
  suggested_height: number
  aspect: string
  rationale: string
}

interface GenerationCommand {
  id: number
  type: string
  content_type: string | null
  status: string
  decision_source: string | null
  confidence_score: number | null
  confidence_factors: Record<string, unknown> | null
  gate_snapshot: Record<string, unknown> | null
  auto_approved: boolean
  published_at: string | null
  post_id: number | null
  post_url: string | null
  impact?: PublishImpactReport | null
}

interface WooProductInfo {
  post_id: number
  title: string
  post_type: string
  url: string | null
  is_product: boolean
  price: string | null
  regular_price: string | null
  sale_price: string | null
  currency: string | null
  stock_quantity: number | null
  stock_status: string | null
  in_stock: boolean | null
}

interface AiGenerationSubject {
  kind: 'ai_generation'
  generation: {
    id: number
    kind: string
    input: string | null
    text: string
    safe_html: string
    structure: ArticleStructure
    standard: Record<string, unknown> | null
    featured_image: FeaturedImageSuggestion | null
    schema: Record<string, unknown>[]
    model: string
    source: string
    status: string
    usage: Record<string, unknown> | null
    createdAt: string
    command: GenerationCommand | null
    woo_product: WooProductInfo | null
  } | null
}

interface CommandSubject {
  kind: 'command'
  command: { id: number; type: string; riskTier: string; payload: Record<string, unknown> | null; status: string; expiresAt: string | null } | null
}

interface UrlProfileSubject {
  kind: 'url_profile'
  profile: { canonicalUrl: string; slug: string; contentType: string } | null
}

type Subject = MoneyPageAuditSubject | AiGenerationSubject | CommandSubject | UrlProfileSubject

const p = defineProps<{
  item: ReviewItem
  decisions: ReviewDecision[]
  subject: Subject | null
}>()

const f = useForm({ decision: 'approved', note: '' })

function decide() {
  f.post(`/app/reviews/${p.item.id}/decision`)
}

const draftForm = useForm({ url_profile_id: 0, kind: 'meta_title' })
const articleForm = useForm({ url_profile_id: 0, title: '' })

function generateDraft(kind: 'meta_title' | 'meta_description'): void {
  if (p.subject?.kind !== 'money_page_audit' || !p.subject.urlProfileId) return
  draftForm.clearErrors()
  draftForm.url_profile_id = p.subject.urlProfileId
  draftForm.kind = kind
  draftForm.post('/app/ai-drafts', { preserveScroll: true })
}

function generateArticle(): void {
  if (p.subject?.kind !== 'money_page_audit' || !p.subject.urlProfileId) return
  articleForm.clearErrors()
  articleForm.url_profile_id = p.subject.urlProfileId
  articleForm.post('/app/ai-drafts/article', { preserveScroll: true })
}

const draftKindLabels: Record<string, string> = {
  meta_title: 'عنوان متا',
  meta_description: 'توضیحات متا',
  article: 'مقاله کامل',
}

const requiredElementLabels: Record<string, string> = {
  h2_structure: 'زیرعنوان‌ها (H2)',
  table: 'جدول',
  faq: 'سؤالات متداول',
  cta: 'دعوت به اقدام',
  pros_cons: 'مزایا/معایب',
  steps: 'مراحل',
  list: 'لیست',
  table_of_contents: 'فهرست مطالب',
  internal_links: 'لینک داخلی',
  keyword: 'کلیدواژه',
  specs: 'مشخصات',
  rating: 'امتیاز',
  social_proof: 'نظرات مشتریان',
  title_required: 'عنوان (H1)',
}

const elementTone = (present: boolean): 'success' | 'danger' | 'neutral' =>
  present ? 'success' : 'danger'

function structureElements(structure: ArticleStructure | undefined): [string, boolean][] {
  if (!structure) return []
  return Object.entries(structure.elements)
}

const commandStatusLabels: Record<string, string> = {
  pending_approval: 'در انتظار تأیید انسانی',
  queued: 'در صف',
  dispatched: 'در حال اجرا',
  executed: 'منتشر شد',
  failed: 'ناموفق',
  rolled_back: 'بازگشت خورده',
  cancelled: 'لغو شده',
}

function impactVerdictLabel(verdict: string | undefined): string {
  return verdict === 'improved' ? 'بهبود' : verdict === 'declined' ? 'افت' : 'پایدار'
}

function impactDelta(n: number | undefined): string {
  if (n === undefined) return '—'
  return n > 0 ? `+${n}` : String(n)
}

function gateRows(snapshot: Record<string, unknown>): [string, string][] {
  const labels: Record<string, string> = {
    auto_publish_scope: 'دامنه انتشار',
    warmup_count: 'گرمایش (موفق)',
    warmup_required: 'گرمایش (نیاز)',
    quality_score: 'امتیاز کیفیت',
    confidence_score: 'امتیاز اطمینان',
    confidence_threshold: 'آستانه اطمینان',
    max_risk_tier: 'سقف ریسک',
    automation_level: 'سطح خودکارسازی',
  }
  const rows: [string, string][] = []
  for (const [key, value] of Object.entries(snapshot)) {
    const label = labels[key]
    if (!label) continue
    if (value === null || value === undefined || value === '') continue
    rows.push([label, String(value)])
  }
  return rows
}

const issueSeverityLabels: Record<string, string> = {
  low: 'کم',
  medium: 'متوسط',
  high: 'زیاد',
  critical: 'بحرانی',
}

const issueSeverityTone: Record<string, 'neutral' | 'info' | 'success' | 'warning' | 'danger'> = {
  low: 'info',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
}

function payloadRows(payload: Record<string, unknown> | null): [string, unknown][] {
  if (!payload) return []
  return Object.entries(payload).filter(([, value]) => value !== null && value !== '')
}
</script>
<template>
  <Head title="جزئیات بررسی" />
  <AppLayout>
    <VPageHeader
      title="بررسی خروجی"
      :description="labelOf(reviewSubjectLabels, p.item.subject_type)"
    />

    <VCard class="mt-8" title="محتوا">
      <!-- money_page_audit -->
      <template v-if="p.subject?.kind === 'money_page_audit'">
        <div v-if="p.subject.audit" class="space-y-4">
          <div class="flex flex-wrap items-center gap-3">
            <VBadge tone="info">امتیاز: {{ p.subject.audit.score }}</VBadge>
            <span class="text-ink-muted text-sm">
              بازبینی در {{ formatJalaliDate(p.subject.audit.auditedAt) }}
            </span>
          </div>
          <p v-if="p.subject.url" class="font-latin text-sm" dir="ltr">
            {{ p.subject.url }}
          </p>
          <div v-if="p.subject.issues.length" class="space-y-2">
            <div
              v-for="issue in p.subject.issues"
              :key="issue.key"
              class="border-line flex items-start gap-3 rounded-ui border p-3"
            >
              <VBadge :tone="issueSeverityTone[issue.severity] ?? 'neutral'">
                {{ issueSeverityLabels[issue.severity] ?? issue.severity }}
              </VBadge>
              <p class="text-ink-strong text-sm">{{ issue.explanation }}</p>
            </div>
          </div>
          <p v-else class="text-ink-muted text-sm">مشکلی ثبت نشده است.</p>

          <div v-if="p.subject.urlProfileId" class="border-line mt-4 border-t pt-4">
            <p class="text-ink-strong mb-2 text-sm font-semibold">تولید پیشنویس با هوش مصنوعی</p>
            <div class="flex flex-wrap gap-2">
              <VButton size="sm" :loading="draftForm.processing && draftForm.kind === 'meta_title'" @click="generateDraft('meta_title')">
                پیشنویس عنوان متا
              </VButton>
              <VButton
                size="sm"
                variant="secondary"
                :loading="draftForm.processing && draftForm.kind === 'meta_description'"
                @click="generateDraft('meta_description')"
              >
                پیشنویس توضیحات متا
              </VButton>
              <VButton
                size="sm"
                variant="secondary"
                :loading="articleForm.processing"
                @click="generateArticle"
              >
                پیشنویس مقاله کامل
              </VButton>
            </div>
            <p class="text-ink-muted mt-2 text-xs">
              پیشنویس تولیدشده با ContentProfiler (زیرنوع/قصد) و استاندارد مؤثر استانداردKB ساخته و وارد صف «بررسی و تأییدها» می‌شود.
            </p>
          </div>

          <div v-if="p.subject.drafts.length" class="mt-5 space-y-3">
            <p class="text-ink-strong text-sm font-semibold">پیشنویس‌های تولیدشده برای این صفحه</p>
            <div
              v-for="draft in p.subject.drafts"
              :key="draft.id"
              class="border-line rounded-ui border p-3"
            >
              <div class="flex items-center justify-between gap-2">
                <VBadge tone="info">{{ draftKindLabels[draft.kind] ?? draft.kind }}</VBadge>
                <span class="text-ink-muted text-xs">{{ formatJalaliDate(draft.createdAt) }}</span>
              </div>
              <p class="text-ink-strong mt-2 text-sm leading-6">{{ draft.text }}</p>
              <p class="text-ink-muted mt-1 text-xs" dir="ltr">
                {{ draft.source === 'ai' ? draft.model : 'پیشنویس داخلی' }}
              </p>
            </div>
          </div>
        </div>
        <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
      </template>

      <!-- ai_generation -->
      <template v-else-if="p.subject?.kind === 'ai_generation'">
        <div v-if="p.subject.generation" class="space-y-4">
          <!-- مقاله/محصول: رندر HTML امن + پیش‌نمایش ساختار -->
          <template v-if="p.subject.generation.kind === 'article' || p.subject.generation.kind === 'product'">
            <div
              v-if="p.subject.generation.structure?.headings.length || p.subject.generation.structure?.elements"
              class="border-line grid gap-4 rounded-ui border p-4 sm:grid-cols-2"
            >
              <div>
                <p class="text-ink-strong mb-2 text-sm font-semibold">
                  ساختار مقاله · {{ p.subject.generation.structure?.word_count ?? 0 }} کلمه
                </p>
                <ul v-if="p.subject.generation.structure?.headings.length" class="space-y-1">
                  <li
                    v-for="(heading, index) in p.subject.generation.structure.headings"
                    :key="index"
                    class="text-ink-muted text-sm"
                    :class="heading.level === 1 ? 'font-semibold' : heading.level === 2 ? 'ps-3' : 'ps-6'"
                  >
                    {{ 'H'.concat(String(heading.level)) }} · {{ heading.text }}
                  </li>
                </ul>
              </div>
              <div v-if="structureElements(p.subject.generation.structure).length">
                <p class="text-ink-strong mb-2 text-sm font-semibold">عناصر الزامی استاندارد</p>
                <div class="flex flex-wrap gap-2">
                  <VBadge
                    v-for="[key, present] in structureElements(p.subject.generation.structure)"
                    :key="key"
                    :tone="elementTone(present)"
                  >
                    {{ requiredElementLabels[key] ?? key }}: {{ present ? '✓' : '✗' }}
                  </VBadge>
                </div>
              </div>
            </div>
            <!-- تصویر شاخص پیشنهادی -->
            <div
              v-if="p.subject.generation.featured_image"
              class="border-line rounded-ui border p-4"
            >
              <p class="text-ink-strong mb-2 text-sm font-semibold">🖼️ تصویر شاخص پیشنهادی</p>
              <div class="flex flex-wrap gap-2">
                <VBadge tone="info">{{ p.subject.generation.featured_image.aspect }}</VBadge>
                <VBadge tone="neutral">
                  {{ p.subject.generation.featured_image.suggested_width }}×{{ p.subject.generation.featured_image.suggested_height }}
                </VBadge>
              </div>
              <p class="text-ink-muted mt-2 text-xs" dir="rtl">
                متن جایگزین: «{{ p.subject.generation.featured_image.alt }}»
              </p>
              <p class="text-ink-muted mt-1 text-sm leading-6">
                {{ p.subject.generation.featured_image.rationale }}
              </p>
            </div>

            <!-- اسکیمای Schema.org -->
            <div v-if="p.subject.generation.schema.length" class="space-y-3">
              <p class="text-ink-strong text-sm font-semibold">📊 اسکیمای Schema.org پیشنهادی</p>
              <div
                v-for="(node, index) in p.subject.generation.schema"
                :key="index"
                class="border-line rounded-ui border p-3"
              >
                <div class="mb-2 flex items-center justify-between gap-2">
                  <VBadge tone="info">{{ node['@type'] ?? 'Schema' }}</VBadge>
                  <span class="text-ink-muted text-xs" dir="ltr">schema.org/{{ node['@type'] ?? '' }}</span>
                </div>
                <pre class="bg-surface-muted text-ink-muted font-latin max-h-56 overflow-auto rounded-ui p-3 text-xs" dir="ltr">{{ JSON.stringify(node, null, 2) }}</pre>
              </div>
            </div>

            <!-- safe_html از ArticleHtmlSanitizer سمت سرور پاک‌سازی می‌شود -->
            <!-- eslint-disable vue/no-v-html -->
            <div
              v-if="p.subject.generation.safe_html"
              class="border-line rounded-ui article-preview border p-5"
              v-html="p.subject.generation.safe_html"
            />
            <!-- eslint-enable vue/no-v-html -->
            <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
          </template>

          <!-- متا: متن ساده -->
          <template v-else>
            <p
              v-if="p.subject.generation.text"
              class="text-ink-strong text-sm leading-7 whitespace-pre-wrap"
            >
              {{ p.subject.generation.text }}
            </p>
            <p v-else-if="p.subject.generation.input" class="text-ink-strong text-sm whitespace-pre-wrap">
              {{ p.subject.generation.input }}
            </p>
            <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
          </template>            <p class="text-ink-muted text-sm">
              وضعیت: {{ p.subject.generation.status }} · ایجاد در
              {{ formatJalaliDate(p.subject.generation.createdAt) }}
              <span v-if="p.subject.generation.model" dir="ltr">· {{ p.subject.generation.model }}</span>
            </p>

            <!-- دادهٔ واقعی ووکامرس (قیمت/موجودی) -->
            <div v-if="p.subject.generation.woo_product" class="border-line rounded-ui border p-4">
              <p class="text-ink-strong mb-2 text-sm font-semibold">🛒 دادهٔ واقعی ووکامرس</p>
              <div class="flex flex-wrap items-center gap-2">
                <VBadge tone="info">
                  {{ p.subject.generation.woo_product.price !== null ? p.subject.generation.woo_product.price + ' ' + (p.subject.generation.woo_product.currency ?? '') : 'بدون قیمت' }}
                </VBadge>
                <VBadge
                  :tone="p.subject.generation.woo_product.in_stock ? 'success' : 'danger'"
                >
                  {{ p.subject.generation.woo_product.in_stock ? 'موجود' : 'ناموجود' }}
                </VBadge>
                <VBadge
                  v-if="p.subject.generation.woo_product.stock_quantity !== null"
                  tone="neutral"
                >
                  موجودی: {{ p.subject.generation.woo_product.stock_quantity }}
                </VBadge>
              </div>
              <p class="text-ink-muted mt-2 text-sm">
                {{ p.subject.generation.woo_product.title }}
                <span v-if="p.subject.generation.woo_product.url" dir="ltr" class="font-latin ms-1">
                  · {{ p.subject.generation.woo_product.url }}
                </span>
              </p>
              <p class="text-ink-muted mt-1 text-xs">
                قیمت واقعی محصول از ووکامرس — برای تصمیم آگاهانهٔ بازبین پیش از انتشار.
              </p>
            </div>

            <!-- ویجت وضعیت بلادرنگ کامند فاز ۲ -->
            <div
              v-if="p.subject.generation.command"
              class="border-line rounded-ui border p-4"
            >
              <p class="text-ink-strong mb-2 text-sm font-semibold">🚀 وضعیت انتشار خودکار</p>
              <div class="flex flex-wrap items-center gap-2">
                <VBadge
                  :tone="
                    p.subject.generation.command.status === 'executed'
                      ? 'success'
                      : p.subject.generation.command.status === 'rolled_back'
                        ? 'warning'
                        : p.subject.generation.command.status === 'pending_approval'
                          ? 'warning'
                          : 'info'
                  "
                >
                  {{ labelOf(commandStatusLabels, p.subject.generation.command.status) }}
                </VBadge>
                <VBadge v-if="p.subject.generation.command.auto_approved" tone="success">
                  انتشار خودکار تأیید شد
                </VBadge>
                <VBadge
                  v-if="p.subject.generation.command.confidence_score !== null"
                  tone="neutral"
                >
                  اطمینان: {{ p.subject.generation.command.confidence_score }}
                </VBadge>
                <span class="text-ink-muted text-sm" dir="ltr">#{{ p.subject.generation.command.id }}</span>
              </div>
              <div
                v-if="p.subject.generation.command.gate_snapshot"
                class="mt-2 flex flex-wrap gap-2"
              >
                <VBadge
                  v-for="[label, value] in gateRows(p.subject.generation.command.gate_snapshot)"
                  :key="label"
                  tone="neutral"
                >
                  {{ label }}: {{ value }}
                </VBadge>
              </div>
              <div v-if="p.subject.generation.command.post_url" class="mt-2">
                <span class="text-ink-strong text-sm">📄 مقالهٔ منتشرشده:</span>
                <a
                  :href="p.subject.generation.command.post_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-latin ms-2 text-sm text-brand-700 underline hover:text-brand-900"
                  dir="ltr"
                >
                  {{ p.subject.generation.command.post_url }}
                </a>
              </div>
              <p
                v-else-if="p.subject.generation.command.status === 'pending_approval'"
                class="text-ink-muted mt-2 text-sm"
              >
                کامند ساخته شد و در صف «تغییرات اجرایی» منتظر تأیید انسانی است.
              </p>

              <!-- گزارش تأثیر پس از انتشار (GSC) -->
              <div v-if="p.subject.generation.command.impact" class="mt-3 border-t border-gray-100 pt-3">
                <template v-if="p.subject.generation.command.impact.status === 'ready'">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="text-ink-strong text-sm font-semibold">📈 تأثیر پس از انتشار</span>
                    <VBadge
                      :tone="
                        p.subject.generation.command.impact.verdict === 'improved'
                          ? 'success'
                          : p.subject.generation.command.impact.verdict === 'declined'
                            ? 'danger'
                            : 'neutral'
                      "
                    >
                      {{ impactVerdictLabel(p.subject.generation.command.impact.verdict) }}
                    </VBadge>
                  </div>
                  <div class="text-ink-muted mt-1 flex flex-wrap gap-x-4 text-sm">
                    <span>
                      جایگاه:
                      <b class="text-ink-strong" dir="ltr">
                        {{ impactDelta(p.subject.generation.command.impact.delta?.position) }}
                      </b>
                    </span>
                    <span>
                      کلیک:
                      <b class="text-ink-strong" dir="ltr">
                        {{ impactDelta(p.subject.generation.command.impact.delta?.clicks) }}
                      </b>
                    </span>
                    <span>
                      نمایش:
                      <b class="text-ink-strong" dir="ltr">
                        {{ impactDelta(p.subject.generation.command.impact.delta?.impressions) }}
                      </b>
                    </span>
                  </div>
                  <VTrendChart
                    v-if="
                      p.subject.generation.command.impact.series &&
                      p.subject.generation.command.impact.series.length
                    "
                    class="mt-3"
                    :points="p.subject.generation.command.impact.series"
                    :publish-date="p.subject.generation.command.impact.published_at ?? ''"
                  />
                </template>
                <p v-else class="text-ink-muted text-sm">
                  دادهٔ GSC کافی برای مقایسه در دسترس نیست.
                </p>
              </div>
            </div>
        </div>
        <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
      </template>

      <!-- command -->
      <template v-else-if="p.subject?.kind === 'command'">
        <div v-if="p.subject.command" class="space-y-3">
          <p class="text-ink-strong text-sm">
            {{ labelOf(reviewSubjectLabels, p.subject.command.type) }} (ریسک:
            {{ p.subject.command.riskTier }})
          </p>
          <div v-if="payloadRows(p.subject.command.payload).length" class="space-y-1">
            <div
              v-for="[key, value] in payloadRows(p.subject.command.payload)"
              :key="key"
              class="text-ink-muted text-sm"
            >
              <span class="text-ink-strong">{{ key }}:</span> {{ String(value) }}
            </div>
          </div>
        </div>
        <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
      </template>

      <!-- url_profile -->
      <template v-else-if="p.subject?.kind === 'url_profile'">
        <div v-if="p.subject.profile" class="space-y-2">
          <p class="font-latin text-sm" dir="ltr">{{ p.subject.profile.canonicalUrl }}</p>
          <p class="text-ink-muted text-sm">
            نوع محتوا: {{ p.subject.profile.contentType }} · اسلاگ: {{ p.subject.profile.slug }}
          </p>
        </div>
        <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
      </template>

      <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
    </VCard>

    <VCard class="mt-6" title="وضعیت">
      <VBadge :tone="p.item.status === 'approved' ? 'success' : p.item.status === 'rejected' ? 'danger' : 'warning'">
        {{ labelOf(reviewStatusLabels, p.item.status) }}
      </VBadge>
    </VCard>

    <VCard class="mt-6" title="تصمیم">
      <form class="space-y-4" @submit.prevent="decide">
        <select v-model="f.decision" class="w-full">
          <option value="approved">تأیید</option>
          <option value="rejected">رد</option>
          <option value="changes_requested">درخواست تغییر</option>
        </select>
        <VTextarea v-model="f.note" label="یادداشت تصمیم" />
        <VButton type="submit" :loading="f.processing">ثبت تصمیم</VButton>
      </form>
    </VCard>

    <VCard class="mt-6" title="تاریخچه تصمیم">
      <div v-if="decisions.length">
        <div v-for="d in decisions" :key="d.id" class="border-line border-b py-3">
          <div class="flex items-center gap-2">
            <VBadge :tone="d.decision === 'approved' ? 'success' : d.decision === 'rejected' ? 'danger' : 'warning'">
              {{ labelOf(decisionLabels, d.decision) }}
            </VBadge>
            <span class="text-ink-muted text-sm">{{ formatJalaliDate(d.decided_at) }}</span>
          </div>
          <p v-if="d.note" class="text-ink-muted mt-1 text-sm">{{ d.note }}</p>
        </div>
      </div>
      <p v-else class="text-ink-muted">هنوز تصمیمی ثبت نشده است.</p>
    </VCard>
  </AppLayout>
</template>

<style scoped>
.article-preview :deep(h1) {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
  line-height: 2;
}

.article-preview :deep(h2) {
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.5rem;
  line-height: 2;
}

.article-preview :deep(h3) {
  font-size: 1.1rem;
  font-weight: 600;
  margin-top: 1.25rem;
  margin-bottom: 0.5rem;
}

.article-preview :deep(p) {
  margin-bottom: 0.75rem;
  line-height: 2;
  font-size: 0.9rem;
}

.article-preview :deep(ul),
.article-preview :deep(ol) {
  margin: 0.5rem 0 0.75rem;
  padding-inline-start: 1.5rem;
  list-style: revert;
}

.article-preview :deep(li) {
  margin-bottom: 0.25rem;
  line-height: 1.9;
}

.article-preview :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 0.75rem 0;
  font-size: 0.85rem;
}

.article-preview :deep(th),
.article-preview :deep(td) {
  border: 1px solid var(--color-line, #e5e7eb);
  padding: 0.5rem 0.75rem;
  text-align: start;
}

.article-preview :deep(th) {
  font-weight: 600;
  background: var(--color-surface-muted, #f9fafb);
}

.article-preview :deep(a) {
  color: var(--color-brand-600, #4f46e5);
  text-decoration: underline;
}

.article-preview :deep(blockquote) {
  border-inline-start: 3px solid var(--color-line, #e5e7eb);
  padding-inline-start: 1rem;
  margin: 0.75rem 0;
  font-style: italic;
}
</style>
