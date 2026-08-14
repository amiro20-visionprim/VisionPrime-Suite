<?php

declare(strict_types=1);

/**
 * Vision Prime Connector — production build.
 *
 * Produces dist/vision-prime-connector.php + dist/vision-prime-connector.zip
 * from the readable source tree:
 *
 *  1. Inlines the four include classes into the main plugin file.
 *  2. Minifies (strips comments / whitespace) and hex-escapes every string
 *     literal, so the shipped code is not human-readable.
 *  3. Embeds a self-referential SHA-256 (SELF_HASH) so VP_Guard can detect any
 *     modification of the installed file and refuse to sign / execute.
 *
 * Usage: php build.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = __DIR__;
$src = $root . '/vision-prime-connector.php';
$distDir = $root . '/dist';
$outFile = $distDir . '/vision-prime-connector.php';
$outZip = $distDir . '/vision-prime-connector.zip';

$includes = [
    'includes/class-vp-guard.php',
    'includes/class-vp-secret.php',
    'includes/class-vp-api-client.php',
    'includes/class-vp-request-verifier.php',
];

/* --------------------------------------------------------------------------
 * 1) Inline includes into the main file.
 * ------------------------------------------------------------------------ */
$main = (string) file_get_contents($src);
foreach ($includes as $inc) {
    $path = $root . '/' . $inc;
    if (! is_file($path)) {
        fwrite(STDERR, "Missing include: {$inc}\n");
        exit(1);
    }
    $body = (string) file_get_contents($path);
    // strip the <?php opener and the ABSPATH guard line from each include
    $body = preg_replace('/^<\?php\s*/', '', $body);
    $body = preg_replace("/^defined\('ABSPATH'\)\s*\|\|\s*exit;\s*/", '', $body);
    $main = preg_replace(
        "/require_once\s+__DIR__\s*\.\s*'\/" . preg_quote($inc, '/') . "';\s*/",
        $body,
        $main,
        1
    );
}
if (str_contains($main, 'require_once')) {
    fwrite(STDERR, "Failed to inline one or more requires.\n");
    exit(1);
}

/* --------------------------------------------------------------------------
 * 2) Keep the plugin header comment readable (WordPress needs it), encode the rest.
 * ------------------------------------------------------------------------ */
if (! preg_match('/\A<\?php\s*(\/\*\*(?:[^*]|\*(?!\/))*\*\/)/', $main, $m)) {
    fwrite(STDERR, "Plugin header comment not found.\n");
    exit(1);
}
$header = $m[1];
$body = substr($main, strlen($m[0]));

$encoded = encode_php($body);
$dist = "<?php\n{$header}\n{$encoded}\n";

/* --------------------------------------------------------------------------
 * 3) Embed the self-referential integrity hash.
 * ------------------------------------------------------------------------ */
$placeholder = str_repeat('0', 64);
if (! preg_match("/const SELF_HASH = (?:''|\"\");/", $dist, $m)) {
    fwrite(STDERR, "SELF_HASH placeholder not found in encoded output.\n");
    exit(1);
}
$dist = str_replace($m[0], "const SELF_HASH = '{$placeholder}';", $dist);
$selfHash = hash('sha256', $dist); // hash of the file with SELF_HASH zeroed
$dist = str_replace("const SELF_HASH = '{$placeholder}';", "const SELF_HASH = '{$selfHash}';", $dist);

/* --------------------------------------------------------------------------
 * 4) Validate, write, zip.
 * ------------------------------------------------------------------------ */
if (! is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}
file_put_contents($outFile, $dist);

// sanity: php -l on the encoded file
$lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($outFile) . ' 2>&1');
if (! str_contains((string) $lint, 'No syntax errors')) {
    fwrite(STDERR, "Encoded file failed lint:\n{$lint}\n");
    exit(1);
}

// sanity: the guard must agree with itself (stub the WP hooks the bootstrap touches)
$tmp = $distDir . '/_guard_check.php';
file_put_contents($tmp, "<?php\ndefine('ABSPATH', __DIR__);\n"
    . "function add_action() {}\nfunction register_setting() {}\nfunction add_options_page() {}\nfunction add_menu_page() {}\nfunction register_rest_route() {}\n"
    . 'require __DIR__ . \'/vision-prime-connector.php\';' . "\n"
    . "echo VP_Guard::tampered() ? 'TAMPERED' : 'OK';\n");
$guardOk = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1');
unlink($tmp);
if (trim((string) $guardOk) !== 'OK') {
    fwrite(STDERR, "Guard self-check failed: {$guardOk}\n");
    exit(1);
}

// zip (folder layout so WP admin upload accepts it)
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($outZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $zip->addFile($outFile, 'vision-prime-connector/vision-prime-connector.php');
        // WordPress reads icon files from the plugin folder for the Plugins
        // list and the details modal — ship them so the brand icon shows.
        foreach (['icon-128x128.png', 'icon-256x256.png'] as $icon) {
            if (is_file($root . '/' . $icon)) {
                $zip->addFile($root . '/' . $icon, 'vision-prime-connector/' . $icon);
            } else {
                fwrite(STDERR, "Missing icon asset: {$icon}\n");
            }
        }
        $zip->close();
        echo "OK  {$outFile}\n    (" . number_format(filesize($outFile)) . " bytes, sha256 " . hash_file('sha256', $outFile) . ")\n";
        echo "OK  {$outZip}\n";
    } else {
        fwrite(STDERR, "Failed to create zip.\n");
        exit(1);
    }
} else {
    echo "WARN ZipArchive unavailable — zip skipped.\n";
}

echo "OK  build complete (version 1.2.0)\n";

/* --------------------------------------------------------------------------
 * Encoder
 * ------------------------------------------------------------------------ */

/**
 * Minify the PHP code and hex-escape every safe string literal so the shipped
 * file is not human-readable. No eval, no base64 blobs — the output is plain
 * executable PHP, which keeps host security scanners happy.
 */
function encode_php(string $code): string
{
    $out = '';
    $firstOpenTag = true;
    // token_get_all needs an open tag, otherwise everything is T_INLINE_HTML;
    // only drop the tag we prepend — mid-code `<?php` reopeners must stay.
    foreach (token_get_all('<?php ' . $code) as $tok) {
        if (is_array($tok)) {
            [$id, $text] = $tok;
            if ($id === T_OPEN_TAG && $firstOpenTag) {
                $firstOpenTag = false;
                continue;
            }
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }
            if ($id === T_WHITESPACE) {
                $out .= ' ';
                continue;
            }
            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $out .= encode_string($text);
                continue;
            }
            $out .= $text;
        } else {
            $out .= $tok;
        }
    }

    return trim($out);
}

function encode_string(string $literal): string
{
    $quote = $literal[0];
    $inner = substr($literal, 1, -1);

    if ($quote === "'") {
        // single-quoted: only \\ and \' are escapes
        $value = str_replace(["\\\\", "\\'"], ["\\", "'"], $inner);
    } else {
        // double-quoted: keep untouched when it could interpolate or carry \u{}
        if (str_contains($inner, '$') || str_contains($inner, '{') || str_contains($inner, '\\u{')) {
            return $literal;
        }
        $value = stripcslashes($inner);
        if (str_ends_with($value, '\\')) {
            return $literal; // suspicious parse; keep original
        }
    }

    $hex = '';
    foreach (str_split($value) as $ch) {
        $hex .= sprintf('\\x%02x', ord($ch));
    }

    return '"' . $hex . '"';
}
