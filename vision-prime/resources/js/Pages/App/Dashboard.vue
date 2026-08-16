<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import { commandTypeLabels, labelOf, reviewSubjectLabels } from '@/lib/labels'
import VAlert from '@/shared/ui/VAlert.vue'
import VBarChart from '@/shared/ui/VBarChart.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VStatCard, { type StatTrend } from '@/shared/ui/VStatCard.vue'
import VIcon, { type IconName, type IconTone } from '@/shared/ui/VIcon.vue'
import type { ContentImpactSummary, ImpactSummaryEntry } from '@/types/automation'
import type { AppPageProps } from '@/types/app'

const props = defineProps<{
  counts: {
    clients: number
    projects: number
    sites: number
    connectedSites: number
    gscConnectedSites: number
    openOpportunities: number
    pendingCommands: number
    pendingReviews: number
    scheduledPublishes: number
  }
  contentImpact: ContentImpactSummary
  publishSuggestions: {
    site_id: number
    site_name: string
    label: string
    hour: number
    datetime: string
    source: string
  }[]
  trend: { date: string; clicks: number; impressions: number; position: number }[]
  kpis: {
    clicks: { value: number | null; delta: number | null }
    impressions: { value: number | null; delta: number | null }
    position: { value: number | null; delta: number | null }
  } | null
  approvalQueue: {
    type: 'command' | 'review'
    id: number
    label: string
    site_name: string
    created_at: string | null
  }[]
  activities: {
    id: number
    action: string
    actorName: string
    subjectType: string | null
    subjectId: number | null
    occurredAt: string | null
  }[]
}>()

const page = usePage<AppPageProps & { currentRole?: string; auth?: { user?: { name: string } } }>()
const role = computed(() => page.props.currentRole ?? 'agency-admin')

// —— بنر نقش (per-role) ——
interface RoleMeta {
  label: string
  message: string
  icon: IconName
  priorities: { label: string; hint: string; href: string; icon: IconName }[]
}

const roleMeta: Record<string, RoleMeta> = {
  'agency-admin': {
    label: 'مدیر آژانس',
    message: 'نمای کلی همهٔ مشتریان، تیم و عملکرد سازمان.',
    icon: 'building',
    priorities: [
      { label: 'مشتریان', hint: 'حساب‌ها و دسترسی‌ها', href: '/app/clients', icon: 'building' },
      { label: 'سایت‌های متصل', hint: 'وضعیت اتصال‌ها', href: '/app/sites', icon: 'activity' },
      { label: 'بررسی و تأییدها', hint: 'صف تصمیم‌ها', href: '/app/reviews', icon: 'user-check' },
      { label: 'تنظیمات سازمان', hint: 'اعضا و نقش‌ها', href: '/app/settings/organization', icon: 'users' },
    ],
  },
  'seo-manager': {
    label: 'کارشناس سئو',
    message: 'فرصت‌ها، ریسک‌ها و دادهٔ واقعی رشد در کانون کار شماست.',
    icon: 'trend-up',
    priorities: [
      { label: 'فرصت‌های رشد', hint: 'اولویت‌های پیشنهادی', href: '/app/opportunities', icon: 'lightbulb' },
      { label: 'صفحات درآمدزا', hint: 'صفحات کلیدی', href: '/app/money-pages', icon: 'shopping-bag' },
      { label: 'ریسک‌های تبدیل', hint: 'نقاط خطر', href: '/app/conversion-risks', icon: 'trend-down' },
      { label: 'سرچ کنسول', hint: 'دادهٔ واقعی', href: '/app/gsc', icon: 'search' },
    ],
  },
  'content-manager': {
    label: 'مدیر محتوا',
    message: 'تولید و بازبینی مقاله و محصول، و برنامه‌ریزی انتشار.',
    icon: 'file',
    priorities: [
      { label: 'تقویم محتوایی', hint: 'برنامهٔ انتشار', href: '/app/content-calendar', icon: 'calendar' },
      { label: 'تولید مقاله', hint: 'پیش‌نویس جدید', href: '/app/ai-drafts/article/create', icon: 'file' },
      { label: 'تولید محصول', hint: 'پیش‌نویس محصول', href: '/app/ai-drafts/product/create', icon: 'shopping-bag' },
      { label: 'بررسی و تأییدها', hint: 'صف بازبینی', href: '/app/reviews', icon: 'user-check' },
    ],
  },
  'expert-reviewer': {
    label: 'بازبین',
    message: 'صف تأیید سریع و دقیق، بدون شلوغی.',
    icon: 'user-check',
    priorities: [
      { label: 'بررسی و تأییدها', hint: 'موارد در انتظار', href: '/app/reviews', icon: 'user-check' },
      { label: 'تغییرات اجرایی', hint: 'اجراها و بازگشت‌ها', href: '/app/commands', icon: 'zap' },
      { label: 'پیشنهادها', hint: 'پیشنهادهای تیم', href: '/app/recommendations', icon: 'lightbulb' },
    ],
  },
  developer: {
    label: 'توسعه‌دهنده',
    message: 'اتصال‌ها، سلامت فنی و اجرای تغییرات.',
    icon: 'zap',
    priorities: [
      { label: 'سایت‌ها', hint: 'وضعیت اتصال‌ها', href: '/app/sites', icon: 'activity' },
      { label: 'یکپارچه‌سازی‌ها', hint: 'سرچ کنسول و وردپرس', href: '/app/settings/integrations', icon: 'zap' },
      { label: 'تغییرات اجرایی', hint: 'لاگ اجرا', href: '/app/commands', icon: 'zap' },
      { label: 'گزارش ممیزی', hint: 'ردپیگری کامل', href: '/app/settings/audit-log', icon: 'shield' },
    ],
  },
  'marketing-manager': {
    label: 'مدیر بازاریابی',
    message: 'لیدها، فانل و کمپین‌ها — رشد از نگاه بازاریابی.',
    icon: 'megaphone',
    priorities: [
      { label: 'لیدها و تبلیغات', hint: 'دادهٔ کمپین', href: '/app/marketing', icon: 'megaphone' },
      { label: 'مشتریان', hint: 'حساب‌ها', href: '/app/clients', icon: 'building' },
      { label: 'گزارش‌ها', hint: 'گزارش‌های دوره‌ای', href: '/app/reports', icon: 'news' },
    ],
  },
  'super-admin': {
    label: 'مدیر ارشد',
    message: 'نظارت کل بر پلتفرم و سازمان‌ها.',
    icon: 'shield',
    priorities: [
      { label: 'مشتریان', hint: 'همهٔ حساب‌ها', href: '/app/clients', icon: 'building' },
      { label: 'سایت‌ها', hint: 'وضعیت اتصال‌ها', href: '/app/sites', icon: 'activity' },
      { label: 'تنظیمات سازمان', hint: 'اعضا و نقش‌ها', href: '/app/settings/organization', icon: 'users' },
    ],
  },
}

