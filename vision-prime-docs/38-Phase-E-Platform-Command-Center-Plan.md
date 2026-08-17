# Vision Prime SUITE — فاز E: اتاق فرماندهی پلتفرم (Super Admin) — پلان تسک اتمی

> این سند مرجع **تسکهای اتمی** فاز E است (فلسفهٔ «اتاق فرماندهی استثنامحور» — سند ۳۷). هر Task در یک اجرای محدود Agent انجام شود؛ Agent نباید از scope همان Task عبور کند.
> قوانین همیشگی پروژه (D-020): بعد از هر تغییر مهم، مستندات قبلی (۳۷، ۳۵، ۳۱، ۰۴، ۰۹، ۰۶) و این سند باید همگام شوند.

## وضعیت فاز E

| زیرفاز | عنوان | وضعیت |
|---|---|---|
| E0 | زیرساخت دادهٔ مالی (plans/subscriptions/payments/invoices/platform_metrics) | ✅ انجام شد — ۲۰۲۶-۰۸-۱۷ |
| E1 | هستهٔ پلتفرم: لایوت + middleware + داشبورد فرماندهی + Triage + صف تصمیم + Daily Briefing | ✅ انجام شد — ۲۰۲۶-۰۸-۱۷ |
| E2 | مدیریت سازمانها: لیست/جزئیات/تعلیق/impersonation با audit | ✅ انجام شد — ۲۰۲۶-۰۸-۱۷ |
| E3 | مشتریان و اشتراکها: پلن/پرداخت دستی/تمدید/dunning | ✅ انجام شد — ۲۰۲۶-۰۸-۱۷ |
| E4 | رصد فنی + اعلان فعال: صف/scheduler/مصرف AI/سقف/Emergency Stop سراسری + تلگرام | ✅ انجام شد — ۲۰۲۶-۰۸-۱۷ |
| E5 | گزارش و بکاپ: درآمد/رشد/سلامت + Export + بکاپ DB + سوییچر org | ✅ انجام شد — ۲۰۲۶-۰۸-۱۷ |

**اصل راهنما:** پنل پلتفرم یعنی «سیستم خودش اجرا میکند، خودش خبر میدهد، فقط تصمیمهای واقعی به مالک میرسد» — نه داشبوردی که هر روز باید چک شود.

---

## E0 — زیرساخت دادهٔ مالی

### E0-01 — جداول مالی (migration)
**وضعیت:** ✅ انجام شد
**هدف:** پایهٔ دادهٔ اشتراک/پرداخت/فاکتور و متریک روزانه.
- [x] `plans`: id, key (slug یکتا), name, description, price_monthly, price_yearly (decimal), currency (پیشفرض `IRT`), limits (json: max_sites/max_clients/max_ai_tokens_monthly/max_profiles), features (json: feature flags), is_active, sort, timestamps, softDeletes.
- [x] `subscriptions`: id, organization_id (FK), plan_id (FK), status (trialing|active|past_due|canceled|suspended), trial_ends_at, starts_at, current_period_end, auto_renew (bool), cancel_at_period_end (bool), timestamps, softDeletes + index (organization_id, status).
- [x] `payments`: id, organization_id, subscription_id (nullable FK), amount, currency, method (zarinpal|idpay|manual|bank), status (pending|paid|failed|refunded), reference (string یکتا), gateway_transaction_id (nullable), paid_at, timestamps + index (organization_id, status, paid_at).
- [x] `invoices`: id, organization_id, subscription_id (FK), payment_id (nullable FK), number (string یکتا), amount, tax, total, status (draft|issued|paid|overdue|canceled), issued_at, due_at, timestamps + index (organization_id, status).
- [x] `platform_metrics`: date (unique), orgs_active, orgs_trialing, clients_total, sites_total, sites_connected, tokens_in, tokens_out, ai_cost, commands_executed, commands_rolled_back, reviews_pending, timestamps.
**قبولی:** migration سبز؛ فهرست جداول در DB واقعی و تست دیده شود؛ soft delete در plans/subscriptions/payments/invoices. ✅ تأیید شد — migration روی DB واقعی اجرا و جداول ساخته شدند.

