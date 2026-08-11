<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domains\Marketing\Actions\ScoreLead;
use App\Domains\Marketing\Services\NotifyMarketingTeam;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    /**
     * Return the current knowledge base (questions + categories + version).
     * The widget renders fresh suggestions on every open, so the assistant
     * stays in sync with the latest product updates.
     */
    public function knowledge(): JsonResponse
    {
        $entries = config('assistant.entries', []);

        $questions = collect($entries)->map(fn (array $entry): array => [
            'id' => $entry['id'],
            'category' => $entry['category'],
            'question' => $entry['question'],
        ])->values()->all();

        return response()->json([
            'version' => config('assistant.version', '1.0.0'),
            'updated_at' => collect($entries)->max('updated_at'),
            'questions' => $questions,
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $normalized = $this->normalize($data['message']);
        $best = null;
        $bestScore = 0;

        foreach (config('assistant.entries', []) as $entry) {
            $score = $this->score($normalized, $entry['keywords']);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entry;
            }
        }

        if ($best === null || $bestScore < 1) {
            return response()->json([
                'matched' => false,
                'answer' => 'هنوز دقیق متوجه سوالت نشدم 😊. می‌توانی دربارهٔ معرفی محصول، قیمت‌گذاری، دمو، امنیت، پرتال مشتری، گردش‌کار تأیید یا پشتیبانی بپرسی. اگر سؤال خاصی داری، تیم ما در کمتر از ۲۴ ساعت کاری پاسخ می‌دهد.',
                'category' => 'عمومی',
                'question' => null,
                'links' => [
                    ['label' => 'صفحهٔ تماس', 'href' => '/contact'],
                    ['label' => 'درخواست دمو', 'href' => '/demo'],
                ],
                'suggestions' => $this->suggestions($bestScore),
            ]);
        }

        return response()->json([
            'matched' => true,
            'answer' => $best['answer'],
            'category' => $best['category'],
            'question' => $best['question'],
            'links' => $best['links'] ?? [],
            'suggestions' => $this->suggestions($best['id']),
        ]);
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $lead = Lead::query()->create([
            'name' => $data['name'],
            'email' => str_contains($data['contact'], '@') ? $data['contact'] : null,
            'message' => $data['message'],
            'source' => 'support',
            'status' => 'new',
            'metadata' => [
                'contact' => $data['contact'],
                'user_agent' => $request->userAgent(),
                'device' => $this->detectDevice($request->userAgent()),
            ],
        ]);

        app(ScoreLead::class)->handle($lead);
        app(NotifyMarketingTeam::class)->handle($lead);

        return response()->json([
            'ok' => true,
            'message' => 'پیام شما به تیم پشتیبانی رسید؛ در کمتر از ۲۴ ساعت کاری پاسخ می‌گیرید.',
        ]);
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function score(string $message, array $keywords): int
    {
        $score = 0;

        foreach ($keywords as $keyword) {
            $keyword = $this->normalize($keyword);

            if ($keyword === '') {
                continue;
            }

            if (str_contains($keyword, ' ')) {
                if (str_contains($message, $keyword)) {
                    $score += 3;
                }
            } elseif (str_contains($message, $keyword)) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function suggestions(mixed $except): array
    {
        $others = collect(config('assistant.entries', []))
            ->filter(fn (array $entry): bool => $entry['id'] !== $except)
            ->values();

        return $others
            ->shuffle()
            ->take(3)
            ->map(fn (array $entry): array => [
                'id' => $entry['id'],
                'question' => $entry['question'],
            ])
            ->all();
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['ي', 'ك', 'ة', 'أ', 'إ', 'آ'], ['ی', 'ک', 'ه', 'ا', 'ا', 'ا'], $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function detectDevice(?string $userAgent): string
    {
        if ($userAgent === null) {
            return 'unknown';
        }

        if (stripos($userAgent, 'mobile') !== false || stripos($userAgent, 'android') !== false || stripos($userAgent, 'iphone') !== false) {
            return 'mobile';
        }

        return 'desktop';
    }
}
