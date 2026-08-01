# Vision Prime — Domain Model, ERD & Data Dictionary v1

## قواعد مدل داده
- تمام رکوردهای business-scoped باید `organization_id` داشته باشند، مستقیم یا از مسیر parent قابل اثبات باشند.
- UUID/ULID برای شناسه‌های public و قابل‌نمایش پیشنهاد می‌شود؛ ID داخلی می‌تواند bigint باشد.
- تاریخ‌ها UTC ذخیره می‌شوند؛ `created_at`, `updated_at` استاندارد هستند.
- soft delete فقط برای entityهایی که حذف عملیاتی دارند؛ AuditLog هرگز soft/hard delete نمی‌شود.
- secret/token هرگز plaintext ذخیره نمی‌شود: tokenهای OAuth encrypted، secretهای قابل verify hashed یا encrypted طبق نیاز استفاده می‌شوند.

## ERD سطح بالا
```text
Organization 1─* Membership *─1 User
Organization 1─* Client 1─* Project 1─* Site
Site 1─1 SiteConnection
Site 1─* UrlProfile 1─* ContentSnapshot
Site 1─* SyncRun 1─* SyncRunItem
Site 1─* GscProperty 1─* GscImportRun
GscProperty 1─* GscPageMetric / GscQueryMetric / GscQueryPageMetric
Site 1─* KeywordInsight 1─* IntentClassification
Site 1─* Opportunity 1─* OpportunityFactor
UrlProfile 1─* MoneyPageAudit 1─* MoneyPageIssue
UrlProfile 1─* ConversionRisk 1─* ConversionRiskFactor
Opportunity/MoneyPageAudit/ConversionRisk 1─* Recommendation
Recommendation or AiGeneration 1─* ReviewItem 1─* ReviewDecision
Site 1─1 SiteAutomationPolicy
Site 1─* Command 1─* CommandExecutionLog
Command 1─* CommandApproval / RollbackSnapshot
Organization 1─* AuditLog
```

## Core Identity & Workspace
| Entity | فیلدهای اصلی | مسئولیت |
|---|---|---|
| organizations | id, public_id, name, slug, status, settings_json | مرز tenant و تنظیمات سازمان |
| users | id, public_id, name, email, password, locale, timezone, preferences_json | هویت کاربر |
| memberships | organization_id, user_id, role_id, status, assigned_scope_json | عضویت و scope دسترسی |
| roles | id, key, name, is_system | گروه permissionها |
| permissions | id, key, domain, description | capabilityهای دقیق |
| clients | organization_id, public_id, name, status, contact_json | مشتری آژانس |
| projects | client_id, public_id, name, objective, status, settings_json | container کاری |
| sites | project_id, public_id, name, canonical_url, timezone, locale, business_importance, status | واحد اصلی تحلیل/اتصال |

## Connector & Content Domain
| Entity | فیلدهای اصلی | مسئولیت |
|---|---|---|
| site_connections | site_id, connector_version, status, platform_url, secret_ciphertext/ref, last_seen_at, health_json | وضعیت اتصال وردپرس |
| pairing_tokens | site_id, token_hash, expires_at, consumed_at, created_by | pairing یک‌بارمصرف |
| connector_nonces | site_connection_id, nonce, expires_at, used_at | replay prevention |
| connector_events | site_id, type, payload_redacted_json, occurred_at | رخدادهای plugin |
| sync_runs | site_id, type, status, started_at, finished_at, cursor, summary_json, error_json | هر اجرای sync |
| sync_run_items | sync_run_id, external_id, url, status, action, error_json | نتیجه هر آیتم sync |
| url_profiles | site_id, external_content_id, canonical_url, slug, content_type, post_status, metadata_json, current_hash, last_synced_at | نمای فعلی هر URL |
| content_snapshots | url_profile_id, content_hash, title, meta_json, headings_json, content_ref, word_count, captured_at | تاریخچه تغییر محتوا |