### E0-02 — مدلها و سرویسهای دامنهٔ مالی
**وضعیت:** ✅ انجام شد
**هدف:** لایهٔ دامنه با منطق وضعیت اشتراک (نه منطق در کنترلر).
- [x] مدلهای Eloquent: `Plan`, `Subscription`, `Payment`, `Invoice` در `app/Domains/Platform/Models` (یا دامنهٔ موجود مناسب) با relation به Organization.
- [x] `Plan::isActive()`، `Plan::limits()` (decode json با پیشفرض).
- [x] `Subscription::statusLabel()` (فارسی)، `Subscription::isExpired()`، `Subscription::remainingDays()`.
- [x] سرویس `SubscriptionService` (در `app/Domains/Platform/Services`): `activate(org, plan, trialDays)`, `renew()`, `cancel(atPeriodEnd)`, `suspend()`, `reactivate()` — همه با آپدیت status و ثبت audit (RecordAuditLog با action `platform.subscription.*`).
- [x] سرویس `PaymentService`: `recordManual(org, amount, subscription, reference, method, invoice)`, `markPaid(payment)`, `markFailed(payment)`, `refund(payment)`.
- [x] سرویس `InvoiceService`: `generateForSubscription(subscription)` (با مالیات ۹٪)، `markPaid(invoice, payment)`، `overdueCheck()` (اسکن invoices با due_at گذشته و status=issued → overdue).
**قبولی:** منطق وضعیت در سرویسها (نه کنترلر)؛ هر اکشن در audit_logs ثبت شود؛ تستهای unit برای انتقال وضعیتها.

### E0-03 — seeder پلنهای نمونه + دادهٔ پایه
**وضعیت:** ✅ انجام شد
**هدف:** پلنهای واقعی Tier (هماهنگ با D-010) + اشتراک/پرداخت نمونه برای demo.
- [x] Seeder `PlanSeeder` (یا داخل RoleAccountsSeeder): پلنهای «استارتاپ / رشد / سازمانی» با قیمت ماهانه/سالانه، limits (max_sites, max_clients, max_ai_tokens_monthly), features.
- [x] تخصیص پلن «رشد» به سازمان demo با subscription فعال + یک پرداخت نمونه (manual/bank, paid) + یک invoice paid.
- [x] یک `platform_metrics` نمونه برای ۱۴ روز گذشته (برای چارت داشبورد E1). تأیید واقعی: plans=3, subs=1, payments=1, invoices=1, metrics=14.
**قبولی:** بعد از seed، سازمان demo اشتراک فعال + پرداخت + فاکتور داشته باشد؛ صفحهٔ Integrations/AI بیکار نشود.

### E0-04 — تستها
**وضعیت:** ✅ انجام شد — `tests/Feature/Platform/BillingTest.php` (۱۰ تست / ۴۰ assert)
**هدف:** پوشش migration، سرویسها و انتقال وضعیت.
- [x] Feature test: ساخت سازمان + activate اشتراک → status=trialing؛ تمدید → current_period_end جلو میرود؛ cancel(atPeriodEnd) → cancel_at_period_end=true و status همچنان active؛ suspend/reactivate.
- [x] Feature test: ثبت پرداخت دستی → payment status=paid با audit؛ markFailed؛ refund؛ صدور فاکتور با مالیات ۹٪ و شمارهٔ یکتا؛ overdueCheck.
- [x] Unit test: `Plan::limits()`/`features()` با json؛ `Subscription::remainingDays()`.
**قبولی:** همهٔ تستها سبز؛ پوشش مسیرهای خوشحال و خطا (سازمان بدون پلن → استثنا).

### E0-05 — گیتها + مستندات (D-020)
**وضعیت:** ✅ انجام شد — 335 تست / 1816 assert · pint ✓
**هدف:** کیفیت و همگامسازی.
- [x] `pint` + `typecheck` + `lint` + `build` + کل تستهای PHP سبز.
- [x] آپدیت مستندات: ۳۸ (این سند، چکباکسهای E0)، ۳۱ (لاگ پیشرفت)، ۰۴ (D-028)، ۰۹ (ERD — جداول جدید).
**قبولی:** همهٔ گیتها سبز؛ مستندات همگام.

---

## E1 — هستهٔ پلتفرم (مغز)

