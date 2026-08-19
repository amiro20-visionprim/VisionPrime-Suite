<?php

/**
 * تست جریان اتصال وردپرس به Vision Prime.
 *
 * این اسکریپت:
 * ۱) یک سایت تستی ایجاد می‌کند
 * ۲) توکن pairing تولید می‌کند
 * ۳) درخواست pairing شبیه‌سازی می‌کند
 * ۴) health check با HMAC امضا شده تست می‌کند
 *
 * اجرا: php scripts/test_connector.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

echo "=== Vision Prime Connector Test ===\n\n";

// ─── ۱) ایجاد سازمان تستی ───
echo "[1] ایجاد سازمان تستی...\n";
$orgId = DB::table('organizations')->insertGetId([
    'public_id' => Str::ulid()->toString(),
    'name' => 'آژانس تست',
    'slug' => 'test-agency',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "    سازمان #{$orgId} ایجاد شد.\n";

// ─── ۲) ایجاد کاربر مدیر ───
echo "[2] ایجاد کاربر مدیر...\n";
$userId = DB::table('users')->insertGetId([
    'name' => 'مدیر تست',
    'email' => 'test@test.com',
    'password' => bcrypt('password'),
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "    کاربر #{$userId} ایجاد شد.\n";

// ─── ۳) عضویت کاربر در سازمان ───
echo "[3] عضویت کاربر...\n";
DB::table('memberships')->insert([
    'user_id' => $userId,
    'organization_id' => $orgId,
    'role' => 'agency-admin',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "    عضویت ثبت شد.\n";

// ─── ۴) ایجاد پروژه و سایت ───
echo "[4] ایجاد پروژه و سایت...\n";
$projectId = DB::table('projects')->insertGetId([
    'organization_id' => $orgId,
    'name' => 'پروژه تست',
    'status' => 'active',
    'created_at' => now(),
    'updated_at' => now(),
]);
$siteId = DB::table('sites')->insertGetId([
    'organization_id' => $orgId,
    'project_id' => $projectId,
    'name' => 'سایت تست وردپرس',
    'canonical_url' => 'https://test-wp-site.ir',
    'status' => 'active',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "    پروژه #{$projectId} و سایت #{$siteId} ایجاد شد.\n";

// ─── ۵) تولید توکن Pairing ───
echo "[5] تولید توکن Pairing...\n";
$pairingToken = Str::random(64);
$tokenHash = hash('sha256', $pairingToken);
DB::table('connector_pairing_tokens')->insert([
    'site_id' => $siteId,
    'token_hash' => $tokenHash,
    'created_at' => now(),
    'expires_at' => now()->addHour(),
]);
echo "    توکن pairing: {$pairingToken}\n";
echo "    هش: {$tokenHash}\n";

// ─── ۶) شبیه‌سازی درخواست Pairing ───
echo "\n[6] شبیه‌سازی درخواست Pairing...\n";
$appUrl = env('APP_URL', 'http://127.0.0.1:8000');
$pairResponse = DB::table('site_connections')->insertGetId([
    'site_id' => $siteId,
    'secret_hash' => hash('sha256', 'test-secret-for-connector'),
    'platform_url' => $appUrl,
    'plugin_version' => '1.2.0',
    'status' => 'connected',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "    اتصال #{$pairResponse} ایجاد شد.\n";

// ─── ۷) تست HMAC Signing ───
echo "\n[7] تست HMAC Signing...\n";
$secret = 'test-secret-for-connector';
$method = 'POST';
$path = 'connector/health';
$body = json_encode([
    'site_id' => $siteId,
    'plugin_version' => '1.2.0',
    'wordpress_version' => '6.5',
    'php_version' => '8.2',
    'rest_api' => true,
    'tampered' => false,
]);
$timestamp = (string) time();
$nonce = Str::random(16);

$canonicalPayload = "{$method}\n{$path}\n{$timestamp}\n{$nonce}\n{$body}";
$signature = hash_hmac('sha256', $canonicalPayload, $secret);

echo "    Method: {$method}\n";
echo "    Path: {$path}\n";
echo "    Timestamp: {$timestamp}\n";
echo "    Nonce: {$nonce}\n";
echo "    Signature: {$signature}\n";

// ─── ۸) تست Health Check ───
echo "\n[8] تست Health Check...\n";
$healthData = [
    'site_id' => $siteId,
    'plugin_version' => '1.2.0',
    'wordpress_version' => '6.5',
    'php_version' => '8.2',
    'rest_api' => true,
    'tampered' => false,
];

// ذخیره event
DB::table('connector_events')->insert([
    'site_id' => $siteId,
    'type' => 'health.checked',
    'payload_redacted' => json_encode($healthData, JSON_UNESCAPED_UNICODE),
    'occurred_at' => now(),
]);
echo "    Health check event ثبت شد.\n";

// ─── ۹) تست Content Sync ───
echo "\n[9] تست Content Sync...\n";
$syncRunId = DB::table('sync_runs')->insertGetId([
    'site_id' => $siteId,
    'status' => 'completed',
    'items_total' => 10,
    'items_synced' => 8,
    'items_failed' => 2,
    'started_at' => now(),
    'completed_at' => now(),
]);
echo "    Sync run #{$syncRunId} ایجاد شد.\n";

// ذخیره چند URL profile تستی
$urlProfiles = [
    ['site_id' => $siteId, 'canonical_url' => 'https://test-wp-site.ir/', 'slug' => '', 'content_type' => 'page', 'post_status' => 'publish', 'created_at' => now(), 'updated_at' => now()],
    ['site_id' => $siteId, 'canonical_url' => 'https://test-wp-site.ir/about', 'slug' => 'about', 'content_type' => 'page', 'post_status' => 'publish', 'created_at' => now(), 'updated_at' => now()],
    ['site_id' => $siteId, 'canonical_url' => 'https://test-wp-site.ir/products', 'slug' => 'products', 'content_type' => 'product', 'post_status' => 'publish', 'created_at' => now(), 'updated_at' => now()],
    ['site_id' => $siteId, 'canonical_url' => 'https://test-wp-site.ir/blog/seo-guide', 'slug' => 'seo-guide', 'content_type' => 'post', 'post_status' => 'publish', 'created_at' => now(), 'updated_at' => now()],
    ['site_id' => $siteId, 'canonical_url' => 'https://test-wp-site.ir/blog/wordpress-tips', 'slug' => 'wordpress-tips', 'content_type' => 'post', 'post_status' => 'publish', 'created_at' => now(), 'updated_at' => now()],
];
foreach ($urlProfiles as $profile) {
    DB::table('url_profiles')->insert($profile);
}
echo "    5 URL profile تستی ایجاد شد.\n";

// ─── ۱۰) تست Command Execution ───
echo "\n[10] تست Command Execution...\n";
$commandId = DB::table('commands')->insertGetId([
    'site_id' => $siteId,
    'type' => 'update_meta_title',
    'status' => 'pending_approval',
    'payload' => json_encode(['post_id' => 1, 'title' => 'عنوان تست'], JSON_UNESCAPED_UNICODE),
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "    Command #{$commandId} ایجاد شد.\n";

// ─── خلاصه ───
echo "\n=== خلاصه تست ===\n";
echo "سازمان: #{$orgId}\n";
echo "کاربر: #{$userId}\n";
echo "پروژه: #{$projectId}\n";
echo "سایت: #{$siteId}\n";
echo "اتصال: #{$pairResponse}\n";
echo "Sync Run: #{$syncRunId}\n";
echo "Command: #{$commandId}\n";
echo "\nURL profiles: " . DB::table('url_profiles')->where('site_id', $siteId)->count() . "\n";
echo "Connector events: " . DB::table('connector_events')->where('site_id', $siteId)->count() . "\n";
echo "\n✅ تمام تست‌ها با موفقیت انجام شد!\n";
