<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { Layers, ShieldCheck, Users } from '@lucide/vue'

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
    title: 'گزارش دستی برای هر مشتری',
    text: 'هر ماه ساعتها صرف جمع‌کردن اسکرین‌شات از ابزارهای مختلف و توضیح دادن «چرا این کار را کردیم» میشود؛ نتیجه باز هم برای مشتری مبهم است.',
    solution: 'گزارش خودکار با impact timeline: هر اقدام، اثرش و گام بعدی — به نام برند خودتان، نه یک لوگوی خارجی.',
  },
  {
    title: 'فرصت‌ها در ۱۰ ابزار پراکندهاند',
    text: 'سرچ کنسول، آنالیتیکس، ردیاب رتبه، ممیزی تکنیکال… هر کدام یک حقیقت متفاوت می‌گویند و اولویت‌بندی عملاً «حدس تیم» است.',
    solution: 'یک پنل واحد که فرصت‌ها را با داده، intent و ارزش کسب‌وکاری رتبه‌بندی می‌کند؛ اولویت از داده می‌آید، نه سلیقه.',
  },
  {
    title: 'کنترل تغییرات پرریسک',
    text: 'اجرای تغییر روی سایت مشتری بدون تأیید صریح او، ریسک اعتماد و مسئولیت دارد؛ پیگیری «چه کسی چه چیزی را تغییر داد» سخت است.',
    solution: 'گردش‌کار تأیید مشتری از پرتال + audit trail کامل + rollback؛ هر تغییر قبل از اجرا، سیاست سایت را پاس می‌کند.',
  },
]

const workflow: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین سوئیت روی وردپرس مشتری نصب و با HMAC امضا می‌شود؛ سرچ کنسول هم متصل می‌شود.' },
  { title: 'تحلیل', text: 'دادهٔ واقعی به فرصت‌ها، صفحات پول‌ساز و ریسک‌های تبدیل تبدیل می‌شود.' },
  { title: 'تأیید', text: 'مشتری از پرتال خودش تصمیم می‌گیرد؛ هیچ تغییری بدون تأیید او اجرا نمی‌شود.' },
  { title: 'اجرا و گزارش', text: 'تغییرات اجرا، اثرشان اندازه‌گیری و در گزارش ماهانه به نام آژانس منتشر می‌شود.' },
]

const faqs: Faq[] = [
  {
    q: 'آیا سوئیت جایگزین تیم SEO ماست؟',
    a: 'نه؛ لایهٔ عملیاتی تیم شماست. تیم‌تان روی تحلیل و استراتژی تمرکز می‌کند، در حالی که جمع‌آوری داده، اولویت‌بندی، پیش‌نویس و گزارش‌دهی خودکار می‌شود. آژانس‌هایی که چند مشتری دارند، با همین یک ابزار مقیاس می‌گیرند.',
  },
  {
    q: 'گزارش مشتری به نام چه کسی منتشر می‌شود؟',
    a: 'کاملاً به نام برند آژانس. در پلن آژانس، پرتال و گزارش‌ها وایتدلیبل هستند — مشتری شما هیچ‌جا نام سوئیت را نمی‌بیند مگر اینکه خودتان بخواهید.',
  },
  {
    q: 'آیا مشتری می‌تواند تغییرات را ببیند و تأیید کند؟',
    a: 'بله. هر تغییر پیشنهادی با توضیح، ریسک و اثر به پرتال مشتری می‌رود؛ او تأیید یا رد می‌کند و همه‌چیز در audit trail ثبت می‌شود. این دقیقاً همان شفافیتی است که اعتماد بلندمدت می‌سازد.',
  },
  {
    q: 'چند سایت و مشتری می‌توانیم مدیریت کنیم؟',
    a: 'پلن آژانس تا ۱۵ سایت و پرتال نامحدود مشتری را پشتیبانی می‌کند؛ برای عملیات بزرگ‌تر پلن سازمانی با استقرار اختصاصی داریم.',
  },
]

const related: AudienceLink[] = [
  {
    href: '/for-ecommerce',
    title: 'فروشگاه‌های اینترنتی',
    description: 'برای تیم‌هایی که هر بازدید اضافه را به فروش تبدیل می‌کنند.',
  },
  {
    href: '/for-clinics',
    title: 'کلینیک‌ها و مراکز درمانی',
    description: 'برای مراکزی که جذب بیمار از جستجوی محلی برایشان حیاتی است.',
  },
  {
    href: '/pricing',
    title: 'قیمت‌گذاری',
    description: 'پلن‌ها را مقایسه کنید و پلن مناسب عملیات خود را پیدا کنید.',
  },
]

const why: AudienceWhyItem[] = [
  {
    icon: Layers,
    title: 'یک پنل برای همهٔ مشتریان',
    text: 'همهٔ سایت‌ها، فرصت‌ها و تأییدها در یک جا؛ بدون جابه‌جایی بین ابزارها.',
  },
  {
    icon: Users,
    title: 'مشتری در جریان است',
    text: 'مشتری می‌بیند چه پیشنهادی داده شده، چرا و چه نتیجه‌ای داشته است.',
  },
  {
    icon: ShieldCheck,
    title: 'مسئولیت‌پذیری کامل',
    text: 'هر تغییر ردپای audit دارد و قابل بازگشت است؛ ریسک اعتبار شما به حداقل می‌رسد.',
  },
]