const genericMeta: RoleMeta = {
  label: 'عضو تیم',
  message: 'به فضای کاری خوش آمدید. بخش‌های مرتبط با کار شما در اینجا جمع شده‌اند.',
  icon: 'users',
  priorities: [
    { label: 'مشتریان', hint: 'همهٔ حساب‌ها', href: '/app/clients', icon: 'building' },
    { label: 'فرصت‌های رشد', hint: 'اولویت‌ها', href: '/app/opportunities', icon: 'lightbulb' },
    { label: 'بررسی و تأییدها', hint: 'صف تصمیم‌ها', href: '/app/reviews', icon: 'user-check' },
    { label: 'سایت‌ها', hint: 'وضعیت اتصال‌ها', href: '/app/sites', icon: 'activity' },
  ],
}

const meta = computed<RoleMeta>(() => roleMeta[role.value] ?? genericMeta)
const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'همکار')

// —— KPI ها ——
const pendingTotal = computed(() => props.counts.pendingCommands + props.counts.pendingReviews)

const kpiCards = computed(() => [
  {
    label: 'مشتریان فعال',
    value: props.counts.clients,
    icon: 'building' as IconName,
    iconTone: 'brand' as IconTone,
    hint: 'حساب‌هایی که آژانس برای آن‌ها پروژه و پرتال مدیریت می‌کند.',
    trend: 'flat' as StatTrend,
    trendLabel: props.counts.projects > 0 ? `${props.counts.projects} پروژه فعال` : 'هنوز پروژه‌ای نیست',
  },
  {
    label: 'سایت‌های متصل',
    value: props.counts.connectedSites,
    icon: 'activity' as IconName,
    iconTone: 'violet' as IconTone,
    hint: 'سایت‌هایی که به وردپرس و سرچ کنسول متصل شده‌اند.',
    trend: 'flat' as StatTrend,
    trendLabel: `${props.counts.sites} سایت کل · ${props.counts.gscConnectedSites} با سرچ کنسول`,
  },
  {
    label: 'فرصت‌های باز',
    value: props.counts.openOpportunities,
    icon: 'lightbulb' as IconName,
    iconTone: 'success' as IconTone,
    hint: 'فرصت‌های رشد شناسایی‌شده که هنوز اقدام نشده‌اند.',
    trend: 'flat' as StatTrend,
    trendLabel: pendingTotal.value > 0 ? `${pendingTotal.value} مورد در صف تأیید` : 'صف تأیید خالی است',
  },
  {
    label: 'در انتظار تأیید',
    value: pendingTotal.value,
    icon: 'user-check' as IconName,
    iconTone: 'warning' as IconTone,
    hint: 'تغییرات اجرایی و بازبینی‌هایی که منتظر تصمیم تیم هستند.',
    trend: 'flat' as StatTrend,
    trendLabel: props.counts.scheduledPublishes > 0
      ? `${props.counts.scheduledPublishes} انتشار زمان‌بندی‌شده`
      : 'نیاز به بررسی دارد',
  },
])

