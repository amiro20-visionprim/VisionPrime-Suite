<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import {
  AlertTriangle,
  ArrowUpRight,
  BarChart3,
  CalendarDays,
  CheckCircle2,
  FileText,
  Globe,
  GraduationCap,
  Layers,
  Plug,
  Quote,
  ShieldCheck,
  Sparkles,
  Stethoscope,
  Store,
  Target,
  TrendingUp,
} from '@lucide/vue'

import AnimatedNumber from '@/marketing/components/AnimatedNumber.vue'

import HeroDashboardMock from '@/marketing/components/HeroDashboardMock.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'

const pillars = [
  {
    icon: BarChart3,
    title: 'اولویت‌گذاری مبتنی بر داده',
    text: 'فرصت‌هایی را ببینید که با داده سرچ کنسول، ارزش کسب‌وکاری و وضعیت صفحه رتبه‌بندی شده‌اند.',
  },
  {
    icon: ShieldCheck,
    title: 'تغییرات کنترل‌شده',
    text: 'از پیشنهاد تا تأیید، اجرا و بازگشت؛ هر تغییر قابل ردیابی و مطابق سیاست هر سایت است.',
  },
  {
    icon: FileText,
    title: 'گزارش قابل فهم برای مشتری',
    text: 'خروجی فنی تیم را به تصمیم، پیشرفت و گام بعدی قابل فهم برای کارفرما تبدیل کنید.',
  },
]

const audiences = [
  {
    icon: Layers,
    href: '/for-agencies',
    title: 'آژانس‌های SEO',
    text: 'چندمشتری، وایتدلیبل و مقیاس‌پذیری عملیات.',
  },
  {
    icon: Store,
    href: '/for-ecommerce',
    title: 'فروشگاه‌های اینترنتی',
    text: 'از ترافیک گوگل تا فروش واقعی.',
  },
  {
    icon: Stethoscope,
    href: '/for-clinics',
    title: 'کلینیک‌ها و مراکز درمانی',
    text: 'جذب بیمار از جستجوی محلی.',
  },
  {
    icon: GraduationCap,
    href: '/for-education',
    title: 'مراکز آموزشی',
    text: 'هنرجوی جدید در فصل ثبت‌نام.',
  },
  {
    icon: CalendarDays,
    href: '/for-hospitality',
    title: 'سفر و هتلداری',
    text: 'رزرو مستقیم از گوگل.',
  },
]

const integrations = [
  { icon: Globe, name: 'WordPress' },
  { icon: Store, name: 'WooCommerce' },
  { icon: BarChart3, name: 'Search Console' },
  { icon: ShieldCheck, name: 'HMAC' },
  { icon: Layers, name: 'Rollback' },
  { icon: Plug, name: 'REST API' },
]

const stats = [
  { to: 12, suffix: '+', label: 'سایت وردپرسی متصل' },
  { to: 480, suffix: '+', label: 'فرصت رشد شناسایی‌شده' },
  { to: 120, suffix: '+', label: 'تغییر اجرایی کنترل‌شده' },
  { to: 98, suffix: '٪', label: 'رضایت مشتری' },
]

const scenarios = [
  {
    quote:
      'مشتری هر ماه می‌پرسید «چرا این صفحه را تغییر دادید؟» و ما فقط حدس می‌زدیم. حالا هر پیشنهاد با دادهٔ سرچ کنسول می‌آید: شکاف CTR، ارزش صفحه و اثر مورد انتظار. جلسهٔ گزارش از حالت دفاعی به گفت‌وگو تبدیل شد.',
    name: 'مدیر SEO یک آژانس با ۱۲ مشتری وردپرسی',
    initials: 'م ‌آ',
  },
  {
    quote:
      'قبلاً تغییر تایتل یا متا را با ترس انجام می‌دادیم؛ یک اشتباه می‌توانست فروش یک صفحه را خراب کند. الان هر تغییری اول تأیید می‌گیرد، snapshot دارد و اگر اثرش منفی بود برمی‌گردانیمش.',
    name: 'بنیان‌گذار یک استودیوی محتوای دیجیتال',
    initials: 'ب ا',
  },
  {
    quote:
      'گزارش ماهانه از جمع‌کردن اسکرین‌شات در ۵ ابزار به یک صفحهٔ خودکار رسید که می‌گوید چه کردیم، چه اثری داشت و قدم بعدی چیست؛ حالا مشتری خودش می‌گوید «این را برای مدیران شرکت بفرست».',
    name: 'مسئول SEO یک فروشگاه ووکامرس',
    initials: 'م ف',
  },
]

