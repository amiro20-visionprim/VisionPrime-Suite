<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { BookOpen, GraduationCap, ShieldCheck } from '@lucide/vue'

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
    title: 'فصل ثبت‌نام می‌رسد و محتوا آماده نیست',
    text: 'قبل از شروع ترم، صفحات دوره به‌روز نیستند؛ هنرجویان در گوگل دورهٔ شما را پیدا نمی‌کنند و سراغ رقیب می‌روند.',
    solution: 'Vision Prime با دادهٔ جستجو، فرصت‌های فصلی را ماه‌ها قبل شناسایی می‌کند و اولویت محتوایی را مشخص می‌کند.',
  },
  {
    title: 'دوره‌های خوب دارید ولی صفحات ضعیف',
    text: 'صفحات دورهٔ شما رتبه‌های ۴ تا ۱۰ دارند؛ هیچ‌کس ثبت‌نام نمی‌کند چون اطلاعات و متا کامل نیست.',
    solution: 'صفحات دوره بر اساس شکاف CTR و intent بهینه‌سازی می‌شوند — بدون نیاز به نویسندهٔ فنی.',
  },
  {
    title: 'فصل که تمام می‌شود، سایت فراموش می‌شود',
    text: 'بین ترم‌ها هیچ اتفاقی نمی‌افتد؛ محتوای آموزشی (مقالات، راهنماها) که دائم بازدید می‌آورد ساخته نشده است.',
    solution: 'فرصت‌های محتوای همیشگی (evergreen) از دادهٔ GSC پیدا می‌شوند تا سایت در تمام سال بازدید داشته باشد.',
  },
]

const workflow: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی وردپرس نصب و سرچ کنسول متصل می‌شود؛ بدون نیاز به تیم فنی.' },
  { title: 'تحلیل', text: 'دوره‌ها، صفحات فرود و مقالات به تفکیک بازدید و ثبت‌نام تحلیل می‌شوند.' },
  { title: 'اولویت‌بندی', text: 'فرصت‌های فصل ثبت‌نام و محتوای همیشگی رتبه‌بندی می‌شوند.' },
  { title: 'اجرا و گزارش', text: 'تغییرات با تأیید شما منتشر و اثرشان در گزارش ماهانه نشان داده می‌شود.' },
]

const faqs: Faq[] = [
  {
    q: 'آیا محتوای فصلی واقعاً اثر دارد؟',
    a: 'بله؛ جستجوی «کلاس X در شهر Y» در بازه‌های مشخصی از سال اوج می‌گیرد. Vision Prime همین بازه‌ها را از دادهٔ سرچ کنسول پیدا می‌کند تا محتوا قبل از اوج آماده باشد.',
  },
  {
    q: 'برای اتصال به تیم فنی نیاز دارم؟',
    a: 'خیر؛ پلاگین وردپرس نصب می‌شود و اتصال سرچ کنسول از پنل انجام می‌شود. تمام مراحل با راهنمایی ما پیش می‌رود.',
  },
  {
    q: 'نتیجه را چطور می‌بینم؟',
    a: 'گزارش ماهانه به زبان ساده: بازدید دوره‌ها، تکمیل فرم‌های ثبت‌نام و اثر هر تغییر — همان چیزی که می‌توانید به مدیریت آموزشگاه ارائه کنید.',
  },
  {
    q: 'فقط برای آموزشگاه‌های بزرگ مناسب است؟',
    a: 'خیر؛ از یک دورهٔ مشخص هم می‌توانید شروع کنید و فرصت‌های فصلی آن را ببینید. پلن پایه برای همین شروع طراحی شده است.',
  },
]

const related: AudienceLink[] = [
  {
    href: '/for-agencies',
    title: 'آژانس‌های SEO',
    description: 'برای تیم‌هایی که چند سایت آموزشی را همزمان مدیریت می‌کنند.',
  },
  {
    href: '/for-clinics',
    title: 'کلینیک‌ها و مراکز درمانی',
    description: 'برای مراکزی که جذب بیمار از جستجوی محلی برایشان حیاتی است.',
  },
  {
    href: '/pricing',
    title: 'قیمت‌گذاری',
    description: 'پلن‌ها را مقایسه کنید و پلن مناسب آموزشگاه را پیدا کنید.',
  },
]

const why: AudienceWhyItem[] = [
  {
    icon: GraduationCap,
    title: 'تمرکز روی فصل ثبت‌نام',
    text: 'محتوای درست، قبل از شروع ترم آماده است؛ وقتی هنرجو جستجو می‌کند، شما دیده می‌شوید.',
  },
  {
    icon: BookOpen,
    title: 'صفحات دورهٔ قوی',
    text: 'هر دوره صفحهٔ مشخص و بهینه‌شده دارد؛ بدون نیاز به کدنویس یا نویسندهٔ فنی.',
  },
  {
    icon: ShieldCheck,
    title: 'تغییر دقیق و امن',
    text: 'گردش‌کار تأیید و rollback؛ محتوای آموزشی بدون ریسک به‌روزرسانی می‌شود.',
  },
]