// —— چارت رشد ——
const growthBars = computed(() =>
  props.trend.map((point, index) => ({
    label:
      index % 5 === 0 || index === props.trend.length - 1
        ? (formatJalaliDate(point.date).split('/')[2] ?? '')
        : '',
    value: point.clicks,
  })),
)

// —— صف تأیید ——
const approvalLabel = (item: { type: 'command' | 'review'; label: string }): string =>
  item.type === 'command' ? labelOf(commandTypeLabels, item.label) : labelOf(reviewSubjectLabels, item.label)

// —— فعالیت‌ها و بقیهٔ بخش‌ها (از قبل) ——
const declinedEntries = computed(() => props.contentImpact.declines ?? [])

const declineSummary = (entry: ImpactSummaryEntry) => {
  const parts: string[] = []
  if (entry.delta.clicks !== 0) parts.push(`کلیک ${entry.delta.clicks > 0 ? '+' : ''}${entry.delta.clicks}`)
  if (entry.delta.position !== 0) parts.push(`جایگاه ${entry.delta.position > 0 ? '+' : ''}${entry.delta.position}`)
  return parts.length ? parts.join(' · ') : 'بدون تغییر محسوس'
}

const sign = (n: number) => (n > 0 ? `+${n}` : String(n))

const formatUrl = (url: string) => {
  try {
    return new URL(url).pathname
  } catch {
    return url
  }
}

const actionLabels: Record<string, string> = {
  'client.created': 'مشتری ایجاد شد',
  'client.updated': 'اطلاعات مشتری به‌روزرسانی شد',
  'client.archived': 'مشتری بایگانی شد',
  'project.created': 'پروژه ایجاد شد',
  'project.updated': 'پروژه به‌روزرسانی شد',
  'project.archived': 'پروژه بایگانی شد',
  'site.created': 'سایت اضافه شد',
  'site.updated': 'سایت به‌روزرسانی شد',
  'site.archived': 'سایت بایگانی شد',
  'organization.created': 'سازمان ایجاد شد',
  'auth.registered': 'ثبت‌نام کاربر جدید',
  'auth.login_succeeded': 'ورود موفق',
  'auth.logout': 'خروج از حساب',
  'gsc.account_connected': 'حساب سرچ کنسول متصل شد',
  'gsc.property_selected': 'ملک سرچ کنسول انتخاب شد',
  'gsc.import_completed': 'همگام‌سازی سرچ کنسول تکمیل شد',
  'gsc.import_failed': 'همگام‌سازی سرچ کنسول ناموفق بود',
  'gsc.analysis_completed': 'تحلیل رشد روی داده‌ها اجرا شد',
  'connector.paired': 'سایت به وردپرس متصل شد',
  'connector.pairing_token_created': 'توکن اتصال وردپرس ایجاد شد',
  'connector.disconnected': 'اتصال وردپرس قطع شد',
  'sync.completed': 'همگام‌سازی محتوا تکمیل شد',
  'sync.failed': 'همگام‌سازی محتوا ناموفق بود',
  'opportunity.created': 'فرصت رشد جدید شناسایی شد',
  'opportunity.updated': 'فرصت رشد به‌روزرسانی شد',
  'recommendation.created': 'توصیه ایجاد شد',
  'recommendation.created_from_opportunity': 'توصیه از فرصت رشد ایجاد شد',
  'recommendation.updated': 'توصیه به‌روزرسانی شد',
  'command.created': 'تغییر اجرایی پیشنهاد شد',
  'command.created_from_recommendation': 'تغییر اجرایی از توصیه ساخته شد',
  'command.approval_decided': 'تصمیم تأیید تغییر ثبت شد',
  'command.dispatched': 'تغییر اجرایی به‌اجرا درآمد',
  'command.executed': 'تغییر اجرایی اجرا شد',
  'command.failed': 'اجرای تغییر ناموفق بود',
  'command.publish_now': 'انتشار فوری پیش‌نویس انجام شد',
  'command.publish_scheduled': 'انتشار پیش‌نویس زمان‌بندی شد',
  'command.publish_schedule_cancelled': 'زمان‌بندی انتشار لغو شد',
  'review.created': 'مورد بازبینی جدید ایجاد شد',
  'review.item_created': 'مورد بازبینی جدید ایجاد شد',
  'review.decided': 'بازبینی تصمیم‌گیری شد',
  'ai.generation_created': 'پیش‌نویس هوش مصنوعی تولید شد',
  'ai.draft_generated': 'پیش‌نویس متا تولید شد',
  'ai.provider_setting_saved': 'پیکربندی هوش مصنوعی ذخیره شد',
  'report.created': 'گزارش جدید ایجاد شد',
  'report.published': 'گزارش برای مشتری منتشر شد',
}

