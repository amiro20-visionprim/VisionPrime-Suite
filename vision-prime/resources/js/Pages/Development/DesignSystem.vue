<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'

import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'
import VSelect, { type SelectOption } from '@/shared/ui/VSelect.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'
import VCard from '@/shared/ui/VCard.vue'
import VConfirmDialog from '@/shared/ui/VConfirmDialog.vue'
import VDrawer from '@/shared/ui/VDrawer.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VModal from '@/shared/ui/VModal.vue'
import VSkeleton from '@/shared/ui/VSkeleton.vue'
import VTabs, { type TabItem } from '@/shared/ui/VTabs.vue'
import VTooltip from '@/shared/ui/VTooltip.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VPagination from '@/shared/ui/VPagination.vue'
import VTable, { type TableColumn, type TableRow } from '@/shared/ui/VTable.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import VStatCard, { type StatTrend } from '@/shared/ui/VStatCard.vue'
import VGuideTip from '@/shared/ui/VGuideTip.vue'
import VBarChart, { type BarDatum } from '@/shared/ui/VBarChart.vue'
import VAreaChart, { type AreaPoint } from '@/shared/ui/VAreaChart.vue'
import VDoughnut, { type DoughnutDatum } from '@/shared/ui/VDoughnut.vue'

interface ColorToken {
  name: string
  className: string
  value: string
  darkText?: boolean
}

const colors: ColorToken[] = [
  { name: 'Brand 900', className: 'bg-brand-900', value: '#163B68' },
  { name: 'Brand 700', className: 'bg-brand-700', value: '#1E4E86' },
  { name: 'Brand 500', className: 'bg-brand-500', value: '#2B6CB0' },
  { name: 'Canvas', className: 'bg-canvas', value: '#F8FBFF', darkText: true },
  { name: 'Surface', className: 'bg-surface', value: '#FFFFFF', darkText: true },
  { name: 'Ink Strong', className: 'bg-ink-strong', value: '#23364D' },
  { name: 'Success', className: 'bg-success-600', value: '#168657' },
  { name: 'Warning', className: 'bg-warning-600', value: '#B7791F' },
  { name: 'Danger', className: 'bg-danger-600', value: '#C53030' },
]

const spacing = [4, 8, 12, 16, 20, 24, 32, 40, 48, 64]
const siteName = ref('')
const notes = ref('')
const siteType = ref('')
const currentPage = ref(2)
const tableColumns: TableColumn[] = [
  { key: 'site', label: 'سایت' },
  { key: 'status', label: 'وضعیت' },
  { key: 'url', label: 'نشانی', technical: true },
  { key: 'updated', label: 'آخرین بروزرسانی', align: 'end' },
]
const tableRows: TableRow[] = [
  {
    id: 'site-1',
    site: 'کلینیک آفتاب',
    status: 'متصل',
    url: 'https://aftab.example.ir',
    updated: '۳ دقیقه پیش',
  },
  {
    id: 'site-2',
    site: 'فروشگاه ویستا',
    status: 'نیازمند بررسی',
    url: 'https://vista.example.ir',
    updated: '۲ ساعت پیش',
  },
]

const activeTab = ref('overview')
const modalOpen = ref(false)
const drawerOpen = ref(false)
const confirmOpen = ref(false)
const confirmLoading = ref(false)
const tabs: TabItem[] = [
  { key: 'overview', label: 'نمای کلی' },
  { key: 'activity', label: 'فعالیت‌ها' },
  { key: 'settings', label: 'تنظیمات' },
]

function confirmAction(): void {
  confirmLoading.value = true
  window.setTimeout(() => {
    confirmLoading.value = false
    confirmOpen.value = false
  }, 700)
}

const siteOptions: SelectOption[] = [
  { label: 'فروشگاه اینترنتی', value: 'commerce' },
  { label: 'سایت خدماتی', value: 'services' },
  { label: 'مجله و محتوا', value: 'content' },
]

