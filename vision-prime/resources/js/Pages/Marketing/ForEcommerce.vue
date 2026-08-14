<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ShieldCheck, ShoppingBag, TrendingUp } from '@lucide/vue'

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
    title: 'صفحات محصولی که هیچ‌کس نمی‌بیند',
    text: 'محصولات باکیفیت دارید ولی رتبه‌های ۵ تا ۱۰ گوگل هیچ بازدیدی نمی‌آورند؛ انبار پر می‌شود و تیم بازاریابی نمی‌داند از کجا شروع کند.',
    solution: 'Vision Prime صفحات پول‌ساز را با شکاف CTR و intent پیدا می‌کند و اولویت اجرا را بر اساس اثر فروش رتبه‌بندی می‌کند — نه بر اساس سلیقه.',
  },
  {
    title: 'فصل‌ها می‌گذرند و فرصت می‌سوزد',
    text: 'محتوای فصلی (تخفیف‌ها، کالاهای پرفروش، مناسبت‌ها) دستی و دیر آماده می‌شود؛ رقبا هفته‌ها زودتر دیده می‌شوند و مشتری سراغ آن‌ها می‌رود.',
    solution: 'فرصت‌های فصلی از دادهٔ GSC شناسایی می‌شوند و به پیشنهاد تغییر زمان‌بندی‌شده تبدیل می‌شوند — قبل از اینکه فصل بگذرد.',
  },
  {
    title: 'تغییر روی سایت زنده، ریسک است',
    text: 'یک تغییر اشتباه روی صفحهٔ محصول یا checkout می‌تواند فروش را مختل کند؛ برای همین تیم‌ها از بهینه‌سازی عقب‌نشینی می‌کنند.',
    solution: 'گردش‌کار تأیید + rollback؛ هر تغییر قبل از اجرا از سیاست سایت شما عبور می‌کند و در هر لحظه قابل بازگشت است.',
  },
]

const workflow: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی ووکامرس نصب می‌شود؛ سرچ کنسول متصل و محصولات و دسته‌بندی‌ها همگام می‌شوند.' },
  { title: 'تحلیل', text: 'صفحات محصول، دسته‌بندی و مقالات به تفکیک بازدید، CTR و ارزش فروش تحلیل می‌شوند.' },
  { title: 'اولویت‌بندی', text: 'فرصت‌هایی که بیشترین اثر روی فروش دارند اول می‌آیند؛ بقیه در صف بررسی.' },
  { title: 'اجرا و سنجش', text: 'تغییرات با تأیید شما اجرا می‌شوند و اثرشان روی بازدید و فروش اندازه‌گیری می‌شود.' },
]

const faqs: Faq[] = [
  {
    q: 'آیا Vision Prime با ووکامرس کار می‌کند؟',
    a: 'بله؛ پلاگین روی وردپرس/ووکامرس نصب می‌شود و با سرچ کنسول شما همگام است. محصولات، دسته‌بندی‌ها، برندها و صفحات فرود همگی قابل تحلیل‌اند و فرصت‌های هر کدام جداگانه دیده می‌شود.',
  },
  {
    q: 'آیا تغییرات مستقیم روی سایت زنده اعمال می‌شود؟',
    a: 'فقط با تأیید صریح شما. هر تغییر قبل از اجرا از سیاست سایت (که خودتان تعریف می‌کنید) عبور می‌کند و با یک کلیک قابل بازگشت است؛ ردپای کامل هم در audit trail ثبت می‌شود.',
  },
  {
    q: 'نتیجه را به چه زبانی می‌بینم؟',
    a: 'گزارش ماهانه به زبان فروش: بازدید درآمدزا، CTR و اثر هر تغییر روی فروش. بدون اصطلاح فنی مبهم — همان چیزی که برای مدیر فروشگاه یا سهامدار قابل ارائه است.',
  },
  {
    q: 'برای فروشگاه کوچک هم به‌صرفه است؟',
    a: 'بله؛ اگر وردپرس و سرچ کنسول دارید، از روز اول فرصت‌های مشخص و رتبه‌بندی‌شده می‌بینید — بدون نیاز به تیم فنی بزرگ. پلن پایه دقیقاً برای همین شروع طراحی شده است.',
  },
]

const related: AudienceLink[] = [
  {
    href: '/for-agencies',
    title: 'آژانس‌های SEO',
    description: 'برای تیم‌هایی که چند فروشگاه را همزمان مدیریت می‌کنند.',
  },
  {
    href: '/for-clinics',
    title: 'کلینیک‌ها و مراکز درمانی',
    description: 'برای مراکزی که جذب بیمار از جستجوی محلی برایشان حیاتی است.',
  },
  {
    href: '/pricing',
    title: 'قیمت‌گذاری',
    description: 'پلن‌ها را مقایسه کنید و پلن مناسب فروشگاه خود را پیدا کنید.',
  },
]

const why: AudienceWhyItem[] = [
  {
    icon: ShoppingBag,
    title: 'تمرکز روی صفحات پول‌ساز',
    text: 'به‌جای بهینه‌سازی کل سایت، روی صفحه‌هایی سرمایه‌گذاری کنید که مستقیماً فروش می‌آورند.',
  },
  {
    icon: TrendingUp,
    title: 'اثر به زبان فروش',
    text: 'گزارش می‌گوید هر تغییر چند بازدید و چند فروش اضافه کرد؛ نه فقط «بهبود رتبه».',
  },
  {
    icon: ShieldCheck,
    title: 'تغییر بدون ریسک',
    text: 'گردش‌کار تأیید و rollback؛ هیچ تغییری بدون اجازهٔ شما روی سایت زنده اعمال نمی‌شود.',
  },
]

