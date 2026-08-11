<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

const props = defineProps<{
  counts: {
    clients: number
    projects: number
    sites: number
    connectedSites: number
    gscConnectedSites: number
    openOpportunities: number
  }
  activities: {
    id: number
    action: string
    actorName: string
    subjectType: string | null
    subjectId: number | null
    occurredAt: string | null
  }[]
}>()

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
  'command.approval_decided': 'تصمیم تأیید تغییر ثبت شد',
  'command.dispatched': 'تغییر اجرایی به‌اجرا درآمد',
  'command.executed': 'تغییر اجرایی اجرا شد',
  'command.failed': 'اجرای تغییر ناموفق بود',
  'review.created': 'مورد بازبینی جدید ایجاد شد',
  'review.decided': 'بازبینی تصمیم‌گیری شد',
  'report.created': 'گزارش جدید ایجاد شد',
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