const statCards = [
  {
    label: 'بازدید از گوگل (این ماه)',
    value: 18420,
    icon: 'eye' as const,
    iconTone: 'brand' as const,
    trend: 'up' as StatTrend,
    trendLabel: '۱۸٪ نسبت به ماه قبل',
    hint: 'تعداد دفعاتی که سایت شما در نتایج جستجوی گوگل دیده شده است.',
  },
  {
    label: 'کلیک روی لینک شما',
    value: 1245,
    icon: 'trend-up' as const,
    iconTone: 'success' as const,
    trend: 'up' as StatTrend,
    trendLabel: '۲۲٪ نسبت به ماه قبل',
    hint: 'چند بار کاربران از گوگل وارد سایت شما شده‌اند.',
  },
  {
    label: 'نرخ جذابیت (CTR)',
    value: 4.2,
    icon: 'gauge' as const,
    iconTone: 'warning' as const,
    format: 'percent' as const,
    trend: 'flat' as StatTrend,
    trendLabel: 'بدون تغییر',
    hint: 'از هر ۱۰۰ نمایش، چند بار کاربر وارد سایت شما شده است.',
  },
  {
    label: 'منتظر تصمیم شما',
    value: 3,
    icon: 'user-check' as const,
    iconTone: 'violet' as const,
    trend: 'flat' as StatTrend,
    trendLabel: 'نیاز به نگاه شما',
    hint: 'پیشنهادهایی که منتظر تأیید شماست؛ بدون تأیید هیچ تغییری اعمال نمی‌شود.',
  },
]

const barData: BarDatum[] = [
  { label: 'اردیبهشت', value: 8100, muted: true },
  { label: 'خرداد', value: 9800, muted: true },
  { label: 'تیر', value: 9200, muted: true },
  { label: 'مرداد', value: 12900 },
  { label: 'شهریور', value: 14600 },
  { label: 'مهر', value: 18400, highlight: true },
]

const areaPoints: AreaPoint[] = [
  { label: '۱ تیر', value: 210 },
  { label: '۸ تیر', value: 245 },
  { label: '۱۵ تیر', value: 228 },
  { label: '۲۲ تیر', value: 310 },
  { label: '۲۹ تیر', value: 352 },
  { label: '۵ مرداد', value: 388 },
  { label: '۱۲ مرداد', value: 461 },
  { label: '۱۹ مرداد', value: 445 },
  { label: '۲۶ مرداد', value: 522 },
]

const doughnutData: DoughnutDatum[] = [
  { label: 'جستجوی مستقیم', value: 42, color: '#245c9b' },
  { label: 'گوگل', value: 35, color: '#4f46e5' },
  { label: 'شبکه‌های اجتماعی', value: 15, color: '#7c3aed' },
  { label: 'سایر', value: 8, color: '#c7ddf2' },
]
</script>

