# Vision Prime SUITE — Information Architecture & Navigation

## 1) تجربه‌های اصلی محصول
ویژن پرایم سوئیت سه تجربه مجزا با یک Design System دارد:

1. **Marketing** — تبدیل بازدیدکننده به Lead/Demo
2. **Operations App** — فضای کاری آژانس و تیم SEO
3. **Client Portal** — نمایش خروجی، تصمیم‌ها و گزارش برای کارفرما

کاربر نباید بدون قصد مشخص میان این سه فضا جابه‌جا شود.

---

## 2) Marketing Sitemap
```text
/
/product
/features
/pricing
/security
/about
/faq
/contact
/demo
/solutions/agencies
/solutions/clinics
/solutions/local-services
/solutions/medical-seo
/solutions/content-teams
/landing/seo-growth-audit
/landing/wordpress-seo-intelligence
/landing/client-reporting-system
/login
```

### Navigation اصلی Marketing
- محصول
- قابلیت‌ها
- راهکارها
- امنیت
- قیمت‌گذاری
- منابع/سؤالات متداول
- CTA اصلی: «درخواست دموی اختصاصی»

---

## 3) Operations App Sitemap
```text
/app/dashboard
/app/clients
/app/clients/{client}
/app/projects
/app/projects/{project}
/app/sites
/app/sites/{site}
/app/sites/{site}/connector
/app/sites/{site}/sync
/app/sites/{site}/automation
/app/url-profiles
/app/url-profiles/{urlProfile}
/app/gsc
/app/gsc/properties
/app/gsc/queries
/app/gsc/pages
/app/opportunities
/app/opportunities/{opportunity}
/app/money-pages
/app/money-pages/{moneyPageAudit}
/app/conversion-risks
/app/conversion-risks/{risk}
/app/recommendations
/app/ai
/app/ai/templates
/app/ai/generations
/app/ai-drafts/article/create  ← تولید مقاله (اختیار سایت/پروفایل/زیرنوع)
/app/ai-drafts/product/create  ← تولید محصول (اختیار سایت/پروفایل/زیرنوع)
/app/reviews
/app/reviews/{reviewItem}
/app/commands
/app/commands/pending
/app/commands/{command}
/app/rollbacks
/app/rollbacks/{rollback}
/app/reports
/app/reports/{report}
/app/activity
/app/settings/organization
/app/settings/members
/app/settings/roles
/app/settings/integrations
/app/settings/billing
/app/settings/audit-log
```

### Sidebar پیشنهادی App
**Overview**
- داشبورد

**Workspace**
- مشتریان
- پروژه‌ها
- سایت‌ها

**Intelligence**
- فرصت‌های رشد
- صفحات درآمدزا
- ریسک‌های تبدیل
- URLها و محتوا

**Data Sources**
- سرچ کنسول
- اتصال وردپرس

**Workflows**
- پیشنهادها
- بررسی و تأییدها
- تولید مقاله (ArticleDraft)
- تولید محصول (ProductDraft)
- دستیار هوشمند
- تغییرات اجرایی

**Outcomes**
- گزارش‌ها
- فعالیت‌ها

**Settings**
- تنظیمات سازمان
- اعضا و دسترسی‌ها
- یکپارچه‌سازی‌ها
- صورتحساب
- گزارش ممیزی

> ترتیب Sidebar عمداً با «ارزش و کار روزانه» شروع می‌شود، نه با منبع داده یا تنظیمات فنی.

---

## 4) Client Portal Sitemap
```text
/client/dashboard          → خانه (سایت من در یک نگاه) — شامل خلاصهٔ فعالیت‌ها
/client/growth             → رشد من (چقدر دیده می‌شوم)
/client/opportunities      → اولویت‌ها (کجا بهتر شویم)
/client/decisions          → تأییدهای من (منتظر تصمیم شما)
/client/reports            → گزارش‌ها
/client/site-health        → وضعیت سایت (سلامت فنی به زبان ساده)
```

### Navigation Client Portal (بازطراحی فاز B — ۶ بخش با آیکون)
1. **خانه** — icon `chart-line` — «سایت من در یک نگاه»
2. **رشد من** — icon `trend-up` — «چقدر دیده می‌شوم»
3. **اولویت‌ها** — icon `lightbulb` — «کجا بهتر شویم»
4. **تأییدهای من** — icon `user-check` — «منتظر تصمیم شما»
5. **گزارش‌ها** — icon `file` — «گزارش‌های دوره‌ای»
6. **وضعیت سایت** — icon `gauge` — «سلامت فنی سایت»

**تصمیم تأییدشده (D-022):** صفحهٔ «فعالیت‌ها» (`/client/activity`) در خانه ادغام شد و از ناوبری حذف شد — منوی مشتری دقیقاً ۶ بخش است. هر بخش یک زیرنویس کوتاه غیرتخصصی دارد (hint) تا کاربر غیرفنی گم نشود.

**قاعده:** Client Portal هیچ‌وقت pageهایی مانند Connector Logs، Command payload، GSC raw queries، API settings یا AI provider settings ندارد.

---

## 5) Context Switcher
در App، Context Switcher بالای Sidebar قرار می‌گیرد:

`Organization → Client → Project → Site`

- کاربر می‌تواند در سطح Organization داشبورد کلی ببیند.
- Intelligence و عملیات معمولاً Site-scoped هستند.
- اگر کاربر وارد صفحه‌ای شود که Site ندارد، CTA روشن برای انتخاب Site دیده می‌شود.
- Context انتخاب‌شده در URL/query یا state پایدار نگه‌داری می‌شود؛ نباید با refresh گم شود.

## 6) اصول Navigation و URL
- URLها انگلیسی، کوتاه و پایدار؛ برچسب UI فارسی.
- Breadcrumb در RTL به‌صورت semantic درست نمایش داده می‌شود، اما URL و نام دامنه LTR باقی می‌مانند.
- هر صفحه عملیاتی عنوان، context، last updated و primary action مشخص دارد.
- یک Primary CTA بیشتر در هر viewport اصلی نباشد.