const modules = [
  {
    icon: TrendingUp,
    title: 'هوش فرصت‌های رشد',
    text: 'تحلیل رشد روی دادهٔ واقعی GSC؛ فرصت‌ها با intent، CTR gap و ارزش تجاری رتبه‌بندی می‌شوند.',
    featured: true,
  },
  {
    icon: Target,
    title: 'بهینه‌سازی صفحات درآمدزا',
    text: 'تمرکز روی صفحه‌هایی که بیشترین ظرفیت اثر تجاری را دارند.',
  },
  {
    icon: AlertTriangle,
    title: 'ریسک‌های تبدیل',
    text: 'شناسایی ریسک‌های محتوایی و تبدیل با شدت و توصیهٔ مشخص.',
  },
  {
    icon: Plug,
    title: 'اتصال امن وردپرس',
    text: 'Pairing امضا شده با HMAC؛ اجرای کنترل‌شده با rollback snapshot.',
  },
  {
    icon: CheckCircle2,
    title: 'گردش‌کار تأیید',
    text: 'هر تغییر پیش از اجرا، تأیید مشتری را می‌گیرد و در audit trail ثبت می‌شود.',
  },
  {
    icon: Sparkles,
    title: 'گزارش و سنجش اثر',
    text: 'گزارش مدیریتی، impact timeline و گام‌های بعدی برای مشتری.',
  },
]
</script>

