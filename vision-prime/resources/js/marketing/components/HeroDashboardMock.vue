<script setup lang="ts">
import {
  AlertTriangle,
  BarChart3,
  CheckCircle2,
  FileText,
  Gauge,
  LayoutDashboard,
  Lock,
  Plug,
  Target,
  TrendingUp,
} from '@lucide/vue'

import AnimatedNumber from '@/marketing/components/AnimatedNumber.vue'
import VBadge from '@/shared/ui/VBadge.vue'

const navItems = [
  { icon: LayoutDashboard, label: 'داشبورد', active: true },
  { icon: TrendingUp, label: 'فرصت‌ها', badge: '۱۲' },
  { icon: Target, label: 'صفحات درآمدزا' },
  { icon: AlertTriangle, label: 'ریسک‌ها' },
  { icon: FileText, label: 'گزارش‌ها' },
]

const chartBars = [34, 52, 41, 68, 58, 82, 74, 92]
</script>

<template>
  <div v-reveal="{ delay: 150 }" class="relative">
    <!-- ambient glow behind the window -->
    <div
      aria-hidden="true"
      class="pointer-events-none absolute -inset-6 rounded-[2.5rem] bg-gradient-to-br from-brand-500/25 via-indigo-500/20 to-violet-500/25 blur-2xl"
    />

    <!-- browser window -->
    <div
      class="shadow-panel relative overflow-hidden rounded-2xl border border-line-strong/70 bg-surface"
    >
      <!-- window chrome -->
      <div class="flex items-center gap-3 border-b border-line bg-surface-muted/70 px-4 py-2.5">
        <div class="flex gap-1.5" aria-hidden="true">
          <span class="size-2.5 rounded-full bg-[#ff5f57]" />
          <span class="size-2.5 rounded-full bg-[#febc2e]" />
          <span class="size-2.5 rounded-full bg-[#28c840]" />
        </div>
        <div
          class="bg-surface flex flex-1 items-center gap-1.5 rounded-md border border-line px-3 py-1.5 text-xs"
        >
          <Lock class="text-ink-muted size-3" aria-hidden="true" />
          <span class="text-ink-muted font-latin" dir="ltr">app.visionprime.ir/app/opportunities</span>
        </div>
      </div>

      <!-- window body: sidebar + main -->
      <div class="grid grid-cols-[120px_1fr] sm:grid-cols-[140px_1fr]">
        <!-- sidebar -->
        <aside class="bg-canvas/60 border-e border-line p-3">
          <div class="flex items-center gap-1.5 px-1">
            <span class="bg-gradient-brand flex size-5 items-center justify-center rounded-md text-[9px] font-bold text-white">
              VP
            </span>
            <span class="font-display text-ink-strong text-[10px] font-bold">Vision Prime SUITE</span>
          </div>
          <nav class="mt-4 space-y-1" aria-hidden="true">
            <div
              v-for="item in navItems"
              :key="item.label"
              class="flex items-center justify-between rounded-md px-2 py-1.5 text-[10px] font-semibold"
              :class="
                item.active
                  ? 'bg-gradient-brand text-white shadow-sm'
                  : 'text-ink-muted hover:bg-surface-muted'
              "
            >
              <span class="flex items-center gap-1.5">
                <component :is="item.icon" class="size-3" aria-hidden="true" />
                {{ item.label }}
              </span>
              <span
                v-if="item.badge"
                class="rounded-full px-1 text-[8px] leading-4"
                :class="item.active ? 'bg-white/25' : 'bg-brand-50 text-brand-700'"
                >{{ item.badge }}</span
              >
            </div>
          </nav>
        </aside>

        <!-- main panel -->
        <div class="min-w-0 p-3 sm:p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-ink-strong text-xs font-bold">نمای کلی رشد سایت</p>
              <p class="text-ink-muted mt-0.5 text-[10px]">آخرین همگام‌سازی: امروز، ۱۰:۴۵</p>
            </div>
            <VBadge tone="success">اتصال پایدار</VBadge>
          </div>

          <!-- KPI row with animated counters -->
          <div class="mt-3 grid grid-cols-3 gap-2">
            <div class="rounded-lg border border-line bg-surface-muted/50 p-2.5">
              <p class="text-ink-muted text-[9px]">فرصت‌های اولویت‌دار</p>
              <p class="text-gradient-brand mt-1 text-lg font-bold leading-none sm:text-xl">
                <AnimatedNumber :to="12" />
              </p>
            </div>
            <div class="rounded-lg border border-line bg-surface-muted/50 p-2.5">
              <p class="text-ink-muted text-[9px]">صفحات درآمدزا</p>
              <p class="text-ink-strong mt-1 text-lg font-bold leading-none sm:text-xl">
                <AnimatedNumber :to="8" />
              </p>
            </div>
            <div class="rounded-lg border border-line bg-surface-muted/50 p-2.5">
              <p class="text-ink-muted text-[9px]">اقدام تکمیل‌شده</p>
              <p class="mt-1 text-lg font-bold leading-none text-emerald-600 sm:text-xl">
                <AnimatedNumber :to="24" />
              </p>
            </div>
          </div>

          <!-- mini bar chart -->
          <div class="mt-3 rounded-lg border border-line p-2.5">
            <div class="flex items-center justify-between">
              <p class="text-ink-strong text-[10px] font-bold">روند نمایش هفتگی</p>
              <span class="text-ink-muted text-[9px]">+۱۸٪</span>
            </div>
            <div class="mt-2 flex h-16 items-end gap-1.5" aria-hidden="true">
              <div
                v-for="(h, i) in chartBars"
                :key="i"
                class="flex-1 rounded-t-sm transition-all duration-500"
                :class="
                  i === chartBars.length - 1
                    ? 'bg-gradient-brand'
                    : 'bg-brand-500/25 group-hover:bg-brand-500/40'
                "
                :style="{ height: `${h}%` }"
              />
            </div>
          </div>

          <!-- opportunities row -->
          <div class="mt-3 space-y-1.5">
            <div class="flex items-center justify-between rounded-lg border border-line p-2">
              <div class="flex items-center gap-1.5">
                <span class="bg-brand-50 text-brand-700 flex size-5 items-center justify-center rounded-md">
                  <Target class="size-3" aria-hidden="true" />
                </span>
                <div>
                  <p class="text-ink-strong text-[10px] font-bold">صفحهٔ خدمات SEO</p>
                  <p class="text-ink-muted text-[9px]">رتبهٔ ۸٫۴ · شکاف CTR</p>
                </div>
              </div>
              <VBadge tone="warning">امتیاز ۸۷</VBadge>
            </div>
            <div class="flex items-center justify-between rounded-lg border border-line p-2">
              <div class="flex items-center gap-1.5">
                <span class="bg-success-50 text-success-700 flex size-5 items-center justify-center rounded-md">
                  <CheckCircle2 class="size-3" aria-hidden="true" />
                </span>
                <div>
                  <p class="text-ink-strong text-[10px] font-bold">به‌روزرسانی عنوان متا</p>
                  <p class="text-ink-muted text-[9px]">تأیید شد · آمادهٔ اجرا</p>
                </div>
              </div>
              <span class="text-ink-muted flex items-center gap-1 text-[9px]">
                <Gauge class="size-3" aria-hidden="true" /> انتظار
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- floating card: command approved -->
    <div
      class="animate-float-slow absolute -start-4 top-16 hidden w-52 rounded-xl border border-line bg-surface/95 p-3 shadow-float backdrop-blur sm:block lg:-start-8"
      aria-hidden="true"
    >
      <div class="flex items-center gap-2">
        <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
          <CheckCircle2 class="size-4" />
        </span>
        <div>
          <p class="text-ink-strong text-[11px] font-bold">دستور تأیید شد</p>
          <p class="text-ink-muted text-[10px]">به‌روزرسانی متای صفحهٔ اصلی</p>
        </div>
      </div>
      <div class="mt-2.5 flex items-center gap-1.5">
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted">
          <div class="bg-gradient-brand h-full w-[85%] rounded-full" />
        </div>
        <span class="text-brand-700 text-[9px] font-bold">۸۵٪</span>
      </div>
    </div>

    <!-- floating card: new opportunity -->
    <div
      class="animate-float absolute -end-4 bottom-14 hidden w-52 rounded-xl border border-line bg-surface/95 p-3 shadow-float backdrop-blur sm:block lg:-end-8"
      style="animation-delay: 1.4s"
      aria-hidden="true"
    >
      <div class="flex items-center gap-2">
        <span class="bg-gradient-brand flex size-7 items-center justify-center rounded-lg text-white">
          <TrendingUp class="size-4" />
        </span>
        <div>
          <p class="text-ink-strong text-[11px] font-bold">فرصت جدید</p>
          <p class="text-ink-muted text-[10px]">شکاف CTR صفحهٔ قیمت‌گذاری</p>
        </div>
      </div>
      <div class="mt-2.5 flex items-center justify-between">
        <span class="text-ink-muted text-[10px]">پتانسیل کلیک</span>
        <span class="text-emerald-600 flex items-center gap-0.5 text-[11px] font-bold">
          <BarChart3 class="size-3" aria-hidden="true" /> +۱۸٪
        </span>
      </div>
    </div>

    <!-- floating chip: connector -->
    <div
      class="animate-float absolute -top-3 end-10 hidden items-center gap-1.5 rounded-full border border-line bg-surface/95 px-3 py-1.5 shadow-card backdrop-blur md:flex"
      style="animation-delay: 0.8s"
      aria-hidden="true"
    >
      <span class="flex size-5 items-center justify-center rounded-full bg-brand-50 text-brand-700">
        <Plug class="size-3" />
      </span>
      <span class="text-ink-strong text-[10px] font-bold">وردپرس متصل است</span>
      <span class="size-1.5 rounded-full bg-emerald-500" />
    </div>
  </div>
</template>