const matrixRows: AudienceMatrixRow[] = [
  {
    concern: 'کدام صفحه را اول اصلاح کنم؟',
    scenario: 'هزاران محصول، بودجه و زمان محدود.',
    vision: 'صفحات پول‌ساز با شکاف CTR و اولویت تجاری',
    inhouse: 'سلیقه و تجربه، بدون دادهٔ قطعی',
    saas: 'دادهٔ خام بدون اولویت تجاری',
  },
  {
    concern: 'فصل‌ها و تخفیف‌ها',
    scenario: 'محتوای فصلی دیر آماده می‌شود و فرصت می‌سوزد.',
    vision: 'فرصت فصلی از GSC، قبل از اوج',
    inhouse: 'دیر متوجه می‌شود و دستی عمل می‌کند',
    saas: 'بدون بافت تجاری و فصلی',
  },
  {
    concern: 'تغییر روی صفحهٔ محصول',
    scenario: 'یک اشتباه یعنی فروش از دست رفته.',
    vision: 'تأیید + snapshot + بازگشت سریع',
    inhouse: 'ریسک بدون پشتیبان و تاریخچه',
    saas: 'اصلاً نمی‌تواند اجرا کند',
  },
]

const steps3: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی ووکامرس نصب و سرچ کنسول متصل می‌شود؛ زیرساخت فعلی دست‌نخورده می‌ماند.' },
  { title: 'تحلیل', text: 'صفحات محصول، دسته‌بندی و مقالات به تفکیک بازدید و ارزش فروش تحلیل می‌شوند.' },
  { title: 'اقدام', text: 'تغییرات با تأیید شما اجرا و اثرشان روی بازدید و فروش اندازه‌گیری می‌شود.' },
]

function onCta(): void {
  trackAudienceCta('ecommerce')
}
</script>

<template>
  <Head title="لندینگ اختصاصی فروشگاه‌های اینترنتی" />
  <MarketingLayout>
    <main>
      <AudiencePageHero
        badge="برای فروشگاه‌های اینترنتی ووکامرس"
        title-before="از ترافیک گوگل تا"
        gradient-word="فروش"
        description="Vision Prime صفحات پول‌ساز فروشگاه شما را از دادهٔ سرچ کنسول پیدا می‌کند، شکاف‌های CTR را به تغییرات کنترل‌شده تبدیل می‌کند و نتیجه را به زبان فروش گزارش می‌دهد — نه به زبان فنی."
        :stats="[
          { to: 35, suffix: '٪', label: 'افزایش بازدید صفحات درآمدزا' },
          { to: 2, suffix: '×', label: 'بازده محتوای بهینه‌شده' },
          { to: 100, suffix: '٪', label: 'تغییرات با تأیید شما' },
          { to: 48, suffix: '٪', label: 'کاهش زمان بهینه‌سازی' },
        ]"
        cta-label="درخواست دموی تخصصی فروشگاه"
        :cta-href="withUtm('/demo', 'landing_ecommerce')"
        @cta-click="onCta"
      />

      <AudiencePainPoints :pains="pains" />

      <AudienceWorkflow :steps="workflow" />

      <AudienceCasePreview
        title="نمونهٔ جریان کار: از صفحهٔ بدون بازدید تا صفحهٔ فروش."
        description="برای یک فروشگاه نمونه با ۲٬۴۰۰ محصول — اعداد نمایشی برای نشان دادن شکل خروجی هستند."
        narrative-title="صفحهٔ پول‌ساز، قبل از فصل پیدا می‌شود."
        narrative="فروشگاه‌ها با صدها محصول نمی‌توانند همه را دستی بررسی کنند. Vision Prime از دادهٔ سرچ کنسول صفحه‌هایی را بیرون می‌کشد که رتبهٔ خوبی دارند ولی کلیک نمی‌گیرند — همان جایی که بیشترین اثر روی فروش را دارد؛ با اولویت تجاری، نه با سلیقه."
        delta="+۳۸٪"
        :points="[
          { label: 'صفحات محصول با شکاف CTR', value: '۲۴', hint: 'رتبهٔ ۴–۱۰ با بازدید پایین' },
          { label: 'فرصت فصلی فعال', value: '۷', hint: 'محتوای تخفیف، قبل از فصل' },
          { label: 'افزایش بازدید درآمدزا', value: '+۳۸٪', hint: 'در سه ماه اول' },
        ]"
      />

      <AudienceWhySection
        eyebrow="WHY ECOMMERCE"
        title="سه دلیلی که فروشگاه‌ها با Vision Prime رشد می‌کنند."
        :items="why"
      />

      <AudienceCompetitiveMatrix :rows="matrixRows" />

      <AudienceFaq :faqs="faqs" />

      <AudienceHowItWorks :steps="steps3" />

      <AudienceCtaBanner
        title="فروشگاه‌تان را با داده جلو ببرید."
        description="در یک دموی ۴۵ دقیقه‌ای، صفحات پول‌ساز فروشگاه شما و اولین فرصت‌ها را با هم بررسی می‌کنیم."
        cta-label="درخواست دموی تخصصی فروشگاه"
        campaign="landing_ecommerce"
        @cta-click="onCta"
      />

      <RelatedAudiences :links="related" />
    </main>
  </MarketingLayout>
</template>
