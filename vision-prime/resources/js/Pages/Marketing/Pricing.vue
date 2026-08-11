<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'

interface Plan {
  id: string
  title: string
  badge: string
  tagline: string
  monthly: number | null
  annual: number | null
  features: string[]
  cta: string
  href: string
  featured?: boolean
}

const annual = ref(false)

const plans: Plan[] = [
  {
    id: 'starter',
    title: 'پایه',
    badge: 'برای شروع',
    tagline: 'پایش و رشد — برای صاحبان سایت و فروشگاه‌هایی که می‌خواهند فرصت‌ها را ببینند.',
    monthly: 2_900_000,
    annual: 29_400_000,
    cta: 'درخواست دموی اختصاصی',
    href: '/demo',
    features: [
      '۱ سایت و ۱ پرتال مشتری',
      'اتصال سرچ کنسول و همگام‌سازی روزانه',
      'فرصت‌های رشد با اولویت‌بندی داده‌محور',
      'سلامت سایت و ریسک‌های تبدیل',
      'گزارش خودکار ماهانه',
      'پشتیبانی ایمیلی',
    ],
  },
  {
    id: 'professional',
    title: 'حرفه‌ای',
    badge: 'پرفروش‌ترین',
    tagline: 'عملیات اجرایی — برای صاحبان سایت جدی و آژانس‌های کوچک.',
    monthly: 6_900_000,
    annual: 69_000_000,
    cta: 'درخواست دموی اختصاصی',
    href: '/demo',
    featured: true,
    features: [
      'تا ۵ سایت و ۵ پرتال مشتری',
      'همهٔ امکانات پلن پایه',
      'تغییرات اجرایی با گردش‌کار تأیید',
      'تأیید مستقیم مشتری از پرتال',
      'اجرای خودکار روی وردپرس (پلاگین اختصاصی)',
      'پیشنویس AI متا و محتوا',
      'گزارش کامل و سنجش اثر هر اقدام',
      'ضمانت ۱۴ روزهٔ بازگشت وجه',
    ],
  },
  {
    id: 'agency',
    title: 'آژانس',
    badge: 'وایتدلیبل',
    tagline: 'برای آژانس‌هایی که عملیات چند مشتری را با برند خودشان اداره می‌کنند.',
    monthly: 12_900_000,
    annual: 129_000_000,
    cta: 'درخواست دموی اختصاصی',
    href: '/demo',
    features: [
      'تا ۱۵ سایت',
      'همهٔ امکانات پلن حرفه‌ای',
      'برند اختصاصی آژانس در پرتال و گزارش‌ها',
      'آنبوردینگ تیم و آموزش',
      'جلسات ماهانهٔ مرور عملکرد',
      'مدیر موفقیت اختصاصی',
      'ضمانت ۱۴ روزهٔ بازگشت وجه',
    ],
  },
]

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)

function displayPrice(plan: Plan): { amount: string; suffix: string } {
  if (plan.monthly === null || plan.annual === null) {
    return { amount: 'سفارشی', suffix: 'بر اساس نیاز شما' }
  }

  if (annual.value) {
    const perMonth = Math.round(plan.annual / 10)
    return { amount: `${faNum(plan.annual)} تومان`, suffix: `/ سالانه — معادل ${faNum(perMonth)} در ماه` }
  }

  return { amount: `${faNum(plan.monthly)} تومان`, suffix: '/ ماهانه' }
}

const comparisonRows = computed(() => [
  { label: 'تعداد سایت', values: ['۱', 'تا ۵', 'تا ۱۵', '۲۰+'] },
  { label: 'پرتال مشتری', values: ['۱', '۵', 'نامحدود', 'نامحدود'] },
  { label: 'فرصت‌های رشد و اولویت‌بندی', values: ['✓', '✓', '✓', '✓'] },
  { label: 'تغییرات اجرایی و گردش‌کار تأیید', values: ['—', '✓', '✓', '✓'] },
  { label: 'تأیید مستقیم مشتری از پرتال', values: ['—', '✓', '✓', '✓'] },
  { label: 'اجرای خودکار وردپرس', values: ['—', '✓', '✓', '✓'] },
  { label: 'پیشنویس AI', values: ['—', '✓', '✓', '✓'] },
  { label: 'گزارش مدیریتی', values: ['ماهانه', 'کامل', 'کامل + برند شما', 'سفارشی'] },
  { label: 'برند اختصاصی', values: ['—', '—', '✓', '✓'] },
  { label: 'ضمانت ۱۴ روزهٔ بازگشت وجه', values: ['—', '✓', '✓', '✓'] },
  { label: 'پشتیبانی', values: ['ایمیلی', '۲۴ ساعته', 'اختصاصی', 'SLA'] },
])

