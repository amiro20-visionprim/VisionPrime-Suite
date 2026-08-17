<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>نتیجهٔ پرداخت — {{ config('app.name', 'Vision Prime SUITE') }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 dark:bg-gray-950 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-3xl mb-4 {{ $ok ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' }}">
                {{ $ok ? '✓' : '✗' }}
            </div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                {{ $ok ? 'پرداخت موفق بود' : 'پرداخت ناموفق بود' }}
            </h1>
            <p class="text-gray-600 dark:text-gray-300 mb-1">مبلغ: {{ number_format($amount) }} ریال</p>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">شمارهٔ پیگیری: {{ $reference }}</p>
            <a href="{{ route('platform.dashboard') }}"
               class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl px-6 py-3 transition">
                بازگشت به اتاق فرماندهی
            </a>
        </div>
    </body>
</html>
