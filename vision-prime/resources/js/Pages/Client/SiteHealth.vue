<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VIcon, { type IconName, type IconTone } from '@/shared/ui/VIcon.vue'

interface SiteHealthSummary {
  total_sites: number
  connected_sites: number
  needs_attention: number
}

interface SiteHealthItem {
  id: number
  name: string
  canonical_url: string
  connected: boolean
  last_seen_at: string | null
  audit_score: number | null
  issue_count: number
  high_risk_count: number
  url_count: number
}

const props = defineProps<{
  summary: SiteHealthSummary | null
  sites: SiteHealthItem[]
}>()

interface HealthLevel {
  label: string
  message: string
  tone: IconTone
  icon: IconName
  bar: string
  badge: 'success' | 'warning' | 'danger' | 'neutral'
}

function healthOf(score: number | null): HealthLevel {
  if (score === null) {
    return {
      label: 'در حال بررسی',
      message: 'هنوز بررسی کاملی روی این سایت انجام نشده است.',
      tone: 'neutral',
      icon: 'clock',
      bar: 'bg-line-strong',
      badge: 'neutral',
    }
  }
  if (score >= 80) {
    return {
      label: 'وضعیت عالی',
      message: 'سایت شما در وضعیت خوبی قرار دارد؛ ادامه دهید!',
      tone: 'success',
      icon: 'check',
      bar: 'bg-success-500',
      badge: 'success',
    }
  }
  if (score >= 70) {
    return {
      label: 'وضعیت خوب',
      message: 'سایت تقریباً سالم است؛ چند مورد کوچک برای بهبود وجود دارد.',
      tone: 'warning',
      icon: 'lightbulb',
      bar: 'bg-warning-500',
      badge: 'warning',
    }
  }
  return {
    label: 'نیاز به توجه',
    message: 'چند مورد مهم پیدا شده که می‌تواند روی رشد سایت اثر بگذارد.',
    tone: 'danger',
    icon: 'ban',
    bar: 'bg-danger-500',
    badge: 'danger',
  }
}

const summaryHealth = computed(() => {
  const scored = props.sites.filter((site) => site.audit_score !== null)
  if (scored.length === 0) return null
  const avg = Math.round(scored.reduce((sum, site) => sum + (site.audit_score ?? 0), 0) / scored.length)
  return { avg, level: healthOf(avg) }
})
</script>

<template>
  <Head title="وضعیت سایت | پرتال مشتری" />
  <ClientPortalLayout>
    <VPageHeader
      title="وضعیت سایت"
      description="سایت شما چقدر سالم است؟ وضعیت اتصال و سلامت صفحات، بدون جزئیات فنی پیچیده."
    />

    <!-- نوار سلامت کلی -->
    <section v-if="summaryHealth" class="rounded-panel border-line bg-surface mt-8 border p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span
            :class="[
              'rounded-ui flex size-12 items-center justify-center',
              summaryHealth.level.tone === 'success'
                ? 'bg-success-50 text-success-600'
                : summaryHealth.level.tone === 'danger'
                  ? 'bg-danger-50 text-danger-600'
                  : 'bg-warning-50 text-warning-600',
            ]"
          >
            <VIcon :name="summaryHealth.level.icon" size="lg" />
          </span>
          <div>
            <p class="text-ink-strong font-display text-lg font-bold">
              سلامت کلی: {{ summaryHealth.level.label }}
            </p>
            <p class="text-ink-muted mt-1 text-sm">{{ summaryHealth.level.message }}</p>
          </div>
        </div>
        <div class="text-center">
          <p class="font-display text-ink-strong text-3xl font-extrabold">{{ summaryHealth.avg }}</p>
          <p class="text-ink-muted text-xs">امتیاز سلامت از ۱۰۰</p>
        </div>
      </div>
      <div class="bg-surface-muted mt-5 h-3 w-full overflow-hidden rounded-full">
        <div
          class="h-full rounded-full transition-all duration-700"
          :class="summaryHealth.level.bar"
          :style="{ width: `${summaryHealth.avg}%` }"
        />
      </div>
    </section>

    <div v-if="props.sites.length" class="mt-8 space-y-4">
      <VCard v-for="site in props.sites" :key="site.id">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-ink-strong font-semibold">{{ site.name }}</h2>
              <VBadge :tone="site.connected ? 'success' : 'neutral'">
                {{ site.connected ? 'متصل است' : 'اتصال برقرار نشده' }}
              </VBadge>
              <VBadge :tone="healthOf(site.audit_score).badge">
                {{ healthOf(site.audit_score).label }}
              </VBadge>
            </div>
            <p class="text-ink-muted mt-1 truncate text-sm" dir="ltr">{{ site.canonical_url }}</p>

            <div class="mt-4">
              <div class="flex items-center justify-between text-xs">
                <span class="text-ink-muted">امتیاز سلامت</span>
                <span class="text-ink-strong font-bold">
                  {{ site.audit_score === null ? 'در حال بررسی' : `${site.audit_score} از ۱۰۰` }}
                </span>
              </div>
              <div class="bg-surface-muted mt-1.5 h-2 w-full overflow-hidden rounded-full">
                <div
                  class="h-full rounded-full transition-all duration-700"
                  :class="healthOf(site.audit_score).bar"
                  :style="{ width: `${site.audit_score ?? 0}%` }"
                />
              </div>
              <p class="text-ink-muted mt-2 text-xs leading-5">{{ healthOf(site.audit_score).message }}</p>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-4 text-sm">
              <div>
                <p class="text-ink-muted">مشکلات شناسایی‌شده</p>
                <p class="text-ink-strong mt-1 font-bold">{{ site.issue_count }}</p>
              </div>
              <div>
                <p class="text-ink-muted">نقاط خطر مهم</p>
                <p class="text-ink-strong mt-1 font-bold">{{ site.high_risk_count }}</p>
              </div>
              <div>
                <p class="text-ink-muted">صفحات بررسی‌شده</p>
                <p class="text-ink-strong mt-1 font-bold">{{ site.url_count }}</p>
              </div>
            </div>
            <p v-if="site.last_seen_at" class="text-ink-muted mt-3 text-xs">
              آخرین ارتباط: {{ formatJalaliDate(site.last_seen_at) }}
            </p>
          </div>
        </div>
      </VCard>
    </div>

    <VEmptyState
      v-if="!props.sites.length"
      class="mt-8"
      title="سایتی برای نمایش وجود ندارد"
      description="وقتی سایتی به حساب شما اضافه شود، وضعیت سلامت آن در اینجا نمایش داده می‌شود."
    />
  </ClientPortalLayout>
</template>
