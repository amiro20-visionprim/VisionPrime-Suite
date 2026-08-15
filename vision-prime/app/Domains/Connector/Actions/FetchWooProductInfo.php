<?php

declare(strict_types=1);

namespace App\Domains\Connector\Actions;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * خواندن دادهٔ واقعی محصول از ووکامرس از طریق کانکتور وردپرس (رابط product-info).
 *
 * درخواست امضاشده (HMAC — همان قرارداد DispatchCommand) ارسال می‌شود تا بازبین
 * پیش از تأیید انتشار، قیمت/موجودی واقعی محصول را ببیند (بدون جعل داده).
 *
 * @return array{post_id: int, title: string, post_type: string, url: string|null, is_product: bool, price: string|null, regular_price: string|null, sale_price: string|null, currency: string|null, stock_quantity: int|null, stock_status: string|null, in_stock: bool|null}
 */
class FetchWooProductInfo
{
    /**
     * @param  int|null  $postId  شناسهٔ پست محصول (پس از انتشار)
     * @param  string|null  $slug  اسلاگ محصول (پیش از انتشار — از canonical_url)
     */
    public function handle(int $siteId, ?int $postId = null, ?string $slug = null): array
    {
        $connection = \DB::table('site_connections')
            ->where('site_id', $siteId)
            ->where('status', 'connected')
            ->firstOrFail();

        $body = json_encode(['post_id' => $postId, 'slug' => $slug], JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $path = '/vision-prime/v1/product-info';
        $signature = hash_hmac('sha256', "POST\n{$path}\n{$timestamp}\n{$nonce}\n".hash('sha256', $body), Crypt::decryptString($connection->secret_ciphertext));

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders([
                'X-VP-Timestamp' => $timestamp,
                'X-VP-Nonce' => $nonce,
                'X-VP-Signature' => $signature,
            ])
            ->withBody($body, 'application/json')
            ->post(rtrim($connection->platform_url, '/').'/wp-json'.$path);

        if (! $response->successful()) {
            throw new \RuntimeException('وردپرس با خطای '.$response->status().' پاسخ داد: '.mb_substr($response->body(), 0, 200));
        }

        $data = $response->json() ?? [];

        return [
            'post_id' => (int) ($data['post_id'] ?? $postId),
            'title' => (string) ($data['title'] ?? ''),
            'post_type' => (string) ($data['post_type'] ?? ''),
            'url' => isset($data['url']) ? (string) $data['url'] : null,
            'is_product' => (bool) ($data['is_product'] ?? false),
            'price' => isset($data['price']) ? (string) $data['price'] : null,
            'regular_price' => isset($data['regular_price']) ? (string) $data['regular_price'] : null,
            'sale_price' => isset($data['sale_price']) ? (string) $data['sale_price'] : null,
            'currency' => isset($data['currency']) ? (string) $data['currency'] : null,
            'stock_quantity' => isset($data['stock_quantity']) ? (int) $data['stock_quantity'] : null,
            'stock_status' => isset($data['stock_status']) ? (string) $data['stock_status'] : null,
            'in_stock' => isset($data['in_stock']) ? (bool) $data['in_stock'] : null,
        ];
    }
}
