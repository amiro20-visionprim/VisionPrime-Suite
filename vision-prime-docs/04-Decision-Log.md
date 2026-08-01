# Vision Prime — Decision Log

این فایل برای جلوگیری از تصمیم‌های حدسی و گم‌شدن مسیر است. هیچ تصمیم معماری/UX حساس بدون ثبت اینجا نهایی تلقی نمی‌شود.

| ID | تاریخ | تصمیم | وضعیت | دلیل | اثر/وابستگی | مالک |
|---|---|---|---|---|---|---|
| D-001 | 2026-07-25 | Modular Monolith با Laravel 12 | قطعی | پیچیدگی عملیاتی کمتر و مرزبندی کافی | کل backend | Product/Tech |
| D-002 | 2026-07-25 | فارسی و RTL-first؛ انگلیسی secondary | قطعی | بازار اولیه ایران | همه UIها | Product/Design |
| D-003 | 2026-07-25 | نمایش جلالی؛ ذخیره UTC/Gregorian | قطعی | تجربه محلی بدون آسیب به داده | UI/date utilities | Product/Tech |
| D-004 | 2026-07-25 | Automation در سطح Site و Policy-driven | قطعی | کنترل ریسک و نیاز مشتریان متفاوت | commands/review/plugin | Product/Security |
| D-005 | 2026-07-25 | L0 تا L4؛ Autopilot نامحدود ممنوع | قطعی | جلوگیری از تغییر پرریسک و حفظ اعتماد | automation UX/API | Product/Security |
| D-006 | 2026-07-25 | AI هیچ‌وقت مستقیم publish نمی‌کند | قطعی | ریسک برند، کیفیت و امنیت | AI Gateway | Product/Security |
| D-007 | 2026-07-25 | مدل استقرار Hybrid است: SaaS پیش‌فرض و Private deployment برای Enterprise | قطعی | تعادل سرعت تجاری‌سازی با نیاز مشتریان بزرگ به کنترل و ایزولیشن | tenancy, deployment, operations | Product/Tech |
| D-008 | 2026-07-25 | Commandهای R3 فقط برای Enterprise و با guardrailهای سخت‌گیرانه می‌توانند خودکار شوند | قطعی | پاسخ به نیاز اتوماسیون بالا بدون ایجاد ریسک عمومی | automation, command policy | Product/Security |
| D-009 | 2026-07-25 | Billing آنلاین در نسخه نهایی اولیه داخل Scope اجرایی نیست؛ entitlement و آماده‌سازی معماری لحاظ می‌شود | قطعی | تمرکز لانچ بر ارزش عملیاتی و فروش سازمانی/قراردادی | settings, plans, commercial ops | Product/Commercial |

## تصمیم‌های نیازمند پاسخ پیش از شروع توسعه مرتبط
| ID | سؤال | Deadline فاز | گزینه‌های اولیه |
|---|---|---|---|
| O-001 | مدل استقرار مشتری چیست؟ | Phase 0 | SaaS چندمستاجری / private deployment / self-hosted |
| O-002 | مدل قیمت‌گذاری و entitlement چیست؟ | Phase 3 | per site / per seat / per agency / usage |
| O-003 | export گزارش چگونه است؟ | Phase 11 | PDF / secure link / email / all |
| O-004 | اولویت providerهای AI چیست؟ | Phase 9 | OpenAI-compatible / local/self-hosted / multiple providers |
| O-005 | چه Commandهای R1 و R2 در نسخه تجاری مجازند؟ | Phase 10 | allowlist نهایی با تیم محصول/حقوقی |
