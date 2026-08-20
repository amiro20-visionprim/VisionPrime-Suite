# Vision Prime SUITE — RBAC & Permission Matrix

## نقش‌ها
Roleها در MVP تجاری اولیه قابل استفاده‌اند، اما Permissionها منبع حقیقت هستند. یک Role صرفاً مجموعه‌ای از Permissionهاست.

| Role | شرح |
|---|---|
| Super Admin | مالک/اپراتور کل پلتفرم؛ فقط برای مدیریت سطح پلتفرم |
| Agency Admin | مدیر سازمان/آژانس و مسئول نهایی سایت‌ها و billing |
| SEO Manager | مالک عملیات SEO، فرصت‌ها و توصیه‌ها |
| Content Manager | مدیریت brief، draft و توصیه‌های محتوایی |
| Expert Reviewer | بررسی و تصمیم روی موارد نیازمند تأیید |
| Developer | مدیریت Connector و اجرای فنی مجاز |
| Client Viewer | مشاهده اطلاعات مجاز در Client Portal |
| Client Approver | Client Viewer با اجازه تصمیم روی آیتم‌های تعیین‌شده |

## قواعد مرزبندی داده
- هر User متعلق به حداقل یک Organization است.
- داده Client/Project/Site فقط در Organization خود قابل دیدن است.
- Client user فقط به Clientهای assigned و Siteهای مجاز دسترسی دارد.
- Super Admin دسترسی cross-organization دارد و تمام دسترسی‌های او audit می‌شود.
- Permissionهای حساس باید server-side با Laravel Policy/Authorization بررسی شوند؛ مخفی‌کردن دکمه در Vue کافی نیست.

## Permission Matrix سطح بالا
| Domain / Action | Super | Agency | SEO | Content | Reviewer | Dev | Client Viewer | Client Approver |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Manage organization/members | ✓ | ✓ | - | - | - | - | - | - |
| Manage clients/projects/sites | ✓ | ✓ | ✓ محدود | - | - | - | - | - |
| View intelligence | ✓ | ✓ | ✓ | ✓ محدود | ✓ | ✓ محدود | ✓ خلاصه | ✓ خلاصه |
| Create recommendations | ✓ | ✓ | ✓ | ✓ محتوایی | ✓ | - | - | - |
| Manage AI templates/settings | ✓ | - | - | - | - | - | - | - |
| Review internal items | ✓ | ✓ | - | - | ✓ | - | - | - |
| Approve client-facing decision | ✓ | ✓ | ✓ در policy | - | ✓ در policy | - | - | ✓ در policy |
| Pair/manage connector | ✓ | ✓ | - | - | - | ✓ | - | - |
| Create/dispatch commands | ✓ | ✓ در policy | ✓ در policy | - | - | ✓ در policy | - | - |
| Change automation policy | ✓ | ✓ | - | - | - | ✓ پیشنهاد فقط | - | - |
| Emergency stop automation | ✓ | ✓ | ✓ برای site assigned | - | ✓ برای item assigned | ✓ برای site assigned | - | - |
| Rollback command | ✓ | ✓ | ✓ در policy | - | ✓ در policy | ✓ در policy | - | - |
| View/export reports | ✓ | ✓ | ✓ | ✓ محدود | ✓ | ✓ محدود | ✓ assigned | ✓ assigned |
| View audit logs | ✓ | ✓ own org | ✓ limited own actions | ✓ own actions | ✓ review actions | ✓ connector actions | - | - |

## کنترل‌های تأیید
- Approval requirement از ترکیب `Command risk tier + Site Automation Policy + Organization policy` تعیین می‌شود.
- هیچ Role به‌تنهایی bypass سراسری ندارد، جز Super Admin با audit reason اجباری.
- Client Approver فقط روی آیتم‌هایی که برای Client visibility و approval منتشر شده‌اند می‌تواند تصمیم بگیرد.

## Acceptance Criteria
- هر route و mutation policy test دارد.
- تغییر Role، assignment و permission در Audit Log ثبت می‌شود.
- حذف دسترسی باید session/API authorization بعدی را فوراً منع کند.
- Client Viewer نمی‌تواند با تغییر URL به routeهای `/app` یا داده خام دسترسی یابد.
