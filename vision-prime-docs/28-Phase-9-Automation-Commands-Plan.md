# Phase 9 — Automation + Secure Commands

> **وضعیت (۲۰۲۶-۰۸-۱۵):** کامل اجرا شده — L0–L4 + AI_policy + امتیاز اطمینان + انتشار خودکار با گیت‌های scope/گرمایش/کیفیت (D-017) + توقف اضطراری + rollback خودکار R3 + پروفایل‌های org-scoped. شش نوع command در پلاگین فعال است (از جمله `publish_new_article` برای مقاله/محصول جدید و `update_content`).

- [x] P9-01 Schema: site automation policies, commands, approvals, execution logs, rollback snapshots (+ `content_type`، `confidence_score`، `confidence_factors`، `decision_source`، `published_at`، `reviewer_type`، `organization_id` روی پروفایل‌ها، `auto_publish_scope`، `content_standards`)
- [x] P9-02 Policy evaluation + automation levels L0-L4 (PolicyEvaluator + گرمایش + کیفیت محتوا + دامنهٔ انتشار)
- [x] P9-03 Command creation + approval gate (انسانی + سیستم؛ از پیشنویس تأییدشده → `publish_new_article` با CreateArticlePublishCommand)
- [x] P9-04 Signed dispatch + expiry + idempotency (هم‌راه replay protection تست‌شده)
- [x] P9-05 Rollback + emergency stop + UI + QA (Endpoint `/rollback` پلاگین + RollbackMonitor + داشبورد 2-4 و Trust)