## GSC & Intelligence Domain
| Entity | فیلدهای اصلی | مسئولیت |
|---|---|---|
| gsc_accounts | organization_id, google_subject, email, token_ciphertext, token_expires_at, status | حساب OAuth |
| gsc_properties | site_id, gsc_account_id, property_uri, property_type, status, selected_at | property متصل به Site |
| gsc_import_runs | gsc_property_id, date_start, date_end, dimensions_json, status, summary_json, error_json | import قابل‌پیگیری |
| gsc_page_metrics | property_id, date, page_url, clicks, impressions, ctr, position, device, country | performance صفحه |
| gsc_query_metrics | property_id, date, query, clicks, impressions, ctr, position, device, country | performance query |
| gsc_query_page_metrics | property_id, date, query, page_url, clicks, impressions, ctr, position | mapping query/page |
| keyword_insights | site_id, query_normalized, latest_metrics_json, mapped_url_profile_id, status | نمای تجمیعی Keyword |
| intent_classifications | keyword_insight_id, intent, confidence, method, explanation, rules_version | خروجی intent |
| opportunities | site_id, url_profile_id, keyword_insight_id, type, score, confidence, status, explanation | فرصت اولویت‌دار |
| opportunity_factors | opportunity_id, key, weight, raw_value, normalized_value, explanation | explainability score |

## Recommendation, AI, Review & Command Domain
| Entity | فیلدهای اصلی | مسئولیت |
|---|---|---|
| recommendations | site_id, source_type, source_id, title, body, priority, status, owner_id, due_at | اقدام پیشنهادی قابل‌پیگیری |
| ai_provider_settings | organization_id, provider, encrypted_config, status | تنظیمات AI مرکزی |
| ai_prompt_templates | organization_id, key, version, input_schema, template, status | templateهای نسخه‌دار |
| ai_generations | site_id, template_id, input_redacted_json, output_status, current_version_id, usage_json | یک generation |
| ai_generation_versions | generation_id, version, output_json, model_meta_json, status | نسخه‌های خروجی |
| review_items | site_id, subject_type, subject_id, status, assigned_to, due_at, policy_snapshot_json | صف بررسی |
| review_decisions | review_item_id, decision, note, decided_by, decided_at | تصمیم immutable |
| site_automation_policies | site_id, version, level, rules_json, emergency_stopped_at, updated_by | سیاست L0-L4 |
| commands | site_id, source_type, source_id, type, risk_tier, payload_json, idempotency_key, status, expires_at, policy_version | درخواست تغییر |
| command_approvals | command_id, reviewer_id, decision, note, policy_snapshot_json | تأیید command |
| command_execution_logs | command_id, attempt, status, request_redacted_json, response_redacted_json, executed_at | lifecycle اجرا |
| rollback_snapshots | command_id, target_ref, snapshot_ciphertext/ref, expires_at, status | پیش‌نیاز rollback |

## Reporting, Audit, Commercial Domain
| Entity | فیلدهای اصلی | مسئولیت |
|---|---|---|
| reports | site_id, type, period_start, period_end, status, content_json, generated_by, published_at | گزارش نسخه‌دار |
| impact_events | site_id, source_type, source_id, baseline_json, outcome_json, observed_at, attribution_note | اتصال اقدام به outcome با احتیاط |
| audit_logs | organization_id, actor_type, actor_id, action, subject_type, subject_id, before_json, after_json, ip_hash, occurred_at | trail غیرقابل تغییر |
| entitlements | organization_id, key, value_json, starts_at, ends_at | آماده‌سازی Plan بدون Billing آنلاین |

## Indexing و Retention اولیه
- metrics: composite index روی `(gsc_property_id, date, page_url/query)`.
- URL profile: unique روی `(site_id, canonical_url)`.
- command: unique روی `(site_id, idempotency_key)`.
- nonce: unique روی `(site_connection_id, nonce)`؛ cleanup بعد از expiration.
- AuditLog و execution logs retention قابل تنظیم اما حذف آن‌ها نیازمند policy سازمانی است.
