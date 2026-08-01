# P2-05 — Site CRUD & First-Site Onboarding Specification

## هدف
تکمیل Journey اصلی Workspace:

`Client → Project → Site → آماده اتصال GSC / WordPress`

## Site Form Contract
| Field | Rule | UX |
|---|---|---|
| Project | required, belongs to Current Organization | Select با نام Client کنار Project |
| Site name | required, 2–160 chars | نام قابل فهم تیم، نه لزوماً Domain |
| Canonical URL | required URL, http/https only, normalized | LTR؛ حذف slash انتهایی؛ یکتا در Organization |
| Locale | required, default `fa` | فارسی/انگلیسی؛ قابل توسعه |
| Timezone | required, default `Asia/Tehran` | برای report و execution window |
| Business importance | integer 1–5, default 3 | Low / Medium / High با توضیح اثر بر Opportunity Score |

## Server-side invariants
1. Project باید متعلق به Current Organization باشد.
2. Canonical URL پس از normalization در Organization یکتا است.
3. Site به Project سازمان دیگر attach نمی‌شود.
4. Create / Update / Archive همیشه Audit دارند.
5. Archive از Soft Delete استفاده می‌کند.

## First-Site Journey
1. User در Dashboard یا Project Detail روی «افزودن سایت» کلیک می‌کند.
2. اگر فقط یک Project فعال وجود داشته باشد، Project به‌صورت پیش‌فرض انتخاب می‌شود.
3. URL اعتبارسنجی و Canonicalized می‌شود.
4. Site ساخته و `site.created` audit می‌شود.
5. Success screen دو CTA دارد:
   - «اتصال سرچ کنسول» (فاز GSC)
   - «اتصال وردپرس» (فاز Connector)
6. تا قبل از آماده‌شدن integrationها، کاربر Site Detail و وضعیت «آماده اتصال» را می‌بیند.

## Atomic implementation order
- P2-05.1 URL Normalization Service + Form Requests + Site Actions
- P2-05.2 Site Controller / routes / tenant policy enforcement
- P2-05.3 Site List / Create / Edit / Detail UI
- P2-05.4 First-site onboarding handoff + dashboard update
- P2-05.5 Tests / mobile RTL QA
