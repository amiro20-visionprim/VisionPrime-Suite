# Phase 5 — Google Search Console Integration

> **وضعیت (۲۰۲۶-۰۸-۱۵):** زیرساخت و ایمپورت کامل است؛ افزون بر import صف‌شده، کامند `php artisan gsc:import --site= --days= --sync` برای ایمپورت هم‌گام روزانه (بدون queue worker) اضافه شد — متریک‌های روزانهٔ جایگاه/کلیک/نمایش پایهٔ گزارش تأثیر پس از انتشار (D-019) هستند.

## Atomic tasks
- [x] P5-01 GSC Schema: accounts, properties, import runs, page/query/query-page metrics (مقیاس: `gsc_page_metrics` با ستون position)
- [x] P5-02 OAuth Service: encrypted tokens, state validation, refresh, disconnect audit
- [x] P5-03 Property Selection + Mapping (محدود به سازمان جاری — `GscPropertyIsolationTest`)
- [x] P5-04 Queued Import + Error Recovery (+ `gsc:import --sync` هم‌گام + `GscImportCommandTest`)
- [x] P5-05 GSC UI + QA
