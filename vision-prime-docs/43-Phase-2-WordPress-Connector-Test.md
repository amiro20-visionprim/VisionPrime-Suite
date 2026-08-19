# 43 — Phase 2: WordPress Connector — Test Plan & Status

**تاریخ:** ۲۰۲۶-۰۸-۱۹
**وضعیت:** در حال اجرا

---

## ۱) نمای کلی

اتصال امن بین Vision Prime (Laravel) و WordPress از طریق پلاگین اختصاصی.

**حلقه اتصال:**
```
WordPress Plugin → Pairing Token → Vision Prime → Secret → HMAC Signed Requests → Commands → Rollback
```

---

## ۲) وضعیت پلاگین وردپرس (v1.2.0)

| قابلیت | وضعیت | تست |
|---------|-------|------|
| Secret Encryption (AES-256-GCM) | ✅ | `VP_Secret::encrypt/decrypt` |
| HMAC Signing (SHA-256) | ✅ | `VP_API_Client::signed_request` |
| Integrity Checking | ✅ | `VP_Guard::file_hash/tampered` |
| Pairing Flow | ✅ | `VP_API_Client::pair` |
| Health Check | ✅ | `VP_API_Client::signed_health` |
| Content Sync (GET /content) | ✅ | `VP_Rest_API::content` |
| Command Execution | ✅ | 6 types: meta_title, meta_desc, content, product_title, product_desc, new_article |
| Rollback | ✅ | Snapshot-based, lossless |
| Product Info (WooCommerce) | ✅ | Price, stock, currency |
| Replay Attack Protection | ✅ | Nonce dedup via transients |
| Rank Math / Yoast Detection | ✅ | Dual meta key support |
| Multisite Support | ❌ | Deferred |

---

## ۳) وضعیت سمت سرور (Laravel)

| Endpoint | Controller | وضعیت |
|----------|-----------|-------|
| POST /connector/pair | PairSiteController | ✅ |
| POST /connector/health | HealthCheckController | ✅ |
| POST /connector/command-result | CommandResultController | ✅ |

| Service | وضعیت |
|---------|-------|
| VerifyConnectorSignature (HMAC) | ✅ |
| ConsumePairingToken | ✅ |
| CreatePairingToken | ✅ |
| DisconnectSite | ✅ |

---

## ۴) تست اتصال واقعی

### ۴.۱ پیش‌نیازها
- WordPress نصب شده (localhost:8080 یا سرور واقعی)
- پلاگین vision-prime-connector v1.2.0 فعال
- Vision Prime روی localhost:8000
- Site record در دیتابیس Vision Prime

### ۴.۲ مراحل تست
1. **ایجاد Site** در Vision Prime
2. **تولید Pairing Token** از UI
3. **نصب پلاگین** در WordPress
4. **وارد کردن Token** در تنظیمات پلاگین
5. **ذخیره تنظیمات** → ارسال درخواست pairing
6. **بررسی اتصال** → health check
7. **تست Content Sync** → دریافت لیست صفحات
8. **تست Command** → تغییر meta title
9. **تست Rollback** → بازگشت به حالت قبل

### ۴.۳ تست‌های امنیتی
- **Replay Attack**: nonce تکراری → رد
- **Timestamp Expired**: timestamp قدیمی → رد
- **Invalid Signature**: امضای نادرست → 403
- **Tampered Plugin**: فایل تغییر یافته → refused to sign

---

## ۵) ملاحظات عملیاتی

### WordPress Plugin Secret Storage
- Secret با AES-256-GCM رمزنگاری می‌شود
- کلید از wp_salt() مشتق می‌شود
- هرگز plaintext ذخیره نمی‌شود

### Rate Limiting
- سمت Laravel: middleware throttle روی endpoints
- سمت Plugin: نیاز به rate limiting اضافی (deferred)

### Offline Fallback
- اگر سرور Vision Prime قطع شود، plugin هیچ کاری نمی‌کند
- نیاز به offline queue برای command‌ها (deferred)

---

## ۶) وضعیت فعلی

| مورد | وضعیت |
|------|-------|
| پلاگین وردپرس | ✅ v1.2.0 کامل |
| سمت سرور | ✅ Endpoints آماده |
| تست واقعی | ⏳ نیاز به WordPress نصب شده |
| تست امنیت | ✅ Tests موجود در tests/Feature/Connector/ |

---

## ۷) گام‌های بعدی

1. نصب WordPress لوکال (localhost:8080)
2. نصب پلاگین و تست pairing
3. تست content sync با داده واقعی
4. تست command execution
5. تست rollback
6. تست امنیت (replay, tamper)