const comparisonColumns = ['پایه', 'حرفه‌ای', 'آژانس', 'سازمانی']

const faqs = [
  {
    q: 'تفاوت Vision Prime با استخدام یک سئوکار چیست؟',
    a: 'ما جایگزین سئوکار نیستیم؛ لایهٔ عملیاتی او هستیم. هر فرصت با دادهٔ سرچ کنسول شما رتبه‌بندی می‌شود، هر تغییر پیش از اجرا توسط شما تأیید می‌شود و اثر هر اقدام به زبان روشن گزارش داده می‌شود. شفافیت و کنترل‌پذیری‌ای که یک نیروی انسانی به‌تنهایی نمی‌تواند ارائه دهد.',
  },
  {
    q: 'آیا فقط سایت‌های وردپرسی پشتیبانی می‌شوند؟',
    a: 'تمرکز اصلی ما روی وردپرس است و اجرای خودکار تغییرات برای آن به‌صورت کامل پشتیبانی می‌شود. اگر سایت شما روی پلتفرم دیگری است، در جلسهٔ دمو مسیر ممکن را بررسی می‌کنیم.',
  },
  {
    q: 'داده‌های سایت ما چطور محافظت می‌شود؟',
    a: 'دسترسی‌ها کنترل‌شده و قابل بازبینی است، اتصال به سایت شما از طریق توکن‌های امن انجام می‌شود و داده‌های شما هرگز به شخص ثالث فروخته یا منتقل نمی‌شود. جزئیات کامل در صفحهٔ امنیت آمده است.',
  },
  {
    q: 'از ثبت‌نام تا اولین نتیجه چقدر طول می‌کشد؟',
    a: 'اتصال سایت و مشاهدهٔ اولین فرصت‌ها معمولاً در روز اول انجام می‌شود. اجرای اولین تغییرات تأییدشده معمولاً در هفتهٔ اول ممکن است.',
  },
  {
    q: 'اگر در ابتدای کار راضی نبودیم چه می‌شود؟',
    a: 'در پلن‌های حرفه‌ای و آژانس، اگر تا ۱۴ روز پس از شروع از خدمت راضی نبودید، کل مبلغ ماه اول بازگردانده می‌شود. ریسک با ماست، نه با شما.',
  },
  {
    q: 'قیمت نهایی چطور قطعی می‌شود؟',
    a: 'اعداد این صفحه برای شروع شفاف و ثابت است؛ در جلسهٔ دمو بر اساس تعداد سایت، اعضای تیم و سطح خودکارسازی، پیکربندی نهایی و قرارداد با قیمت کاملاً شفاف نهایی می‌شود.',
  },
  {
    q: 'آیا امکان تغییر پلن در میانهٔ مسیر هست؟',
    a: 'بله. ارتقا یا تغییر پلن در هر زمان بدون جریمه ممکن است و هزینه‌ها به‌صورت روزشمار محاسبه می‌شود.',
  },
]
</script>

