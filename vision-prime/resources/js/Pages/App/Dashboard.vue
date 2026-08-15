<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

import VAlert from '@/shared/ui/VAlert.vue'
import type { ContentImpactSummary, ImpactSummaryEntry } from '@/types/automation'

const props = defineProps<{
  counts: {
    clients: number
    projects: number
    sites: number
    connectedSites: number
    gscConnectedSites: number
    openOpportunities: number
  }
  contentImpact: ContentImpactSummary
  activities: {
    id: number
    action: string
    actorName: string
    subjectType: string | null
    subjectId: number | null
    occurredAt: string | null
  }[]
}>()

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
  }
  catch {
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
  'review.created': 'مورد بازبینی جدید ایجاد شد',
  'review.item_created': 'مورد بازبینی جدید ایجاد شد',
  'review.decided': 'بازبینی تصمیم‌گیری شد',
  'ai.generation_created': 'پیشنویس هوش مصنوعی تولید شد',
  'ai.draft_generated': 'پیشنویس متا تولید شد',
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
  <Head title="داشبورد" />
  <AppLayout>
    <VPageHeader
      title="داشبورد"
      description="نمای کلی عملیات رشد و وضعیت فضای کاری شما."
      :status="{ label: 'فضای کاری فعال', tone: 'success' }"
      ><template #actions
        ><VButton :href="nextStep.href">{{ nextStep.label }}</VButton></template
      ></VPageHeader
    >
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
    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <VCard title="مشتریان"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ counts.clients }}</p>
        <p class="text-ink-muted mt-1 text-sm">مشتریان فعال فضای کاری</p></VCard
      >
      <VCard title="پروژه‌ها"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ counts.projects }}</p>
        <p class="text-ink-muted mt-1 text-sm">هدف‌های عملیاتی ثبت‌شده</p></VCard
      >
      <VCard title="سایت‌ها"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ counts.sites }}</p>
        <p class="text-ink-muted mt-1 text-sm">از این تعداد {{ counts.connectedSites }} سایت به وردپرس متصل است</p></VCard
      >
      <VCard title="فرصت‌های باز"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ counts.openOpportunities }}</p>
        <p class="text-ink-muted mt-1 text-sm">فرصت‌های اولویت‌دار منتظر اقدام</p></VCard
      >
    </section>
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