const nextStep = computed(() => {
  if (props.counts.clients === 0)
    return {
      title: 'اولین مشتری را اضافه کنید',
      description: 'با ثبت مشتری، می‌توانید پروژه‌ها و دسترسی پرتال او را مدیریت کنید.',
      label: 'افزودن مشتری',
      href: '/app/clients/create',
    }
  if (props.counts.projects === 0)
    return {
      title: 'برای مشتری یک پروژه بسازید',
      description: 'پروژه، هدف رشد و سایت‌های مرتبط را در یک فضای عملیاتی نگه می‌دارد.',
      label: 'ایجاد پروژه',
      href: '/app/projects/create',
    }
  if (props.counts.sites === 0)
    return {
      title: 'اولین سایت را اضافه کنید',
      description: 'پس از ایجاد سایت، اتصال سرچ کنسول و وردپرس را شروع می‌کنید.',
      label: 'افزودن سایت',
      href: '/app/sites/create',
    }
  if (props.counts.gscConnectedSites === 0)
    return {
      title: 'سرچ کنسول را متصل کنید',
      description:
        'با اتصال داده‌های جستجو، سیستم می‌تواند فرصت‌های رشد واقعی را برای سایت‌های شما شناسایی کند.',
      label: 'اتصال سرچ کنسول',
      href: '/app/gsc',
    }
  if (props.counts.openOpportunities === 0)
    return {
      title: 'تحلیل رشد را اجرا کنید',
      description:
        'داده‌های سرچ کنسول آماده‌اند؛ با اجرای تحلیل، فرصت‌ها و ریسک‌های هر صفحه شناسایی می‌شوند.',
      label: 'اجرای تحلیل رشد',
      href: '/app/gsc',
    }
  return {
    title: 'فرصت‌های رشد آماده بررسی هستند',
    description: 'فرصت‌های اولویت‌دار را مرور کنید و اقدامات پیشنهادی را تأیید یا رد کنید.',
    label: 'مشاهده فرصت‌ها',
    href: '/app/opportunities',
  }
})
</script>

