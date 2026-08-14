<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { FileText, ShieldCheck, Stethoscope } from '@lucide/vue'

import AudienceCasePreview from '@/marketing/components/audience/AudienceCasePreview.vue'
import AudienceCompetitiveMatrix from '@/marketing/components/audience/AudienceCompetitiveMatrix.vue'
import AudienceCtaBanner from '@/marketing/components/audience/AudienceCtaBanner.vue'
import AudienceFaq from '@/marketing/components/audience/AudienceFaq.vue'
import AudienceHowItWorks from '@/marketing/components/audience/AudienceHowItWorks.vue'
import AudiencePageHero from '@/marketing/components/audience/AudiencePageHero.vue'
import AudiencePainPoints from '@/marketing/components/audience/AudiencePainPoints.vue'
import AudienceWhySection from '@/marketing/components/audience/AudienceWhySection.vue'
import AudienceWorkflow from '@/marketing/components/audience/AudienceWorkflow.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import RelatedAudiences from '@/marketing/components/audience/RelatedAudiences.vue'
import { trackAudienceCta, withUtm } from '@/lib/analytics'
import type {
  AudienceFaq as Faq,
  AudienceLink,
  AudienceMatrixRow,
  AudiencePain,
  AudienceStep,
  AudienceWhyItem,
} from '@/types/audience'

const pains: AudiencePain[] = [
  {
    title: 'بیماران شما را در گوگل پیدا نمی‌کنند',
    text: 'سایت کلینیک معمولاً فقط اطلاعات تماس دارد؛ بیمار برای «دندانپزشک نزدیک من» سراغ رقیبی می‌رود که در جستجو بهتر دیده می‌شود.',
    solution: 'Vision Prime کلمات و صفحاتی را که بیمار واقعاً جستجو می‌کند از دادهٔ سرچ کنسول پیدا و اولویت‌بندی می‌کند؛ بدون دانش فنی.',
  },
  {
    title: 'وابستگی به تبلیغات پولی',
    text: 'هر ماه برای کلیک‌های تبلیغاتی هزینه می‌کنید و به محض قطع بودجه، تماس‌ها قطع می‌شود؛ رشد ارگانیک هیچ‌وقت ساخته نشده است.',
    solution: 'با بهینه‌سازی صفحات خدمات و محتوای پاسخ‌گو، ترافیک رایگان و پایدار جایگزین کلیک‌های پولی می‌شود.',
  },
  {
    title: 'تغییر روی سایت درمانی حساس است',
    text: 'اطلاعات پزشکی باید دقیق و مطابق مقررات باشد؛ هر تغییری با ترس و وابسته به یک نفر انجام می‌شود و قابل پیگیری نیست.',
    solution: 'گردش‌کار تأیید + rollback؛ هر تغییر قبل از انتشار از سیاست سایت شما عبور می‌کند و قابل بازگشت است.',
  },
]

const workflow: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی وردپرس نصب و سرچ کنسول متصل می‌شود؛ بدون نیاز به تیم فنی.' },
  { title: 'تحلیل', text: 'کلمات کلیدی، صفحات خدمات و محتوای آموزشی به تفکیک بازدید و تماس تحلیل می‌شوند.' },
  { title: 'اولویت‌بندی', text: 'فرصت‌هایی که بیشترین شانس جذب بیمار را دارند، اول اجرا می‌شوند.' },
  { title: 'اجرا و گزارش', text: 'تغییرات با تأیید شما منتشر می‌شوند و اثرشان در گزارش ماهانه قابل ارائه است.' },
]

const faqs: Faq[] = [
  {
    q: 'برای اتصال به تیم فنی نیاز دارم؟',
    a: 'خیر؛ پلاگین وردپرس نصب می‌شود و اتصال سرچ کنسول از پنل انجام می‌شود. تمام مراحل با راهنمایی ما پیش می‌رود.',
  },
  {
    q: 'آیا اطلاعات بیماران در خطر است؟',
    a: 'خیر؛ ما به دادهٔ جستجوی سایت (سرچ کنسول) دسترسی داریم، نه به پرونده یا اطلاعات بیماران. هیچ داده‌ای به جز آمار جستجو پردازش نمی‌شود.',
  },
  {
    q: 'نتیجه را چطور می‌بینم؟',
    a: 'گزارش ماهانه به زبان ساده: چند جستجو، چند بازدید، چند تماس. همان گزارشی که می‌توانید به شرکای کلینیک ارائه کنید.',
  },
  {
    q: 'برای کلینیک تازه‌کار هم مفید است؟',
    a: 'بله؛ اگر سایت وردپرسی و سرچ کنسول دارید، از روز اول مشخص می‌شود بیماران چه می‌جویند و کجا فرصت دارید.',
  },
]

const related: AudienceLink[] = [
  {
    href: '/for-agencies',
    title: 'آژانس‌های SEO',
    description: 'برای تیم‌هایی که چند سایت درمانی را همزمان مدیریت می‌کنند.',
  },
  {
    href: '/for-ecommerce',
    title: 'فروشگاه‌های اینترنتی',
    description: 'برای کسب‌وکارهایی که هر بازدید را به فروش تبدیل می‌کنند.',
  },
  {
    href: '/pricing',
    title: 'قیمت‌گذاری',
    description: 'پلن‌ها را مقایسه کنید و پلن مناسب کلینیک را پیدا کنید.',
  },
]

