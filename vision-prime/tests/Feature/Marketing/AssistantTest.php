<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_endpoint_returns_questions_and_version(): void
    {
        $response = $this->getJson('/assistant/knowledge');

        $response->assertOk()
            ->assertJsonStructure([
                'version',
                'updated_at',
                'questions' => [
                    '*' => ['id', 'category', 'question'],
                ],
            ]);

        $this->assertNotEmpty($response->json('questions'));
        $this->assertSame(config('assistant.version'), $response->json('version'));
    }

    public function test_chat_matches_pricing_question(): void
    {
        $response = $this->postJson('/assistant/chat', [
            'message' => 'پلن حرفه‌ای چقدر هزینه دارد؟ قیمت چی است؟',
        ]);

        $response->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('category', 'قیمت‌گذاری')
            ->assertJsonStructure(['answer', 'links' => [['label', 'href']]]);

        $this->assertStringContainsString('۶٬۹۰۰٬۰۰۰', $response->json('answer'));
    }

    public function test_chat_matches_support_question(): void
    {
        $response = $this->postJson('/assistant/chat', [
            'message' => 'چطور می‌توانم با پشتیبانی تماس بگیرم؟',
        ]);

        $response->assertOk()->assertJsonPath('matched', true);
        $this->assertStringContainsString('۰۹۰۲۴۱۵۱۶۳۰', $response->json('answer'));
    }

    public function test_chat_matches_content_calendar_catalog_entry(): void
    {
        $response = $this->postJson('/assistant/chat', [
            'message' => 'تقویم محتوایی چطور کار می‌کند؟',
        ]);

        $response->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('category', 'محصول');

        $this->assertStringContainsString('تقویم محتوایی', $response->json('answer'));
    }

    public function test_chat_matches_training_center_entry(): void
    {
        $response = $this->postJson('/assistant/chat', [
            'message' => 'مرکز آموزش کجاست؟ می‌خواهم یاد بگیرم',
        ]);

        $response->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('category', 'پشتیبانی');

        $this->assertStringContainsString('/app/training', $response->json('links')[0]['href'] ?? '');
    }

    public function test_chat_falls_back_for_unknown_message(): void
    {
        $response = $this->postJson('/assistant/chat', [
            'message' => 'xyzzy qwerty',
        ]);

        $response->assertOk()
            ->assertJsonPath('matched', false)
            ->assertJsonStructure(['answer', 'links', 'suggestions']);
    }

    public function test_chat_requires_message(): void
    {
        $this->postJson('/assistant/chat', [])->assertUnprocessable();
    }

    public function test_support_contact_stores_lead(): void
    {
        $response = $this->postJson('/assistant/contact', [
            'name' => 'رضا کریمی',
            'contact' => '09121234567',
            'message' => 'در اتصال وردپرس مشکل دارم',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $lead = Lead::query()->first();
        $this->assertNotNull($lead);
        $this->assertSame('support', $lead->source);
        $this->assertSame('09121234567', $lead->metadata['contact']);
        $this->assertNull($lead->email);
    }

    public function test_support_contact_requires_fields(): void
    {
        $this->postJson('/assistant/contact', [
            'name' => '',
        ])->assertUnprocessable();

        $this->assertSame(0, Lead::query()->count());
    }
}