const matrixRows: AudienceMatrixRow[] = [
  {
    concern: 'فصل ثبت‌نام',
    scenario: 'هنرجو در اوج جستجو می‌کند و شما آماده نیستید.',
    vision: 'فرصت فصلی از داده، قبل از اوج',
    inhouse: 'واکنش دیرهنگام و دستی',
    saas: 'بدون بافت ثبت‌نام و ترم',
  },
  {
    concern: 'صفحات دوره',
    scenario: 'رتبه‌های ۴ تا ۱۰ بدون بازدید و ثبت‌نام.',
    vision: 'بهینه‌سازی صفحات دوره با اولویت داده',
    inhouse: 'کند و وابسته به یک نفر',
    saas: 'گزارش خام، بدون پیشنهاد اجرا',
  },
  {
    concern: 'بین ترم‌ها',
    scenario: 'سایت در تعطیلی فراموش می‌شود.',
    vision: 'محتوای همیشگی با بازدید دائمی',
    inhouse: 'بدون برنامهٔ محتوا',
    saas: 'بدون جهت استراتژیک',
  },
]

const steps3: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی وردپرس نصب و سرچ کنسول متصل می‌شود؛ بدون نیاز به تیم فنی.' },
  { title: 'تحلیل', text: 'دوره‌ها، صفحات فرود و مقالات به تفکیک بازدید و ثبت‌نام تحلیل می‌شوند.' },
  { title: 'اقدام', text: 'تغییرات با تأیید شما منتشر و اثرشان روی ثبت‌نام فصل بعد سنجیده می‌شود.' },
]

function onCta(): void {
  trackAudienceCta('education')
}
</script>

<template>
  <Head title="لندینگ اختصاصی مراکز آموزشی" />
  <MarketingLayout>
    <main>
      <AudiencePageHero
        badge="برای آموزشگاه‌ها و مراکز آموزشی"
        title-before="در فصل ثبت‌نام"
        gradient-word="دیده شوید"
        description="Vision Prime سایت آموزشگاه شما را با دادهٔ سرچ کنسول تحلیل می‌کند: هنرجویان چه دوره‌هایی را جستجو می‌کنند، کدام صفحات ثبت‌نام می‌آورند و محتوای فصلی کجا از دست می‌رود."
        :stats="[
          { to: 2, suffix: '×', label: 'بازدید صفحات دوره' },
          { to: 45, suffix: '٪', label: 'افزایش ثبت‌نام فصلی' },
          { to: 100, suffix: '٪', label: 'تغییرات با تأیید شما' },
          { to: 40, suffix: '٪', label: 'کاهش زمان آماده‌سازی محتوا' },
        ]"
        cta-label="درخواست دموی تخصصی آموزشگاه"
        :cta-href="withUtm('/demo', 'landing_education')"
        @cta-click="onCta"
      />

      <AudiencePainPoints :pains="pains" />

      <AudienceWorkflow :steps="workflow" />

      <AudienceCasePreview
        title="نمونهٔ جریان کار: از جستجوی دوره تا تکمیل فرم ثبت‌نام."
        description="برای یک آموزشگاه نمونه با ۲۴ دوره — اعداد نمایشی برای نشان دادن شکل خروجی هستند."
        narrative-title="محتوای فصل، قبل از شروع ترم آماده است."
        narrative="جستجوی «کلاس زبان در شهر» در بازه‌های مشخصی از سال اوج می‌گیرد. Vision Prime همان بازه‌ها را از دادهٔ سرچ کنسول پیدا می‌کند، صفحات دوره را اولویت‌بندی و به پیشنهاد تغییر مشخص تبدیل می‌کند — محتوا قبل از اوج آماده است، نه بعد از آن."
        delta="+۳۱٪"
        :points="[
          { label: 'صفحات دوره با شکاف CTR', value: '۱۸', hint: 'رتبهٔ ۴–۱۰ در جستجوی دوره' },
          { label: 'فرصت فصلی فعال', value: '۹', hint: 'پیش از شروع ترم بعدی' },
          { label: 'افزایش تکمیل فرم ثبت‌نام', value: '+۳۱٪', hint: 'در فصل گذشته' },
        ]"
      />

      <AudienceWhySection
        eyebrow="WHY EDUCATION"
        title="سه دلیلی که آموزشگاه‌ها با Vision Prime هنرجو جذب می‌کنند."
        :items="why"
      />

      <AudienceCompetitiveMatrix :rows="matrixRows" />

      <AudienceFaq :faqs="faqs" />

      <AudienceHowItWorks :steps="steps3" />

      <AudienceCtaBanner
        title="آموزشگاه‌تان را در فصل ثبت‌نام جلو بیندازید."
        description="در یک دموی ۴۵ دقیقه‌ای، جستجوهای هنرجویان و فرصت‌های فصل بعد را با هم بررسی می‌کنیم."
        cta-label="درخواست دموی تخصصی آموزشگاه"
        campaign="landing_education"
        @cta-click="onCta"
      />

      <RelatedAudiences :links="related" />
    </main>
  </MarketingLayout>
</template>