### E1-01 — لایوت و مسیر پلتفرم
**وضعیت:** ✅ انجام شد
**هدف:** جداسازی کامل پنل پلتفرم از پنل آژانس.
- [x] `PlatformLayout.vue` (resources/js/platform/layouts) — شبیه AppLayout ولی با هویت «پلتفرم» (بنر/آیکون متفاوت، دارکمود، دستیار پشتیبانی مشترک).
- [x] middleware `PlatformOnly` (فقط نقش super-admin + session معتبر + audit ورود)؛ ثبت در Kernel + اعمال روی گروه `/platform/*`.
- [x] `PlatformNavigation.vue` — ناوبری: داشبورد / سازمانها / مشتریان / اشتراکها / پرداختها / رصد فنی / گزارشها / تنظیمات پلتفرم.
- [x] لینک ورود به پلتفرم در هدر AppLayout (فقط برای super-admin).
**قبولی:** غیر super-admin به `/platform/*` → 403؛ لایوت با دارکمود و دیزاینسیستم هماهنگ.

### E1-02 — داشبورد فرماندهی (KPI + چارت + رویدادها)
**وضعیت:** ✅ انجام شد
**هدف:** نمای یکپارچهٔ کل اکوسیستم.
- [x] `PlatformDashboardController@index` — داده: KPI (orgs_active, orgs_trialing, clients_total, sites_connected, revenue_month, tokens_month, reviews_pending) از platform_metrics + کوئری زنده؛ روند ۳۰ روز (VAreaChart/VBarChart موجود)؛ آخرین رویدادها (audit/commands).
- [x] `Platform/Dashboard.vue` — کارتهای KPI متحرک (VStatCard) + دو چارت + فید آخرین رویدادها + Quick Actions.
- [x] خلاصهٔ «امروز چه خبر است» (از Triage E1-03) — یک باکس برجسته در بالای داشبورد.
**قبولی:** داشبورد با دادهٔ واقعی رندر شود؛ KPI و چارت و رویدادها یکجا.

### E1-03 — Triage Engine + صف تصمیم (قلب هوشمند)
**وضعیت:** ✅ انجام شد
**هدف:** طبقهبندی رویدادها به عادی/استثنا/تصمیم و صف تصمیم واحد.
- [x] جدول `platform_events` (migration): id, org_id (nullable), type (subscription.expiring|payment.failed|ai.cost_spike|site.disconnected|job.failure|review.awaiting|command.awaiting), severity (info|warning|critical), payload (json), triage (normal|exception|decision), seen_at, resolved_at, timestamps + index (triage, seen_at).
- [x] سرویس `TriageEngine` (app/Domains/Platform/Services): `classify(event)` → normal/exception/decision؛ `record(...)`؛ `pendingDecisions()`؛ `resolve(id, actor)`.
- [x] Job `CollectPlatformEvents` (روزانه): اسکن وضعیتها (اشتراکهای نزدیک انقضا، پرداختهای failed، مصرف AI نزدیک سقف، سایتهای قطع، jobs failed) و ثبت رویداد در platform_events.
- [x] صف تصمیم در UI: `Platform/Decisions.vue` — فقط رویدادهای decision با دکمههای اکشن (تأیید/رد/مشاهده)؛ resolve با audit.
**قبولی:** رویدادهای واقعی (از data واقعی) طبقهبندی و در صف تصمیم دیده شوند؛ resolve در audit ثبت شود.

### E1-04 — Daily Briefing (گزارش روزانهٔ هوشمند)
**وضعیت:** ✅ انجام شد
**هدف:** مالک بدون لاگین، خلاصهٔ روز را بگیرد.
- [x] Job `SendDailyBriefing` (صبح ۰۷:۳۰): تجمیع خلاصه از platform_metrics + Triage (تعداد تصمیمها/استثناها) + «۳ چیزی که امروز فقط تو میتوانی» (از صف تصمیم، prioritization ساده).
- [x] Notification `DailyBriefing` (database + mail؛ تلگرام در E4) — متن فارسی روون، لینک مستقیم به هر مورد.
- [x] تست: ساخت brief با دادهٔ نمونه؛ لینکها و شمارندهها درست.
**قبولی:** اجرای دستی job → نوتیفیکیشن برای super-admin ساخته شود؛ متن شامل KPI + تصمیمهای در انتظار.