const why: AudienceWhyItem[] = [
  {
    icon: Stethoscope,
    title: 'تمرکز روی جستجوی محلی',
    text: 'به‌جای رقابت کلی، روی کلماتی تمرکز کنید که بیمارِ نزدیک شما جستجو می‌کند.',
  },
  {
    icon: FileText,
    title: 'محتوای درمانی هدفمند',
    text: 'پاسخ به سؤالات بیمار با صفحات خدمات اختصاصی؛ هر صفحه برای یک درمان.',
  },
  {
    icon: ShieldCheck,
    title: 'تغییر دقیق و امن',
    text: 'گردش‌کار تأیید و rollback؛ محتوای پزشکی بدون ریسک به‌روزرسانی می‌شود.',
  },
]

const matrixRows: AudienceMatrixRow[] = [
  {
    concern: 'بیمار ما را پیدا نمی‌کند',
    scenario: '«دندانپزشک نزدیک من» → رقیب.',
    vision: 'جستجوی محلی با دادهٔ واقعی جستجو',
    inhouse: 'بدون تخصص و ابزار محلی',
    saas: 'انگلیسی‌محور، بدون بافت ایران',
  },
  {
    concern: 'وابستگی به تبلیغات پولی',
    scenario: 'بودجه قطع می‌شود، تماس‌ها قطع می‌شوند.',
    vision: 'رشد ارگانیک پایدار با محتوای خدمات',
    inhouse: 'فرایند کند و بدون داده',
    saas: 'بدون مسیر اجرا و انتشار',
  },
  {
    concern: 'محتوای درمانی حساس',
    scenario: 'یک اشتباه یعنی اعتماد از دست رفته.',
    vision: 'گردش‌کار تأیید + rollback',
    inhouse: 'وابسته به یک نفر، بدون تاریخچه',
    saas: 'بدون کنترل انتشار',
  },
]

const steps3: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی وردپرس نصب و سرچ کنسول متصل می‌شود؛ بدون نیاز به تیم فنی.' },
  { title: 'تحلیل', text: 'کلمات کلیدی، صفحات خدمات و محتوای آموزشی به تفکیک بازدید و تماس تحلیل می‌شوند.' },
  { title: 'اقدام', text: 'تغییرات با تأیید شما منتشر و اثرشان روی تماس و مراجعه سنجیده می‌شود.' },
]

function onCta(): void {
  trackAudienceCta('clinics')
}
</script>

<template>
  <Head title="لندینگ اختصاصی کلینیک‌ها" />
  <MarketingLayout>
    <main>
      <AudiencePageHero
        badge="برای کلینیک‌ها و مراکز درمانی"
        title-before="بیمار جدید را از"
        gradient-word="گوگل"
        description="Vision Prime سایت کلینیک شما را با دادهٔ سرچ کنسول تحلیل می‌کند: بیماران چه می‌جویند، کدام صفحات آن‌ها را به تماس می‌رسانند و کجا فرصت از دست می‌رود — بدون نیاز به دانش فنی."
        :stats="[
          { to: 2, suffix: '×', label: 'بازدید جستجوی محلی' },
          { to: 40, suffix: '٪', label: 'کاهش وابستگی به تبلیغات' },
          { to: 100, suffix: '٪', label: 'تغییرات با تأیید شما' },
          { to: 70, suffix: '٪', label: 'بیماران جدید از جستجو' },
        ]"
        cta-label="درخواست دموی تخصصی کلینیک"
        :cta-href="withUtm('/demo', 'landing_clinics')"
        @cta-click="onCta"
      />

      <AudiencePainPoints :pains="pains" />

      <AudienceWorkflow :steps="workflow" />

      <AudienceCasePreview
        title="نمونهٔ جریان کار: از جستجوی بیمار تا تماس تلفنی."
        description="برای یک کلینیک نمونه با ۴ شعبه — اعداد نمایشی برای نشان دادن شکل خروجی هستند."
        narrative-title="بیماری که جستجو می‌کند، باید شما را ببیند."
        narrative="بیمار «دندانپزشک نزدیک من» را تایپ می‌کند و ظرف چند ثانیه تصمیم می‌گیرد. Vision Prime نشان می‌دهد بیماران دقیقاً چه عبارتی را جستجو می‌کنند، کدام صفحه جواب را دارد و کجا رقیب بهتر دیده می‌شود — تا تصمیم در چند روز گرفته شود، نه چند ماه."
        delta="+۲۷٪"
        :points="[
          { label: 'کلمات کلیدی محلی فعال', value: '۳۱', hint: '«دندانپزشک نزدیک من» و مشابه' },
          { label: 'صفحات خدمات بهینه‌شده', value: '۱۲', hint: 'هر صفحه برای یک درمان خاص' },
          { label: 'افزایش تماس تلفنی', value: '+۲۷٪', hint: 'در سه ماه اول' },
        ]"
      />

      <AudienceWhySection
        eyebrow="WHY CLINICS"
        title="سه دلیلی که کلینیک‌ها با Vision Prime بیمار جذب می‌کنند."
        :items="why"
      />

      <AudienceCompetitiveMatrix :rows="matrixRows" />

      <AudienceFaq :faqs="faqs" />

      <AudienceHowItWorks :steps="steps3" />

      <AudienceCtaBanner
        title="کلینیک‌تان را در جستجوهای محلی جلو بیندازید."
        description="در یک دموی ۴۵ دقیقه‌ای، جستجوهای بیماران در شهر شما و فرصت‌های سایت‌تان را با هم بررسی می‌کنیم."
        cta-label="درخواست دموی تخصصی کلینیک"
        campaign="landing_clinics"
        @cta-click="onCta"
      />

      <RelatedAudiences :links="related" />
    </main>
  </MarketingLayout>
</template>