const matrixRows: AudienceMatrixRow[] = [
  {
    concern: 'گزارش ماهانهٔ هر مشتری',
    scenario: '۵ مشتری، ۵ گزارش، یک شب کاری.',
    vision: 'خودکار، وایتدلیبل، با impact timeline',
    inhouse: 'ساعت‌ها کار دستی و جمع‌کردن اسکرین‌شات',
    saas: 'گزارش خام به نام ابزار، بدون روایت',
  },
  {
    concern: 'تغییر روی سایت مشتری',
    scenario: 'مشتری می‌ترسد چیزی خراب شود.',
    vision: 'تأیید مستقیم مشتری + rollback + audit',
    inhouse: 'بدون گردش‌کار و تاریخچهٔ قابل ارائه',
    saas: 'فقط پیشنهاد؛ قابلیت اجرا ندارد',
  },
  {
    concern: 'اولویت از کجا می‌آید',
    scenario: '۱۰ ابزار، ۱۰ حقیقت متفاوت.',
    vision: 'دادهٔ GSC رتبه‌بندی‌شده با ارزش تجاری',
    inhouse: 'حدس و تجربهٔ فردی',
    saas: 'دادهٔ خام، تحلیل دستی',
  },
]

const steps3: AudienceStep[] = [
  { title: 'اتصال', text: 'پلاگین روی وردپرس مشتری نصب و سرچ کنسول متصل می‌شود؛ زیرساخت فعلی دست‌نخورده می‌ماند.' },
  { title: 'تحلیل', text: 'دادهٔ واقعی به فرصت‌های رتبه‌بندی‌شده با اولویت تجاری تبدیل می‌شود.' },
  { title: 'اقدام', text: 'با تأیید مشتری، تغییرات اجرا و گزارش ماهانه به نام برند شما منتشر می‌شود.' },
]

function onCta(): void {
  trackAudienceCta('agencies')
}
</script>

<template>
  <Head title="لندینگ اختصاصی آژانس‌ها" />
  <MarketingLayout>
    <main>
      <AudiencePageHero
        badge="برای آژانس‌های SEO و دیجیتال مارکتینگ"
        title-before="عملیات چندمشتری را"
        gradient-word="مقیاس‌پذیر"
        description="سوئیت دادهٔ همهٔ مشتریان شما را به فرصت‌های رتبه‌بندی‌شده، تغییرات کنترل‌شده و گزارش‌های وایتدلیبل تبدیل می‌کند — بدون وابستگی به ابزارهای پراکنده و گزارش‌های دستی."
        :stats="[
          { to: 15, suffix: '+', label: 'سایت در پلن آژانس' },
          { to: 40, suffix: '٪', label: 'کاهش زمان گزارش‌دهی' },
          { to: 100, suffix: '٪', label: 'تغییرات با تأیید مشتری' },
          { to: 24, suffix: '/۷', label: 'پشتیبانی حرفه‌ای' },
        ]"
        cta-label="درخواست دموی تخصصی آژانس"
        :cta-href="withUtm('/demo', 'landing_agencies')"
        @cta-click="onCta"
      />

      <AudiencePainPoints :pains="pains" />

      <AudienceWorkflow :steps="workflow" />

      <AudienceCasePreview
        title="نمونهٔ جریان کار: از داده تا گزارش وایتدلیبل."
        description="برای یک آژانس نمونه با ۵ مشتری — اعداد نمایشی برای نشان دادن شکل خروجی هستند."
        narrative-title="گزارش ماهانه، دیگر یک شب کاری نیست."
        narrative="برای آژانسی که ۵ مشتری دارد، گزارش قبلاً یعنی پنج شب جمع‌کردن اسکرین‌شات و توضیح «چرا این کار را کردیم». حالا همان دادهٔ سرچ کنسول بدون هیچ دخالت دستی به گزارش وایتدلیبل با impact timeline تبدیل می‌شود — و آژانس وقتش را صرف استراتژی می‌کند، نه اسکرین‌شات."
        :points="[
          { label: 'فرصت‌های اولویت‌دار', value: '۱۲', hint: 'شناسایی‌شده از دادهٔ ۵ مشتری' },
          { label: 'تغییرات تأییدشده', value: '۸', hint: 'با تأیید مستقیم مشتریان از پرتال' },
          { label: 'گزارش‌های منتشرشده', value: '۵', hint: 'به نام برند آژانس، آمادهٔ ارائه' },
        ]"
      />

      <AudienceWhySection
        eyebrow="WHY AGENCIES"
        title="سه دلیلی که آژانس‌ها با سوئیت مقیاس می‌گیرند."
        :items="why"
      />

      <AudienceCompetitiveMatrix :rows="matrixRows" />

      <AudienceFaq :faqs="faqs" />

      <AudienceHowItWorks :steps="steps3" />

      <AudienceCtaBanner
        title="عملیات آژانستان را با داده، کنترل و گزارش‌پذیری جلو ببرید."
        description="در یک دموی ۴۵ دقیقه‌ای، جریان کاری آژانس و سایت‌های مشتریانتان را با هم بررسی می‌کنیم."
        cta-label="درخواست دموی تخصصی آژانس"
        campaign="landing_agencies"
        @cta-click="onCta"
      />

      <RelatedAudiences :links="related" />
    </main>
  </MarketingLayout>
</template>