---

## E2 — مدیریت سازمانها

### E2-01 — لیست و جزئیات سازمان
**وضعیت:** ✅ انجام شد
- [x] `Platform/Organizations.vue` — جدول (نام، status، پلن، تعداد کلاینت/سایت، آخرین فعالیت، درآمد) + فیلتر/مرتبسازی/صفحهبندی + جستجو.
- [x] `Platform/Organizations/Show.vue` — جزئیات: اعضا و نقشها، کلاینتها، سایتها و وضعیت اتصال، کلیدهای AI (فقط masked + has_key)، مصرف توکن ماه، اشتراک/پرداختها، audit لاگ همان org.
- [x] اکشنها: suspend/activate (با دلیل الزامی + audit)، حذف نرمافزاری (soft) با تأیید دوتایی.
**قبولی:** drill-down کامل org؛ هیچ کلید API کامل نمایش داده نشود؛ اکشنها در audit ثبت شوند.

### E2-02 — Impersonation با audit کامل
**وضعیت:** ✅ انجام شد
**هدف:** ورود بهجای کاربر برای پشتیبانی، با ردپای کامل.
- [x] روت `POST /platform/orgs/{org}/impersonate` + `POST /platform/impersonation/stop` (فقط super-admin).
- [x] سشن impersonation (کلید مجزا + flag) + بنر قرمز ثابت در هر دو پنل («در حال مشاهده بهجای X — خروج»).
- [x] ثبت audit در شروع/پایان impersonation (actor، target، request_id).
- [x] غیرفعالکردن اکشنهای حساس هنگام impersonation (تغییر پلن، پرداخت، حذف) — فقط مشاهده.
**قبولی:** impersonation با audit کامل کار کند؛ هیچ اکشن حساسی در حالت impersonate فعال نباشد.

---

## E3 — مشتریان و اشتراکها (مالی عملیاتی)

### E3-01 — مدیریت پلن و اشتراک
**وضعیت:** ✅ انجام شد
- [x] `Platform/Plans.vue` — CRUD پلنها (نام، قیمت ماهانه/سالانه، limits، features، فعال/بایگانی).
- [x] `Platform/Subscriptions.vue` — لیست اشتراکها (org، پلن، status، دوره، auto_renew) + اکشنها: ثبت دستی، تغییر پلن، تمدید، cancel(atPeriodEnd)، reactivate، suspend.
- [x] همهٔ اکشنها از `SubscriptionService` (E0-02) — نه منطق در UI.
**قبولی:** مدیریت کامل اشتراک با سرویس یکتا؛ اکشنها audit شوند.

### E3-02 — پرداختها و فاکتورها
**وضعیت:** ✅ انجام شد
- [x] `Platform/Payments.vue` — ثبت پرداخت دستی (org، مبلغ، method، reference)، لیست با فیلتر وضعیت، markPaid/markFailed/refund.
- [x] `Platform/Invoices.vue` — لیست فاکتورها، صادرکردن دستی، شمارهٔ یکتا، markPaid، ارسال یادآوری (email)، Export CSV.
- [x] Job `DunningJob` (روزانه): invoices overdue → وضعیت + اعلان به org (database+mail)؛ بعد از grace period (مثلاً ۵ روز) → subscription suspend.
**قبولی:** کل چرخهٔ پرداخت/فاکتور/dunning کار کند؛ همه با audit.

### E3-03 — درگاه پرداخت (زرینپال) — اختیاری در این فاز
**وضعیت:** ✅ (stub — درگاه واقعی فاز بعدی)
- [x] abstraction `PaymentGateway` (contract) + درایور `ManualGateway` + stub `ZarinpalGateway` (بدون کلید واقعی).
- [x] webhook handler با idempotency (reference یکتا) و امضای درگاه.
**قبولی:** stub بدون کلید کار کند؛ webhook idempotent تست شود. (درگاه واقعی: فاز بعدی.)

---

## E4 — رصد فنی + اعلان فعال

