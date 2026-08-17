<?php
/**
 * deploy-console.php — کنسول دیپلوی سایت‌های استاتیک + وبهوک گیت‌هاب
 *
 * کاربردها:
 *   1) فرم وب (GET) و آپلود/استقرار (POST) — توکن از /etc/deploy-secret
 *   2) وبهوک گیت‌هاب: ?domain=example.com&hook=1 با هدر X-Hub-Signature-256
 *      (سکرت از /etc/deploy-webhook-secret)
 */
declare(strict_types=1);

$token      = trim((string) @file_get_contents('/etc/deploy-secret'));
$hookSecret = trim((string) @file_get_contents('/etc/deploy-webhook-secret'));
$CLI        = '/usr/local/bin/deploy-site.sh';

$domain = (string) ($_GET['domain'] ?? ($_POST['domain'] ?? ''));
$isHook = isset($_GET['hook']) || (($_SERVER['HTTP_USER_AGENT'] ?? '') === 'GitHub-Hookshot');

function validDomain(string $d): bool
{
    return (bool) preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $d);
}

function runCli(array $args): array
{
    global $CLI;
    $cmd = $CLI;
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg($a);
    }
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    return ['code' => $code, 'out' => implode("\n", $out)];
}

/* ---------- وبهوک گیت‌هاب ---------- */
if ($isHook) {
    header('Content-Type: application/json; charset=utf-8');
    $payload = (string) file_get_contents('php://input');
    $sig = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
    $computed = 'sha256=' . hash_hmac('sha256', $payload, $hookSecret);
    if ($sig === '' || !hash_equals($computed, $sig)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'bad signature']);
        exit;
    }
    if (!validDomain($domain)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad domain']);
        exit;
    }
    $data = json_decode($payload, true);
    $repoUrl = (string) ($data['repository']['clone_url'] ?? '');
    if ($repoUrl === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'no repository url']);
        exit;
    }
    $res = runCli([$domain, $repoUrl]);
    http_response_code($res['code'] === 0 ? 200 : 500);
    echo json_encode(['ok' => $res['code'] === 0, 'domain' => $domain, 'output' => $res['out']]);
    exit;
}

/* ---------- کنسول وب ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = (string) ($_POST['token'] ?? '');
    if (!hash_equals($token, $t)) {
        http_response_code(403);
        die('<meta charset="utf-8"><div style="font-family:Tahoma;padding:2rem;color:#b91c1c">❌ توکن اشتباه است.</div>');
    }
    if (!validDomain($domain)) {
        http_response_code(400);
        die('<meta charset="utf-8"><div style="font-family:Tahoma;padding:2rem;color:#b91c1c">❌ نام دامنه نامعتبر است.</div>');
    }

    if (trim((string) ($_POST['git_url'] ?? '')) !== '') {
        $res = runCli([$domain, trim($_POST['git_url'])]);
    } elseif (!empty($_FILES['zip']['tmp_name']) && is_uploaded_file($_FILES['zip']['tmp_name'])) {
        $zip = '/tmp/deploy-' . $domain . '-' . bin2hex(random_bytes(4)) . '.zip';
        if (!move_uploaded_file($_FILES['zip']['tmp_name'], $zip)) {
            die('<meta charset="utf-8"><div style="font-family:Tahoma;padding:2rem;color:#b91c1c">❌ آپلود ناموفق بود.</div>');
        }
        $res = runCli([$domain, $zip]);
        @unlink($zip);
    } else {
        die('<meta charset="utf-8"><div style="font-family:Tahoma;padding:2rem;color:#b91c1c">❌ منبعی داده نشده — آدرس گیت یا فایل zip.</div>');
    }

    $ok = $res['code'] === 0;
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>نتیجهٔ دیپلوی</title></head><body style="font-family:Tahoma;background:#0f172a;color:#e2e8f0;padding:2rem">';
    echo '<h2 style="color:' . ($ok ? '#22c55e' : '#ef4444') . '">' . ($ok ? '✅ دیپلوی موفق شد' : '❌ دیپلوی ناموفق') . ' — ' . htmlspecialchars($domain) . '</h2>';
    echo '<pre style="background:#1e293b;padding:1rem;border-radius:10px;direction:ltr;text-align:left;overflow:auto">' . htmlspecialchars($res['out']) . '</pre>';
    echo '<p><a href="deploy.php" style="color:#38bdf8">← بازگشت به کنسول</a></p></body></html>';
    exit;
}

/* ---------- فرم ---------- */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>کنسول دیپلوی سایت‌های استاتیک</title>
<style>
  body{font-family:Tahoma,Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:2rem;display:flex;justify-content:center}
  .card{background:#1e293b;padding:2rem;border-radius:16px;max-width:560px;width:100%}
  h1{color:#38bdf8;font-size:1.4rem;margin-top:0}
  label{display:block;margin:1rem 0 .3rem;font-weight:bold}
  input[type=text],input[type=password],input[type=file]{width:100%;padding:.6rem;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#e2e8f0;box-sizing:border-box}
  button{width:100%;margin-top:1.5rem;padding:.8rem;border:0;border-radius:10px;background:#2563eb;color:#fff;font-size:1rem;cursor:pointer}
  button:hover{background:#1d4ed8}
  .hint{font-size:.8rem;color:#94a3b8;margin-top:.25rem}
  .sep{border-top:1px dashed #334155;margin:1.5rem 0}
</style>
</head>
<body>
<div class="card">
  <h1>🚀 کنسول دیپلوی سایت‌های استاتیک</h1>
  <form method="post" enctype="multipart/form-data">
    <label>دامنه (مثلاً example.com)</label>
    <input type="text" name="domain" required placeholder="example.com">
    <label>توکن</label>
    <input type="password" name="token" required>
    <div class="sep"></div>
    <label>روش ۱ — آدرس مخزن گیت (Git URL)</label>
    <input type="text" name="git_url" placeholder="https://github.com/user/repo.git">
    <div class="hint">کد مخزن به‌صورت خودکار clone/pull و روی سایت قرار می‌گیرد.</div>
    <div class="sep"></div>
    <label>روش ۲ — آپلود فایل zip</label>
    <input type="file" name="zip" accept=".zip">
    <div class="hint">فایل zip فایل‌های سایت (index.html و ...) — یک پوشهٔ ریشه هم باشد خودکار باز می‌شود.</div>
    <button type="submit">استقرار</button>
  </form>
</div>
</body>
</html>