<template>
  <Head title="داشبورد | سوئیت" />
  <AppLayout>
    <VPageHeader
      :title="`سلام، ${firstName} 👋`"
      :description="`${meta.message} اینجا اولویت‌های نقش «${meta.label}» برای شما چیده شده است.`"
      :status="{ label: 'فضای کاری فعال', tone: 'success' }"
    />

    <VAlert
      v-if="declinedEntries.length"
      class="mt-6"
      tone="danger"
      :title="`⚠️ هشدار افت عملکرد — ${declinedEntries.length} محتوا پس از انتشار افت کرده است`"
    >
      <ul class="list-inside list-disc space-y-1">
        <li v-for="entry in declinedEntries" :key="entry.command_id" class="text-sm">
          <span class="font-semibold">{{ entry.site_name ?? '—' }}</span>
          <span class="font-latin" dir="ltr">{{ formatUrl(entry.url) }}</span>
          <span class="text-ink-muted">— {{ declineSummary(entry) }}</span>
        </li>
      </ul>
    </VAlert>

    <!-- KPI متحرک -->
    <section v-stagger class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <VStatCard
        v-for="card in kpiCards"
        :key="card.label"
        :label="card.label"
        :value="card.value"
        :icon="card.icon"
        :icon-tone="card.iconTone"
        :hint="card.hint"
        :trend="card.trend"
        :trend-label="card.trendLabel"
      />
    </section>

    <!-- اولویت‌های نقش من (per-role) -->
    <section class="mt-8">
      <div class="flex items-center gap-2">
        <span class="rounded-ui bg-brand-50 text-brand-700 flex size-8 items-center justify-center">
          <VIcon :name="meta.icon" size="sm" />
        </span>
        <h2 class="text-ink-strong text-lg font-bold">اولویت‌های «{{ meta.label }}»</h2>
      </div>
      <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a
          v-for="priority in meta.priorities"
          :key="priority.href"
          :href="priority.href"
          class="transition-ui rounded-panel border-line bg-surface group flex items-start gap-3 border p-4 hover:-translate-y-0.5 hover:shadow-card"
        >
          <span class="rounded-ui bg-surface-muted text-ink-muted group-hover:bg-brand-50 group-hover:text-brand-700 flex size-10 shrink-0 items-center justify-center transition-colors">
            <VIcon :name="priority.icon" size="lg" />
          </span>
          <span class="min-w-0">
            <span class="text-ink-strong block text-sm font-bold">{{ priority.label }}</span>
            <span class="text-ink-muted mt-0.5 block text-xs">{{ priority.hint }}</span>
          </span>
        </a>
      </div>
    </section>

    <!-- صف تأیید -->
    <VCard class="mt-8" title="صف تأیید" description="تغییرات و بازبینی‌هایی که منتظر تصمیم تیم هستند.">
      <div v-if="approvalQueue.length" class="space-y-1">
        <div
          v-for="item in approvalQueue"
          :key="`${item.type}-${item.id}`"
          class="flex items-center justify-between gap-3 rounded-ui px-2 py-2 hover:bg-surface-muted"
        >
          <div class="flex min-w-0 items-center gap-3">
            <span
              :class="[
                'rounded-ui flex size-8 shrink-0 items-center justify-center',
                item.type === 'command' ? 'bg-warning-50 text-warning-600' : 'bg-violet-50 text-violet-600',
              ]"
            >
              <VIcon :name="item.type === 'command' ? 'zap' : 'eye'" size="sm" />
            </span>
            <div class="min-w-0">
              <p class="text-ink-strong truncate text-sm font-semibold">{{ approvalLabel(item) }}</p>
              <p class="text-ink-muted text-xs">{{ item.site_name }} · از {{ formatJalaliDate(item.created_at) }}</p>
            </div>
          </div>
        </div>
        <div class="pt-2">
          <VButton href="/app/reviews" size="sm" variant="secondary">مشاهدهٔ همه در بررسی و تأییدها</VButton>
        </div>
      </div>
      <p v-else class="text-ink-muted text-sm leading-7">صف تأیید خالی است — همه‌چیز به‌روز است. ✅</p>
    </VCard>

    <!-- چارت رشد -->
    <VCard
      class="mt-8"
      title="بازدید روزانه از گوگل"
      description="مجموع کلیک‌های واقعی سرچ کنسول همهٔ سایت‌های سازمان — ۲۸ روز اخیر."
    >
      <div v-if="growthBars.length" class="mt-4">
        <VBarChart :data="growthBars" :height="190" aria-label="نمودار بازدید روزانهٔ کل سازمان" />
      </div>
      <p v-else class="text-ink-muted text-sm leading-7">
        پس از اتصال سرچ کنسول، روند بازدید اینجا نمایش داده می‌شود.
      </p>
    </VCard>

    <div class="mt-8">
      <VEmptyState
        :title="nextStep.title"
        :description="nextStep.description"
        :action-label="nextStep.label"
        @action="router.visit(nextStep.href)"
      />
    </div>

    <VCard
      class="mt-8"
      title="📈 تأثیر محتوا پس از انتشار"
      description="تأثیر GSC محتوای منتشرشدهٔ خودکار — فقط بر اساس دادهٔ واقعی سرچ کنسول، بدون تخمین."
    >
      <div v-if="contentImpact.reported > 0" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4">
          <div class="flex items-baseline gap-2">
            <p class="text-ink-strong text-3xl font-bold">{{ contentImpact.reported }}</p>
            <p class="text-ink-muted text-sm">محتوا با دادهٔ GSC کافی</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <span v-if="contentImpact.verdicts.improved > 0" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
              {{ contentImpact.verdicts.improved }} بهبود
            </span>
            <span v-if="contentImpact.verdicts.stable > 0" class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
              {{ contentImpact.verdicts.stable }} پایدار
            </span>
            <span v-if="contentImpact.verdicts.declined > 0" class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">
              {{ contentImpact.verdicts.declined }} افت
            </span>
          </div>
          <p v-if="contentImpact.insufficient_data > 0" class="text-ink-muted text-xs">
            {{ contentImpact.insufficient_data }} مورد دیگر دادهٔ کافی GSC ندارند.
          </p>
        </div>
        <div v-if="contentImpact.best" class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
          <p class="text-xs font-semibold text-emerald-700">🏆 بهترین بهبود</p>
          <p class="text-ink-strong mt-2 text-sm font-semibold">{{ contentImpact.best.site_name ?? '—' }}</p>
          <p class="text-ink-muted mt-1 truncate text-xs" :title="contentImpact.best.url">{{ formatUrl(contentImpact.best.url) }}</p>
          <p class="text-ink-strong mt-3 text-sm">
            جایگاه {{ sign(contentImpact.best.delta.position) }} · کلیک {{ sign(contentImpact.best.delta.clicks) }}
          </p>
        </div>
        <div v-if="contentImpact.worst" class="rounded-xl border border-red-100 bg-red-50/50 p-4">
          <p class="text-xs font-semibold text-red-700">⚠️ ضعیف‌ترین نتیجه</p>
          <p class="text-ink-strong mt-2 text-sm font-semibold">{{ contentImpact.worst.site_name ?? '—' }}</p>
          <p class="text-ink-muted mt-1 truncate text-xs" :title="contentImpact.worst.url">{{ formatUrl(contentImpact.worst.url) }}</p>
          <p class="text-ink-strong mt-3 text-sm">
            جایگاه {{ sign(contentImpact.worst.delta.position) }} · کلیک {{ sign(contentImpact.worst.delta.clicks) }}
          </p>
        </div>
      </div>
      <p v-else class="text-ink-muted text-sm leading-7">
        هنوز محتوایی با دادهٔ کافی GSC منتشر نشده است — پس از اجرای چند انتشار خودکار و همگام‌سازی سرچ کنسول، تأثیر آن‌ها اینجا نمایش داده می‌شود.
      </p>
    </VCard>

    <VCard
      v-if="publishSuggestions.length"
      class="mt-8"
      title="✨ بهترین زمان انتشار"
      description="پیشنهاد هوشمند روز و ساعت انتشار برای هر سایت — بر اساس میانگین کلیک واقعی GSC."
    >
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="s in publishSuggestions"
          :key="s.site_id"
          class="rounded-xl border border-brand-100 bg-brand-50/40 p-4"
        >
          <p class="text-ink-strong text-sm font-semibold">{{ s.site_name }}</p>
          <p class="text-brand-800 mt-2 text-lg font-bold">
            {{ s.label }} · {{ String(s.hour).padStart(2, '0') }}:۰۰
          </p>
          <p class="text-ink-muted mt-1 text-xs">
            {{ s.source === 'hourly' ? 'دادهٔ ساعتی GSC' : 'دادهٔ روزانه GSC' }}
          </p>
        </div>
      </div>
      <div class="mt-4">
        <VButton href="/app/content-calendar" size="sm" variant="secondary">
          باز کردن تقویم محتوایی
        </VButton>
      </div>
    </VCard>

    <VCard
      class="mt-8"
      title="فعالیت‌های اخیر"
      description="آخرین تغییرات مهم فضای کاری، بدون نمایش داده‌های حساس."
    >
      <div v-if="activities.length" class="divide-line divide-y">
        <div
          v-for="activity in activities"
          :key="activity.id"
          class="flex items-start justify-between gap-4 py-4"
        >
          <div>
            <p class="text-ink-strong text-sm font-semibold">
              {{ actionLabels[activity.action] ?? 'فعالیت ثبت شد' }}
            </p>
            <p class="text-ink-muted mt-1 text-sm">{{ activity.actorName }}</p>
          </div>
          <time v-if="activity.occurredAt" class="text-ink-muted shrink-0 text-sm">{{
            new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: 'Asia/Tehran',
            }).format(new Date(activity.occurredAt))
          }}</time>
        </div>
      </div>
      <p v-else class="text-ink-muted text-sm leading-7">
        هنوز فعالیت مهمی در این فضای کاری ثبت نشده است.
      </p>
    </VCard>
  </AppLayout>
</template>