<template>
  <Head title="قیمت‌گذاری" />
  <MarketingLayout>
    <MarketingPageHero
      title="قیمت‌گذاری شفاف؛ متناسب با عملیات شما."
      description="هر پلن بر اساس تعداد سایت، سطح عملیات و مدل استقرار طراحی شده است. قیمت نهایی در جلسهٔ دمو با جزئیات کامل قطعی می‌شود — بدون هزینهٔ پنهان."
    />

    <!-- Anchor: value framing -->
    <section class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
      <div class="rounded-panel border-line bg-surface border p-6 text-center sm:p-8">
        <p class="text-ink-muted text-sm font-medium">قبل از مقایسهٔ قیمت‌ها، این را بدانید:</p>
        <p class="text-ink-strong font-display mt-3 text-xl font-bold leading-9 sm:text-2xl">
          یک سئوکار تمام‌وقت در ایران از <span class="text-brand-700">۲۰ میلیون تومان در ماه</span>{{
            ' '
          }}شروع می‌شود — با ابزارهای جداگانه، گزارش‌های دستی و بدون کنترل شما.
        </p>
        <p class="text-ink-muted mt-3 leading-7">
          Vision Prime از <span class="text-ink-strong font-bold">یک‌دهم این هزینه</span> شروع
          می‌شود؛ با دادهٔ واقعی، تأیید شما پیش از هر تغییر و گزارش‌پذیری کامل.
        </p>
      </div>
    </section>

    <!-- Billing toggle -->
    <section class="mx-auto max-w-7xl px-5 pt-10 sm:px-8 lg:px-10">
      <div class="flex items-center justify-center gap-2">
        <button
          type="button"
          class="rounded-ui px-4 py-2 text-sm font-bold transition"
          :class="annual ? 'text-ink-muted' : 'bg-brand-700 text-white'"
          @click="annual = false"
        >
          پرداخت ماهانه
        </button>
        <button
          type="button"
          class="rounded-ui px-4 py-2 text-sm font-bold transition"
          :class="annual ? 'bg-brand-700 text-white' : 'text-ink-muted'"
          @click="annual = true"
        >
          پرداخت سالانه
          <span class="rounded-ui bg-success-100 text-success-700 ms-1 px-2 py-0.5 text-xs"
            >۲ ماه رایگان</span
          >
        </button>
      </div>
      <p class="text-ink-muted mt-3 text-center text-sm">
        {{
          annual
            ? 'با پرداخت سالانه، ۲ ماه رایگان دریافت می‌کنید (۱۷٪ تخفیف).'
            : 'بدون قرارداد بلندمدت — ماهانه پرداخت کنید و هر زمان تغییر دهید.'
        }}
      </p>
    </section>

    <!-- Plan cards -->
    <section class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10">
      <div class="grid gap-5 lg:grid-cols-3">
        <div
          v-for="plan in plans"
          :key="plan.id"
          class="rounded-panel border flex flex-col p-6 sm:p-7"
          :class="
            plan.featured
              ? 'bg-brand-900 text-white shadow-panel border-brand-900'
              : 'border-line bg-surface'
          "
        >
          <div class="flex items-center justify-between gap-3">
            <h2 class="font-display text-ink-strong text-xl font-bold" :class="{ 'text-white': plan.featured }">
              {{ plan.title }}
            </h2>
            <VBadge :tone="plan.featured ? 'success' : 'info'">{{ plan.badge }}</VBadge>
          </div>
          <p class="mt-2 text-sm leading-6" :class="plan.featured ? 'text-brand-100' : 'text-ink-muted'">
            {{ plan.tagline }}
          </p>

          <div class="mt-6">
            <p class="text-2xl font-bold" :class="plan.featured ? 'text-white' : 'text-ink-strong'">
              {{ displayPrice(plan).amount }}
            </p>
            <p class="mt-1 text-xs" :class="plan.featured ? 'text-brand-200' : 'text-ink-muted'">
              {{ displayPrice(plan).suffix }}
            </p>
          </div>

          <ul class="mt-6 flex-1 space-y-2.5">
            <li
              v-for="feature in plan.features"
              :key="feature"
              class="flex gap-2 text-sm leading-6"
              :class="plan.featured ? 'text-brand-50' : 'text-ink'"
            >
              <span class="shrink-0 font-bold" :class="plan.featured ? 'text-success-300' : 'text-success-600'"
                >✓</span
              >
              {{ feature }}
            </li>
          </ul>

          <VButton
            :href="plan.href"
            class="mt-7 w-full"
            size="lg"
            :variant="plan.featured ? 'secondary' : 'primary'"
            >{{ plan.cta }}</VButton
          >
          <p
            class="mt-3 text-center text-xs"
            :class="plan.featured ? 'text-brand-200' : 'text-ink-muted'"
          >
            بدون نیاز به کارت اعتباری · قرارداد در جلسهٔ دمو
          </p>
        </div>
      </div>

      <!-- Enterprise band -->
      <div class="rounded-panel border-line bg-surface-muted mt-5 flex flex-col gap-6 border p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 class="font-display text-ink-strong text-lg font-bold">سازمانی — برای ۲۰+ سایت</h2>
          <p class="text-ink-muted mt-2 max-w-2xl text-sm leading-7">
            استقرار اختصاصی (Private Deployment)، SLA، یکپارچه‌سازی سفارشی و مشاورهٔ میدانی برای
            سازمان‌های چندسایته. قیمت بر اساس scope جلسهٔ مشاوره تعیین می‌شود.
          </p>
        </div>
        <VButton href="/contact" size="lg" variant="secondary" class="shrink-0">تماس با تیم فروش</VButton>
      </div>
    </section>

    <!-- Guarantee strip -->
    <section class="mx-auto max-w-7xl px-5 pb-12 sm:px-8 lg:px-10">
      <div class="rounded-panel bg-success-50 border-success-200 border p-6 text-center sm:p-8">
        <p class="text-success-700 font-display text-lg font-bold">🛡 ضمانت ۱۴ روزهٔ بازگشت وجه</p>
        <p class="text-ink-muted mx-auto mt-2 max-w-2xl text-sm leading-7">
          در پلن‌های حرفه‌ای و آژانس، اگر تا ۱۴ روز پس از شروع، به هر دلیلی راضی نبودید، کل مبلغ ماه
          اول بدون سؤال بازگردانده می‌شود. ما ریسک را بر عهده می‌گیریم تا شما با خیال راحت شروع
          کنید.
        </p>
      </div>
    </section>

    <!-- Comparison table -->
    <section class="border-line bg-surface border-y">
      <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
        <h2 class="font-display text-ink-strong text-2xl font-bold sm:text-3xl">
          مقایسهٔ کامل پلن‌ها
        </h2>
        <p class="text-ink-muted mt-3 max-w-2xl leading-7">
          برای اینکه دقیقاً بدانید روی چه چیزی حساب می‌کنید — بدون ابهام و سورپرایز.
        </p>
        <div class="rounded-panel border-line mt-8 overflow-x-auto border bg-white">
          <table class="w-full min-w-[640px] border-collapse text-sm">
            <thead>
              <tr class="border-line border-b bg-surface-muted/60">
                <th class="text-ink-muted px-4 py-4 text-start font-medium">امکانات</th>
                <th v-for="col in comparisonColumns" :key="col" class="px-4 py-4 text-center font-bold">
                  <span :class="col === 'حرفه‌ای' ? 'text-brand-700' : 'text-ink-strong'">{{ col }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in comparisonRows" :key="row.label" class="border-line border-b last:border-0">
                <td class="text-ink-strong px-4 py-3.5 font-semibold">{{ row.label }}</td>
                <td
                  v-for="(value, index) in row.values"
                  :key="index"
                  class="px-4 py-3.5 text-center"
                  :class="value === '✓' ? 'text-success-600 font-bold' : value === '—' ? 'text-ink-muted' : 'text-ink'"
                >
                  {{ value }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="text-ink-muted mt-4 text-xs leading-6">
          * سازمانی: جزئیات هر ردیف بر اساس scope قرارداد نهایی می‌شود.
        </p>
      </div>
    </section>

    <!-- FAQ -->
    <section class="mx-auto max-w-4xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
      <h2 class="font-display text-ink-strong text-center text-2xl font-bold sm:text-3xl">
        سؤالاتی که معمولاً می‌پرسند
      </h2>
      <div class="mt-10 space-y-3">
        <details
          v-for="faq in faqs"
          :key="faq.q"
          class="rounded-card border-line bg-surface group border p-5"
        >
          <summary
            class="text-ink-strong flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold sm:text-base"
          >
            {{ faq.q }}
            <span class="text-brand-700 transition-transform group-open:rotate-45 text-xl leading-none"
              >+</span
            >
          </summary>
          <p class="text-ink-muted mt-3 leading-7">{{ faq.a }}</p>
        </details>
      </div>
    </section>

    <!-- Final CTA -->
    <section class="mx-auto max-w-7xl px-5 pb-16 sm:px-8 lg:px-10 lg:pb-20">
      <div class="rounded-panel bg-brand-900 px-6 py-10 text-center text-white sm:px-10 sm:py-14">
        <h2 class="font-display text-2xl font-bold leading-relaxed sm:text-3xl">
          مطمئن نیستید کدام پلن مناسب شماست؟
        </h2>
        <p class="text-brand-100 mx-auto mt-3 max-w-2xl leading-8">
          در جلسهٔ دموی رایگان، وضعیت سایت خودتان را بررسی می‌کنیم و بهترین مسیر را پیشنهاد
          می‌دهیم — بدون هیچ تعهدی.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
          <VButton href="/demo" size="lg" variant="secondary">درخواست دموی اختصاصی</VButton>
          <a
            href="/contact"
            class="transition-ui rounded-ui inline-flex min-h-12 items-center justify-center gap-2 border border-white/25 bg-white/10 px-5 text-base font-semibold text-white whitespace-nowrap hover:bg-white/20"
            >تماس با تیم فروش</a
          >
        </div>
      </div>
    </section>
  </MarketingLayout>
</template>
