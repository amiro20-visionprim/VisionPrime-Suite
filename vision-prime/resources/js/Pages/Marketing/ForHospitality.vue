<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { CalendarDays, MapPin, ShieldCheck } from '@lucide/vue'

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
    title: 'مسافران مقصد شما را پیدا نمی‌کنند',
    text: 'صفحات مقصد شما در نتایج گوگل نیستند؛ مسافر برای «هتل در شهر X» سراغ OTA یا رقیب می‌رود و کمیسیون سنگینی پرداخت می‌شود.',
    solution: 'Vision Prime کلمات و صفحاتی را که مسافر برای مقصد شما جستجو می‌کند پیدا و اولویت‌بندی می‌کند.',
  },
  {
    title: 'فصل‌ها غیرقابل پیش‌بینی‌اند',
    text: 'محتوا و صفحات برای فصل پررونق آماده نیستند؛ تا فصل که می‌رسد، رقبا جلوترند و فرصت از دست رفته است.',
    solution: 'بازه‌های اوج جستجو از دادهٔ سرچ کنسول شناسایی می‌شوند و محتوا قبل از فصل آماده می‌شود.',
  },
  {
    title: 'وابستگی به پلتفرم‌های رزرو',
    text: 'بخش بزرگی از درآمد از OTA ها می‌آید و کمیسیون می‌گیرند؛ رزرو مستقیم ساخته نشده و مشتری وفادار هم ندارید.',
    solution: 'با تقویت سایت خودتان، رزرو مستقیم (بدون کمیسیون) افزایش می‌یابد و مشتری مستقیم جذب می‌شود.',
  },
]

const workflow: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی وردپرس نصب و سرچ کنسول متصل می‌شود؛ بدون نیاز به تیم فنی.' },
  { title: 'تحلیل', text: 'صفحات مقصد، اتاق‌ها و محتوای سفر به تفکیک بازدید و رزرو تحلیل می‌شوند.' },
  { title: 'اولویت‌بندی', text: 'فرصت‌های فصل پررونق و صفحات رزرو مستقیم رتبه‌بندی می‌شوند.' },
  { title: 'اجرا و گزارش', text: 'تغییرات با تأیید شما منتشر و اثرشان در گزارش ماهانه نشان داده می‌شود.' },
]

const faqs: Faq[] = [
  {
    q: 'آیا با OTA ها رقابت می‌کنید؟',
    a: 'نه؛ ما سایت خودتان را قوی می‌کنیم تا رزرو مستقیم (بدون کمیسیون) بیشتر شود. OTA ها جای خودشان را دارند؛ هدف کاهش وابستگی به آن‌هاست.',
  },
  {
    q: 'محتوای فصلی را چطور می‌فهمید؟',
    a: 'از دادهٔ سرچ کنسول، بازه‌های اوج جستجوی مقصد شما شناسایی می‌شود و محتوا و صفحات قبل از آن بازه آماده می‌شوند.',
  },
  {
    q: 'برای اتصال به تیم فنی نیاز دارم؟',
    a: 'خیر؛ پلاگین وردپرس نصب می‌شود و اتصال سرچ کنسول از پنل انجام می‌شود. تمام مراحل با راهنمایی ما پیش می‌رود.',
  },
  {
    q: 'نتیجه را چطور می‌بینم؟',
    a: 'گزارش ماهانه به زبان ساده: بازدید صفحات مقصد، تماس‌ها و رزروهای مستقیم و اثر هر تغییر.',
  },
]

const related: AudienceLink[] = [
  {
    href: '/for-education',
    title: 'مراکز آموزشی',
    description: 'برای آموزشگاه‌هایی که در فصل ثبت‌نام جذب هنرجو می‌کنند.',
  },
  {
    href: '/for-ecommerce',
    title: 'فروشگاه‌های اینترنتی',
    description: 'برای کسب‌وکارهایی که هر بازدید را به فروش تبدیل می‌کنند.',
  },
  {
    href: '/pricing',
    title: 'قیمت‌گذاری',
    description: 'پلن‌ها را مقایسه کنید و پلن مناسب کسب‌وکار خود را پیدا کنید.',
  },
]

const why: AudienceWhyItem[] = [
  {
    icon: MapPin,
    title: 'تمرکز روی مقصد',
    text: 'کلمات و صفحاتی که مسافر برای شهر شما جستجو می‌کند، اولویت می‌گیرند.',
  },
  {
    icon: CalendarDays,
    title: 'آماده برای فصل',
    text: 'محتوای فصلی قبل از اوج سفر آماده است؛ وقتی مسافر جستجو می‌کند، شما دیده می‌شوید.',
  },
  {
    icon: ShieldCheck,
    title: 'تغییر دقیق و امن',
    text: 'گردش‌کار تأیید و rollback؛ محتوای سایت زنده بدون ریسک به‌روزرسانی می‌شود.',
  },
]