<template>
  <Head title="Design System" />

  <main class="bg-canvas min-h-screen px-5 py-10 sm:px-8 lg:px-12" dir="rtl">
    <div class="mx-auto max-w-6xl space-y-10">
      <header class="border-line border-b pb-8">
        <p class="text-brand-700 text-sm font-bold tracking-wide">VISION PRIME SUITE / DEVELOPMENT</p>
        <h1 class="font-display text-display text-ink-strong mt-3 font-bold">سیستم طراحی</h1>
        <p class="text-ink-muted mt-3 max-w-2xl leading-8">
          مرجع بصری tokenها و الگوهای پایه. این صفحه برای کنترل یکپارچگی طراحی در توسعه نگه‌داری
          می‌شود.
        </p>
      </header>

      <section aria-labelledby="color-heading">
        <h2 id="color-heading" class="text-section-title text-ink-strong font-bold">رنگ‌ها</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="color in colors"
            :key="color.name"
            class="rounded-card border-line bg-surface shadow-card overflow-hidden border"
          >
            <div
              :class="[color.className, color.darkText ? 'text-ink-strong' : 'text-white']"
              class="h-24 p-4"
            >
              <span class="text-sm font-semibold">{{ color.name }}</span>
            </div>
            <div class="flex items-center justify-between px-4 py-3 text-sm">
              <span class="text-ink">{{ color.name }}</span>
              <code class="font-latin text-ink-muted">{{ color.value }}</code>
            </div>
          </article>
        </div>
      </section>

      <section aria-labelledby="kpi-heading">
        <h2 id="kpi-heading" class="text-section-title text-ink-strong font-bold">
          کارت‌های آمار (VStatCard)
        </h2>
        <p class="text-ink-muted mt-1 mb-5 text-sm">
          کارت KPI با آیکون، شمارندهٔ متحرک، روند و نکتهٔ راهنمای «💡» — مخصوص کاربر غیرفنی.
        </p>
        <div v-stagger class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <VStatCard
            v-for="card in statCards"
            :key="card.label"
            v-bind="card"
          />
        </div>
      </section>

      <section aria-labelledby="charts-heading">
        <h2 id="charts-heading" class="text-section-title text-ink-strong font-bold">چارت‌ها</h2>
        <p class="text-ink-muted mt-1 mb-5 text-sm">
          چارت‌های گرادیانی با انیمیشن ورود و tooltip — بدون وابستگی جدید (SVG سفارشی).
        </p>
        <div class="grid gap-5 lg:grid-cols-3">
          <VCard title="میله‌ای (VBarChart)" description="مقایسهٔ ماهانه — ماه‌های خنثی با مقایسهٔ قبل">
            <VBarChart :data="barData" unit=" بازدید" aria-label="بازدید ماهانه" />
          </VCard>
          <VCard title="مساحت (VAreaChart)" description="روند هفتگی با گرادیان و tooltip">
            <VAreaChart :points="areaPoints" unit=" کلیک" aria-label="روند کلیک هفتگی" />
          </VCard>
          <VCard title="دونات (VDoughnut)" description="سهم منابع ترافیک">
            <VDoughnut :data="doughnutData" center-label="جمع" center-value="۱۰۰٪" />
          </VCard>
        </div>
      </section>

      <section aria-labelledby="icons-heading">
        <h2 id="icons-heading" class="text-section-title text-ink-strong font-bold">آیکون‌ها (VIcon)</h2>
        <p class="text-ink-muted mt-1 mb-5 text-sm">
          نگاشت یکپارچهٔ آیکون‌ها؛ هر وضعیت در کل سوئیت یک آیکون ثابت دارد.
        </p>
        <div class="rounded-card border-line bg-surface shadow-card flex flex-wrap gap-5 border p-6">
          <div v-for="name in (['eye','trend-up','trend-down','check','x','user-check','clock','calendar','sparkles','support'] as const)" :key="name" class="flex items-center gap-2">
            <VIcon :name="name" tone="brand" size="lg" />
            <span class="font-latin text-ink-muted text-xs">{{ name }}</span>
          </div>
          <div class="flex items-center gap-2">
            <VGuideTip text="مثلاً: بدون تأیید شما هیچ تغییری روی سایت اعمال نمی‌شود." />
            <span class="text-ink-muted text-xs">VGuideTip (نگه‌دارید)</span>
          </div>
        </div>
      </section>

      <section
        class="rounded-panel border-line bg-surface shadow-card border p-6"
        aria-labelledby="controls-heading"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 id="controls-heading" class="text-section-title text-ink-strong font-bold">
              کنترل‌ها و بازخورد
            </h2>
            <p class="text-ink-muted mt-1 text-sm">
              نمونه حالت‌های پایه برای تمام فرم‌ها و اقدام‌ها.
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <VBadge>خنثی</VBadge>
            <VBadge tone="success">موفق</VBadge>
            <VBadge tone="warning">نیازمند بررسی</VBadge>
            <VBadge tone="danger">خطا</VBadge>
          </div>
        </div>

        <div class="mt-6 grid gap-5 md:grid-cols-2">
          <VInput
            v-model="siteName"
            label="نام سایت"
            required
            hint="این نام در فضای کاری تیم نمایش داده می‌شود."
            placeholder="مثلاً کلینیک آفتاب"
          />
          <VSelect
            v-model="siteType"
            label="نوع سایت"
            :options="siteOptions"
            required
            error="برای ادامه، نوع سایت را انتخاب کنید."
          />
          <VTextarea
            v-model="notes"
            class="md:col-span-2"
            label="یادداشت پروژه"
            placeholder="هدف تجاری یا نکات مهم این سایت را بنویسید..."
          />
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
          <VButton>افزودن سایت</VButton>
          <VButton variant="secondary">پیش‌نمایش</VButton>
          <VButton variant="ghost">انصراف</VButton>
          <VButton variant="danger">حذف</VButton>
          <VButton loading>در حال ذخیره</VButton>
        </div>

        <div class="mt-6 space-y-3">
          <VAlert title="اتصال سرچ کنسول کامل شد" tone="success"
            >داده‌های عملکرد سایت آماده دریافت هستند.</VAlert
          >
          <VAlert title="بررسی لازم است" tone="warning" dismissible
            >تنظیمات اتصال وردپرس هنوز تکمیل نشده است.</VAlert
          >
        </div>
      </section>

      <section class="space-y-6" aria-labelledby="navigation-heading">
        <div>
          <h2 id="navigation-heading" class="text-section-title text-ink-strong font-bold">
            ناوبری و داده
          </h2>
          <p class="text-ink-muted mt-1 text-sm">
            الگوی استاندارد برای صفحه‌های عملیاتی و فهرست‌های بزرگ.
          </p>
        </div>

        <VCard>
          <VPageHeader
            title="سایت‌ها"
            description="سایت‌های متصل به پروژه را مدیریت و وضعیت اتصال آن‌ها را بررسی کنید."
            :breadcrumbs="[
              { label: 'فضای کاری', href: '/app/dashboard' },
              { label: 'پروژه‌ها', href: '/app/projects' },
              { label: 'سایت‌ها' },
            ]"
            :status="{ label: '۲ سایت فعال', tone: 'success' }"
          >
            <template #actions><VButton size="sm">افزودن سایت</VButton></template>
          </VPageHeader>
        </VCard>

        <VCard
          title="نمونه جدول عملیاتی"
          description="جدول‌ها در موبایل به کارت قابل اسکن تبدیل می‌شوند."
        >
          <VTable :columns="tableColumns" :rows="tableRows" row-key="id" mobile-mode="cards">
            <template #cell-status="{ value }"
              ><VBadge :tone="value === 'متصل' ? 'success' : 'warning'">{{
                value
              }}</VBadge></template
            >
          </VTable>
          <div class="mt-5"><VPagination v-model="currentPage" :total-pages="12" /></div>
        </VCard>
      </section>

      <section class="space-y-6" aria-labelledby="feedback-heading">
        <div>
          <h2 id="feedback-heading" class="text-section-title text-ink-strong font-bold">
            کانتینر و بازخورد
          </h2>
          <p class="text-ink-muted mt-1 text-sm">
            الگوهای پایه برای داشبورد، حالت‌های خالی و گفت‌وگوهای حساس.
          </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
          <VCard
            title="وضعیت همگام‌سازی"
            description="آخرین داده‌های سایت در حال آماده‌سازی هستند."
          >
            <template #action><VBadge tone="info">در حال پردازش</VBadge></template>
            <div class="space-y-3">
              <VSkeleton height="1rem" width="72%" />
              <VSkeleton height="1rem" width="88%" />
              <VSkeleton height="1rem" width="56%" />
            </div>
          </VCard>

          <VEmptyState
            title="هنوز سایتی اضافه نشده است"
            description="با افزودن اولین سایت، مسیر اتصال سرچ کنسول و وردپرس را شروع کنید."
            action-label="افزودن سایت"
          />
        </div>

        <VCard>
          <VTabs v-model="activeTab" :tabs="tabs">
            <template #overview
              ><p class="text-ink leading-7">
                خلاصه عملکرد و فرصت‌های کلیدی در این بخش نمایش داده می‌شود.
              </p></template
            >
            <template #activity
              ><p class="text-ink leading-7">
                رویدادها و تغییرات اخیر سایت در این بخش قرار می‌گیرند.
              </p></template
            >
            <template #settings
              ><p class="text-ink leading-7">
                تنظیمات مرتبط با این سایت در این بخش قابل مدیریت هستند.
              </p></template
            >
          </VTabs>
        </VCard>

        <div class="flex flex-wrap items-center gap-3">
          <VButton variant="secondary" @click="modalOpen = true">بازکردن Modal</VButton>
          <VButton variant="secondary" @click="drawerOpen = true">بازکردن Drawer</VButton>
          <VButton variant="danger" @click="confirmOpen = true">Dialog تأیید حذف</VButton>
          <VTooltip text="توضیح کوتاه برای یک مفهوم یا کنترل"
            ><button
              type="button"
              class="rounded-ui border-line text-ink hover:bg-surface-muted border px-3 py-2 text-sm"
            >
              راهنما
            </button></VTooltip
          >
        </div>
      </section>

      <VModal v-model="modalOpen" title="جزئیات فرصت رشد">
        <p class="text-ink leading-7">
          Modal برای نمایش اطلاعات مهم بدون خارج‌کردن کاربر از مسیر فعلی استفاده می‌شود.
        </p>
        <template #footer
          ><div class="flex justify-end">
            <VButton @click="modalOpen = false">متوجه شدم</VButton>
          </div></template
        >
      </VModal>

      <VDrawer v-model="drawerOpen" title="فیلتر فرصت‌ها">
        <p class="text-ink leading-7">
          Drawer در موبایل و فضای عملیاتی برای فیلترها یا navigation ثانویه استفاده خواهد شد.
        </p>
      </VDrawer>

      <VConfirmDialog
        v-model="confirmOpen"
        title="حذف سایت"
        description="این عمل قابل بازگشت نیست. آیا از حذف این سایت مطمئن هستید؟"
        confirm-label="بله، حذف شود"
        :loading="confirmLoading"
        tone="danger"
        @confirm="confirmAction"
      />

      <section class="grid gap-6 lg:grid-cols-2" aria-label="Typography and elevation">
        <article class="rounded-panel border-line bg-surface shadow-card border p-6">
          <h2 class="text-section-title text-ink-strong font-bold">تایپوگرافی</h2>
          <div class="mt-6 space-y-5">
            <p class="font-display text-display text-ink-strong font-bold">
              تیتر نمایشی سوئیت
            </p>
            <p class="text-page-title text-ink-strong font-bold">عنوان اصلی صفحه</p>
            <p class="text-section-title text-ink-strong font-semibold">عنوان بخش</p>
            <p class="text-ink leading-8">
              متن رابط کاربری باید روشن، حرفه‌ای و نتیجه‌محور باشد؛ بدون پیچیدگی غیرضروری و ترجمه
              تحت‌اللفظی.
            </p>
            <p class="font-latin text-ink-muted text-sm" dir="ltr">
              SEO opportunity · URL · CTR · 12,450 impressions
            </p>
          </div>
        </article>

        <article class="rounded-panel border-line bg-surface shadow-card border p-6">
          <h2 class="text-section-title text-ink-strong font-bold">فاصله و لایه‌بندی</h2>
          <div class="mt-6 space-y-4">
            <div v-for="item in spacing" :key="item" class="flex items-center gap-4">
              <span class="font-latin text-ink-muted w-8 text-xs">{{ item }}</span>
              <div class="bg-brand-600 h-4 rounded-full" :style="{ width: `${item * 3}px` }" />
            </div>
          </div>
          <div class="mt-8 grid grid-cols-2 gap-4">
            <div class="rounded-card border-line bg-surface-muted border p-4">Radius Card</div>
            <div class="rounded-panel bg-surface shadow-panel p-4">Shadow Panel</div>
          </div>
        </article>
      </section>
    </div>
  </main>
</template>