<template>
  <Head title="پلتفرم هوشمند عملیات SEO" />
  <MarketingLayout>
    <main>
      <!-- HERO -->
      <section class="relative overflow-hidden border-b border-line bg-canvas">
        <!-- ambient background -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
          <div
            class="bg-radial-fade bg-hero-grid absolute inset-0"
          />
          <div
            class="absolute -top-32 right-[-10%] size-[560px] rounded-full bg-brand-200/50 blur-3xl"
          />
          <div
            class="absolute top-10 left-[-12%] size-[520px] rounded-full bg-violet-200/40 blur-3xl"
          />
          <div
            class="absolute bottom-[-20%] right-1/3 size-[420px] rounded-full bg-indigo-200/30 blur-3xl"
          />
        </div>

        <div
          class="relative mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 lg:grid-cols-[1.02fr_0.98fr] lg:px-10 lg:py-24"
        >
          <div v-reveal class="self-center">
            <VBadge tone="info">
              <Sparkles class="size-3.5" aria-hidden="true" />
              برای آژانس‌ها و تیم‌های SEO وردپرسی
            </VBadge>
            <h1
              class="font-display text-ink-strong mt-5 max-w-3xl text-4xl leading-[1.55] font-bold sm:text-5xl sm:leading-[1.5]"
            >
              از داده سرچ کنسول تا اقدام قابل تأیید؛
              <span class="text-gradient-brand">یک مرکز فرماندهی</span> برای رشد SEO.
            </h1>
            <p class="text-ink-muted mt-5 max-w-2xl text-base leading-8 sm:text-lg">
              سوئیت فرصت‌های رشد را شناسایی می‌کند، اقدامات را در مسیر کنترل‌شده پیش می‌برد و
              اثر هر تصمیم را به زبان روشن نشان می‌دهد.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
              <VButton href="/demo" size="lg" variant="gradient">
                درخواست دموی اختصاصی
                <ArrowUpRight class="size-4" aria-hidden="true" />
              </VButton>
              <VButton href="/pricing" size="lg" variant="secondary">مشاهدهٔ قیمت‌گذاری</VButton>
            </div>
            <div class="mt-7 flex flex-wrap items-center gap-x-6 gap-y-2">
              <span class="text-ink-muted flex items-center gap-1.5 text-sm">
                <ShieldCheck class="size-4 text-success-600" aria-hidden="true" />
                بدون انتشار مستقیم AI
              </span>
              <span class="text-ink-muted flex items-center gap-1.5 text-sm">
                <CheckCircle2 class="size-4 text-success-600" aria-hidden="true" />
                بدون تغییر پرریسک خارج از سیاست سایت
              </span>
            </div>
          </div>

          <HeroDashboardMock />
        </div>
      </section>

      <!-- TRUSTED BY / LOGO STRIP -->
      <section class="border-b border-line bg-surface">
        <div v-reveal class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10">
          <p class="text-ink-muted text-center text-xs font-bold tracking-wide">
            بدون تغییر زیرساخت شما؛ سازگار با اکوسیستمی که از قبل دارید
          </p>
          <div class="mt-7 grid grid-cols-2 items-center gap-x-6 gap-y-5 sm:grid-cols-3 lg:grid-cols-6">
            <div
              v-for="item in integrations"
              :key="item.name"
              class="text-ink-muted flex items-center justify-center gap-2 opacity-70 transition-all duration-300 hover:opacity-100"
            >
              <component :is="item.icon" class="size-5" aria-hidden="true" />
              <span class="font-latin text-ink text-base font-bold tracking-tight" dir="ltr">{{ item.name }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- WHY -->
      <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
        <div v-reveal>
          <SectionHeading
            eyebrow="WHY VISION PRIME SUITE"
            title="عملیات SEO را از ابزارهای پراکنده به یک جریان قابل کنترل تبدیل کنید."
            description="برای تیم‌هایی که رشد پایدار، مسئولیت‌پذیری و شفافیت خروجی را در کنار هم می‌خواهند."
          />
        </div>
        <div class="mt-12 grid gap-5 md:grid-cols-3">
          <div
            v-for="(pillar, index) in pillars"
            :key="pillar.title"
            v-reveal="{ delay: index * 120 }"
            class="group rounded-2xl border border-line bg-surface p-6 shadow-card transition-all duration-300 hover:-translate-y-1.5 hover:shadow-panel hover:border-brand-200"
          >
            <span
              class="bg-gradient-brand inline-flex size-11 items-center justify-center rounded-xl text-white shadow-md transition-transform duration-300 group-hover:scale-110"
            >
              <component :is="pillar.icon" class="size-5" aria-hidden="true" />
            </span>
            <h3 class="text-ink-strong mt-5 text-lg font-bold">{{ pillar.title }}</h3>
            <p class="text-ink-muted mt-2.5 leading-7">{{ pillar.text }}</p>
          </div>
        </div>

        <!-- STATS BAND -->
        <div
          v-reveal
          class="relative mt-16 overflow-hidden rounded-3xl border border-line bg-surface px-6 py-10 shadow-card sm:px-10"
        >
          <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute -top-24 right-1/4 size-64 rounded-full bg-brand-200/40 blur-3xl" />
            <div class="absolute -bottom-24 left-1/4 size-64 rounded-full bg-violet-200/40 blur-3xl" />
          </div>
          <div class="relative grid grid-cols-2 gap-8 lg:grid-cols-4">
            <div
              v-for="(stat, index) in stats"
              :key="stat.label"
              v-reveal="{ delay: index * 110 }"
              class="text-center"
            >
              <p class="font-display text-gradient-brand text-4xl font-bold sm:text-5xl">
                <AnimatedNumber :to="stat.to" />
                <span>{{ stat.suffix }}</span>
              </p>
              <p class="text-ink-muted mt-2 text-sm font-semibold">{{ stat.label }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- AUDIENCES (hub) -->
      <section class="border-line bg-canvas border-b">
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
          <div v-reveal>
            <SectionHeading
              eyebrow="AUDIENCES"
              title="سوئیت برای چه کسی ساخته شده؟"
              description="یک صفحهٔ اختصاصی برای هر نوع کسب‌وکار — پیام، درد و مثال‌ها مخصوص همان حوزه‌اند."
              centered
            />
          </div>
          <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <a
              v-for="(item, index) in audiences"
              :key="item.href"
              v-reveal="{ delay: index * 90 }"
              :href="item.href"
              class="group rounded-2xl border border-line bg-surface p-6 shadow-card transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-panel"
            >
              <span
                class="bg-gradient-brand inline-flex size-11 items-center justify-center rounded-xl text-white shadow-md transition-transform duration-300 group-hover:scale-110"
              >
                <component :is="item.icon" class="size-5" aria-hidden="true" />
              </span>
              <h3 class="text-ink-strong mt-5 text-lg font-bold">{{ item.title }}</h3>
              <p class="text-ink-muted mt-2 leading-7">{{ item.text }}</p>
              <span class="text-brand-700 mt-4 inline-flex items-center gap-1.5 text-sm font-bold">
                مشاهدهٔ صفحهٔ اختصاصی
                <ArrowUpRight class="size-4" aria-hidden="true" />
              </span>
            </a>
          </div>
        </div>
      </section>

      <!-- MODULES (bento) -->
      <section class="border-y border-line bg-surface">
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
          <div v-reveal>
            <SectionHeading
              eyebrow="CORE MODULES"
              title="همه اجزای ضروری رشد SEO در یک سیستم یکپارچه."
              description="هر ماژول یک نقطهٔ مشخص از عملیات رشد را پوشش می‌دهد؛ همه در یک جریان مشترک کار می‌کنند."
            />
          </div>
          <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="(module, index) in modules"
              :key="module.title"
              v-reveal="{ delay: index * 90 }"
              class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:-translate-y-1"
              :class="
                module.featured
                  ? 'bg-gradient-brand border-transparent text-white shadow-panel lg:col-span-2 lg:row-span-2'
                  : 'border-line bg-canvas hover:border-brand-200 hover:shadow-card'
              "
            >
              <div
                v-if="module.featured"
                aria-hidden="true"
                class="pointer-events-none absolute -top-20 -left-20 size-56 rounded-full bg-white/15 blur-2xl"
              />
              <span
                class="inline-flex size-11 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-110"
                :class="
                  module.featured
                    ? 'bg-white/20 text-white'
                    : 'bg-gradient-brand text-white shadow-md'
                "
              >
                <component :is="module.icon" class="size-5" aria-hidden="true" />
              </span>
              <h3
                class="mt-5 text-lg font-bold"
                :class="module.featured ? 'text-white' : 'text-ink-strong'"
              >
                {{ module.title }}
              </h3>
              <p
                class="mt-2.5 max-w-md leading-7"
                :class="module.featured ? 'text-brand-100' : 'text-ink-muted'"
              >
                {{ module.text }}
              </p>
              <div
                v-if="module.featured"
                class="mt-6 flex flex-wrap gap-2"
                aria-hidden="true"
              >
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white"
                  >۴۸ صفحه تحلیل‌شده</span
                >
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white"
                  >۳۲ فرصت رتبه‌بندی‌شده</span
                >
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white"
                  >CTR gap محاسبه‌شده</span
                >
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- TESTIMONIALS -->
      <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
        <div v-reveal>
          <SectionHeading
            eyebrow="SCENARIOS"
            title="سناریوهای واقعی؛ دغدغه‌ها چطور حل می‌شوند."
            description="سه دغدغهٔ رایج در عملیات SEO و مسیری که سوئیت برای آن‌ها پیشنهاد می‌کند — با برچسب نمونه، چون هنوز در مرحلهٔ تست میدانی هستیم."
          />
        </div>
        <div class="mt-12 grid gap-5 lg:grid-cols-3">
          <figure
            v-for="(scenario, index) in scenarios"
            :key="scenario.name"
            v-reveal="{ delay: index * 140 }"
            class="relative rounded-2xl border border-line bg-surface p-7 shadow-card transition-all duration-300 hover:-translate-y-1 hover:shadow-panel sm:p-8"
          >
            <Quote
              class="text-brand-200 absolute top-6 left-6 size-8"
              aria-hidden="true"
            />
            <VBadge tone="info" class="mt-1">سناریوی نمونه</VBadge>
            <blockquote class="text-ink-strong mt-4 text-base leading-8 font-medium sm:text-lg sm:leading-9">
              «{{ scenario.quote }}»
            </blockquote>
            <figcaption class="mt-6 flex items-center gap-3.5">
              <span
                class="bg-gradient-brand flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white shadow-md"
                >{{ scenario.initials }}</span
              >
              <div>
                <p class="text-ink-strong text-sm font-bold">{{ scenario.name }}</p>
              </div>
            </figcaption>
          </figure>
        </div>
      </section>

      <!-- CTA -->
      <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
        <div
          v-reveal
          class="relative overflow-hidden rounded-3xl bg-brand-900 px-6 py-12 text-white sm:px-12 sm:py-16"
        >
          <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute -top-24 left-1/4 size-72 rounded-full bg-violet-500/30 blur-3xl" />
            <div class="absolute -bottom-28 right-1/4 size-72 rounded-full bg-indigo-500/30 blur-3xl" />
            <div
              class="absolute inset-0 opacity-[0.12]"
              style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 26px 26px"
            />
          </div>
          <div class="relative">
            <p class="text-brand-200 text-sm font-bold tracking-wide">
              READY FOR A BETTER SEO OPERATING SYSTEM?
            </p>
            <div class="mt-4 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <h2 class="max-w-2xl text-3xl leading-relaxed font-bold sm:text-4xl sm:leading-relaxed">
                  رشد سایت‌هایتان را با داده، کنترل و گزارش‌پذیری جلو ببرید.
                </h2>
                <p class="text-brand-100 mt-4 max-w-2xl leading-8">
                  در دمو، جریان کاری مناسب آژانس و سایت‌های وردپرسی شما را بررسی می‌کنیم؛ بدون تعهد و
                  بدون نیاز به کارت اعتباری.
                </p>
              </div>
              <div class="flex flex-wrap gap-3">
                <VButton href="/demo" size="lg" variant="secondary">
                  درخواست دموی اختصاصی
                  <ArrowUpRight class="size-4" aria-hidden="true" />
                </VButton>
                <a
                  href="/pricing"
                  class="transition-ui rounded-ui inline-flex min-h-12 items-center justify-center gap-2 border border-white/25 bg-white/10 px-5 text-base font-semibold text-white whitespace-nowrap hover:bg-white/20"
                  >مشاهدهٔ قیمت‌گذاری</a
                >
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </MarketingLayout>
</template>