const matrixRows: AudienceMatrixRow[] = [
  {
    concern: 'رزرو مستقیم',
    scenario: 'هر رزرو از OTA کمیسیون سنگین دارد.',
    vision: 'تقویت سایت برای رزرو مستقیم بدون واسطه',
    inhouse: 'بدون دادهٔ کافی برای تصمیم',
    saas: 'بدون مسیر اجرا روی سایت',
  },
  {
    concern: 'فصل سفر',
    scenario: 'اوج سفر از راه می‌رسد و محتوا آماده نیست.',
    vision: 'شناسایی اوج جستجو از GSC، قبل از فصل',
    inhouse: 'دیر و دستی',
    saas: 'بدون بافت محلی و فصلی',
  },
  {
    concern: 'مقصد ناشناخته در گوگل',
    scenario: 'مسافر «هتل در شهر X» را جستجو می‌کند.',
    vision: 'صفحات مقصد با کلمات واقعی مسافر',
    inhouse: 'حدس می‌زند، بدون داده',
    saas: 'دادهٔ عمومی بدون مختصات محلی',
  },
]

const steps3: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی وردپرس نصب و سرچ کنسول متصل می‌شود؛ بدون نیاز به تیم فنی.' },
  { title: 'تحلیل', text: 'صفحات مقصد، اتاق‌ها و محتوای سفر به تفکیک بازدید و رزرو تحلیل می‌شوند.' },
  { title: 'اقدام', text: 'تغییرات با تأیید شما منتشر و اثرشان روی رزرو مستقیم سنجیده می‌شود.' },
]

function onCta(): void {
  trackAudienceCta('hospitality')
}
</script>

<template>
  <Head title="لندینگ اختصاصی سفر و هتلداری" />
  <MarketingLayout>
    <main>
      <AudiencePageHero
        badge="برای هتل‌ها، اقامتگاه‌ها و کسب‌وکارهای سفر"
        title-before="هر جستجوی مقصد را به"
        gradient-word="رزرو"
        description="Vision Prime سایت شما را با دادهٔ سرچ کنسول تحلیل می‌کند: مسافران مقصد شما را چگونه جستجو می‌کنند، کدام صفحات رزرو می‌آورند و محتوای فصلی کجا از دست می‌رود."
        :stats="[
          { to: 3, suffix: '×', label: 'بازدید صفحات مقصد' },
          { to: 55, suffix: '٪', label: 'رشد جستجوی فصلی' },
          { to: 100, suffix: '٪', label: 'تغییرات با تأیید شما' },
          { to: 35, suffix: '٪', label: 'افزایش رزرو مستقیم' },
        ]"
        cta-label="درخواست دموی تخصصی هتلداری"
        :cta-href="withUtm('/demo', 'landing_hospitality')"
        @cta-click="onCta"
      />

      <AudiencePainPoints :pains="pains" />

      <AudienceWorkflow :steps="workflow" />

      <AudienceCasePreview
        title="نمونهٔ جریان کار: از جستجوی مقصد تا رزرو مستقیم."
        description="برای یک اقامتگاه نمونه با ۱۸ واحد — اعداد نمایشی برای نشان دادن شکل خروجی هستند."
        narrative-title="مسافری که جستجو می‌کند، باید رزرو مستقیم بدهد."
        narrative="هر رزرو از طریق OTA کمیسیون دارد. Vision Prime نشان می‌دهد مسافران مقصد شما را با چه عبارتی جستجو می‌کنند و کدام صفحه بیشترین شانس تبدیل به رزرو مستقیم را دارد — تا هر بازدیدِ رایگان، به درآمد بدون واسطه تبدیل شود."
        delta="+۲۴٪"
        :points="[
          { label: 'صفحات مقصد با شکاف CTR', value: '۱۷', hint: 'رتبهٔ ۴–۱۰ در جستجوی مقصد' },
          { label: 'فرصت فصلی فعال', value: '۶', hint: 'قبل از اوج سفر تابستان' },
          { label: 'افزایش رزرو مستقیم', value: '+۲۴٪', hint: 'بدون کمیسیون OTA' },
        ]"
      />

      <AudienceWhySection
        eyebrow="WHY HOSPITALITY"
        title="سه دلیلی که کسب‌وکارهای سفر با Vision Prime رزرو مستقیم می‌سازند."
        :items="why"
      />

      <AudienceCompetitiveMatrix :rows="matrixRows" />

      <AudienceFaq :faqs="faqs" />

      <AudienceHowItWorks :steps="steps3" />

      <AudienceCtaBanner
        title="رزرو مستقیم را از گوگل شروع کنید."
        description="در یک دموی ۴۵ دقیقه‌ای، جستجوهای مسافران مقصد شما و فرصت‌های فصل بعد را با هم بررسی می‌کنیم."
        cta-label="درخواست دموی تخصصی هتلداری"
        campaign="landing_hospitality"
        @cta-click="onCta"
      />

      <RelatedAudiences :links="related" />
    </main>
  </MarketingLayout>
</template>
