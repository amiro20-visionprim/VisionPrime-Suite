# راه‌اندازی OAuth سرچ کنسول گوگل (GSC)

برای اتصال واقعی به Google Search Console باید یک **OAuth Client** در Google Cloud بسازی.
این کار فقط با حساب گوگل خودت انجام می‌شود (حدود ۵ دقیقه). بعد از ساخت، مقادیر را در `vision-prime/.env` می‌گذاریم.

## مرحله ۱ — ساخت پروژه

1. وارد [Google Cloud Console](https://console.cloud.google.com/) شو (با همون ایمیلی که سرچ کنسول سایت‌هات توشه).
2. از بالا، پروژه‌ای را انتخاب کن یا **New Project** بزن (مثلاً `visionprime-suite`).
3. بعد از ساخت، از نوار بالا مطمئن شو که همان پروژه انتخاب شده.

## مرحله ۲ — فعال‌سازی API

1. به **APIs & Services → Library** برو.
2. جستجو کن: `Google Search Console API`
3. روی نتیجه کلیک کن و **Enable** بزن.

## مرحله ۳ — صفحهٔ رضایت (OAuth consent screen)

1. به **APIs & Services → OAuth consent screen** برو.
2. نوع **External** را انتخاب کن (حتی برای استفادهٔ شخصی).
3. فرم را پر کن:
   - App name: `VisionPrime Suite`
   - User support email: ایمیل خودت
   - Developer contact: ایمیل خودت
4. در بخش **Audience**، **Test users** را انتخاب کن و **ایمیل خودت** را اضافه کن (تا وقتی اپ در حالت Testing است فقط تو بتوانی وصل شوی).

## مرحله ۴ — ساخت OAuth Client ID

1. به **APIs & Services → Credentials** برو.
2. **Create Credentials → OAuth client ID** بزن.
3. Application type: **Web application**
4. نام: `VisionPrime Local`
5. در **Authorized redirect URIs** دقیقاً این را اضافه کن:

```
http://127.0.0.1:8000/app/gsc/callback
```

6. **Create** بزن.

> نکته: برای نسخهٔ عمومی (وقتی روی دامنهٔ `visionprime-suite.ir` یا لینک `*.ts.net` دیپلوی شد) باید یک redirect URI دیگر هم اضافه کنی:
> `https://visionprime-suite.ir/app/gsc/callback`

## مرحله ۵ — کپی مقادیر در .env

در صفحهٔ Credentials، روی client ساخته‌شده کلیک کن و دو مقدار را کپی کن:

```env
# vision-prime/.env
GSC_CLIENT_ID=xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com
GSC_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
GSC_REDIRECT_URI="http://127.0.0.1:8000/app/gsc/callback"
```

## مرحله ۶ — تست اتصال

بعد از ست کردن `.env`، سرور dev را ریاستارت کن و:

1. وارد پرتال شو: `http://127.0.0.1:8000/app/gsc`
2. دکمهٔ «اتصال حساب Google» را بزن → گوگل صفحهٔ اجازه را نشان می‌دهد → اجازه بده.
3. به داشبورد GSC برمی‌گردی و حساب با وضعیت `connected` ثبت می‌شود.
4. «انتخاب ملک» (property) → ملک سایت را انتخاب کن.
5. «شروع Import» → دادهٔ واقعی سرچ کنسول وارد می‌شود.

## عیب‌یابی

| خطا | علت |
|---|---|
| `redirect_uri_mismatch` | آدرس redirect در گوگل با `GSC_REDIRECT_URI` یکی نیست (دقیق کپی کن، بدون `/` اضافه) |
| `access_denied` | ایمیلی که با آن لاگین می‌کنی در **Test users** نیست |
| بعد از callback صفحهٔ 403 | state منقضی شده؛ دوباره از دکمهٔ اتصال شروع کن |
| `consent screen` دیده نمی‌شود | مطمئن شو نوع اپ **External** است |