### E4-01 — سلامت صف و scheduler
**وضعیت:** ✅ انجام شد
- [x] ویجت/صفحهٔ `Platform/Operations.vue` — وضعیت صف (pending/failed jobs)، آخرین اجرای هر job زمانبندیشده (gsc:import, LearningLoop, RollbackMonitor, ReleaseScheduledCommands, RemindScheduledPublishes, ProcessQueuedCommands, CollectPlatformEvents, SendDailyBriefing).
- [x] جدول/منبع «آخرین اجرا» (از audit_logs با action مخصوص یا جدول run_logs ساده).
**قبولی:** مالک وضعیت صف و scheduler را یکجا ببیند؛ jobهای failed مشخص باشند.

### E4-02 — مصرف AI سراسری + سقف
**وضعیت:** ✅ انجام شد
- [x] مجموع توکن/هزینه per org این ماه (از ai_generations.usage) — ویجت در Operations + Drill-down.
- [x] سقف ماهانه per-org (از plan.limits.max_ai_tokens_monthly) + هشدار نزدیک سقف (platform_events → exception).
- [ ] (اختیاری) بلوککردن تولید جدید هنگام عبور از سقف — با پیام واضح.
**قبولی:** مصرف AI هر org با سقفش نمایش داده شود؛ هشدار spike ثبت شود.

### E4-03 — Emergency Stop سراسری + کانال تلگرام
**وضعیت:** ✅ انجام شد
- [x] اکشن `PlatformEmergencyStop` (بالای EmergencyStop per-site): توقف همهٔ سایتهای یک org یا همهٔ orgها + ثبت audit + پیام تأیید دوتایی در UI.
- [x] کانال تلگرام: `TelegramChannel` برای Notifications (config: bot token + chat id از env؛ بدون توکن → fallback به database فقط).
- [x] اتصال استثناها/تصمیمهای Triage به تلگرام (اختیاری toggle در تنظیمات پلتفرم).
**قبولی:** Emergency Stop سراسری کار کند؛ تلگرام بدون توکن crash نکند (fallback).

---

## E5 — گزارش و بکاپ

### E5-01 — گزارشهای پلتفرم
**وضعیت:** ✅ انجام شد
- [x] `Platform/Reports.vue` — درآمد ماهانه/سالانه (از payments)، رشد سازمانها (new orgs per week)، سلامت (سایتهای متصل، نرخ موفقیت اتوماسیون)، Export CSV.
- [x] گزارش هفتگی خودکار (email به super-admin) — خلاصهٔ KPI + رویدادهای مهم.
**قبولی:** گزارشها با دادهٔ واقعی؛ Export کار کند؛ ایمیل هفتگی ارسال شود.

### E5-02 — بکاپ DB + سوییچر org
**وضعیت:** ✅ انجام شد
- [x] command `platform:backup-db` (dump sqlite/پایگاه جاری به فایل timestamped در storage/backups) + schedule روزانه + نگهداری N نسخهٔ آخر.
- [x] سوییچر org برای کاربران چند-سازمانی (dropdown در هدر هر دو پنل — فقط orgهای active عضویت).
**قبولی:** بکاپ دورهای ساخته شود؛ سوییچر org بین عضویتهای فعال جابجا کند.

---

## یادداشت پایانی

- همهٔ صفحات پلتفرم با کامپوننتهای فاز A–D ساخته میشوند (VCard, VStatCard, VBadge, VButton, VInput, VSelect, VPageHeader, VIcon, چارتها، دارکمود، VSupportAssistant).
- زبان UI: فنی ولی روون؛ هر عدد مهم با hint (💡).
- امنیت: MFA برای super-admin **پیاده‌سازی شد (فاز F-04)** + rate-limit روی روت‌های پلتفرم + همهٔ اکشن‌ها در audit_logs.
- تست ایزوله‌سازی داده (سازمان A هرگز سازمان B را نمی‌بیند) — در هر فاز که API پلتفرم باز می‌شود.

---

## ادامه در فاز F — سند ۳۹

فاز E با E3/E5 بسته شد و **فاز F (سند ۳۹) اجرا شد:** درگاه‌های پرداخت چندگانه (زرین‌پال + آقای پرداخت + دستی — F-01)، پنل پیامکی کاوه‌نگار + sms_logs (F-02)، خلاصهٔ هوشمند Triage با fallback آفلاین (F-03) و MFA سوپرادمین با TOTP RFC 6238 (F-04). 354 تست / 1893 assert سبز.
