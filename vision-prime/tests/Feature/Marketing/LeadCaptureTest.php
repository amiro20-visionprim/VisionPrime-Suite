<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_lead_is_stored_with_attribution_data(): void
    {
        $response = $this
            ->withHeaders([
                'referer' => 'https://google.com/',
                'user-agent' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36',
            ])
            ->post('/demo', [
                'name' => 'سارا محمدی',
                'email' => 'sara@agency.ir',
                'company' => 'آژانس رشد',
                'website' => 'https://example.ir',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'seo_1405',
                'utm_term' => 'مدیریت سئو',
                'utm_content' => 'pricing-banner',
                'landing_page' => '/pricing',
            ]);

        $response->assertStatus(302);

        $lead = Lead::query()->first();

        $this->assertNotNull($lead);
        $this->assertSame('سارا محمدی', $lead->name);
        $this->assertSame('demo', $lead->source);
        $this->assertSame('new', $lead->status);
        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('cpc', $lead->utm_medium);
        $this->assertSame('seo_1405', $lead->utm_campaign);
        $this->assertSame('مدیریت سئو', $lead->utm_term);
        $this->assertSame('pricing-banner', $lead->utm_content);
        $this->assertSame('/pricing', $lead->landing_page);
        $this->assertSame('https://google.com/', $lead->referrer);
        $this->assertSame('mobile', $lead->metadata['device']);
        $this->assertNotNull($lead->metadata['user_agent']);
    }

    public function test_lead_without_utm_is_stored_with_null_attribution(): void
    {
        $this->from('/demo')->post('/demo', [
            'name' => 'علی رضایی',
            'email' => 'ali@site.ir',
        ])->assertRedirect('/demo');

        $lead = Lead::query()->first();

        $this->assertNotNull($lead);
        $this->assertNull($lead->utm_source);
        $this->assertNull($lead->utm_medium);
        $this->assertNull($lead->landing_page);
        $this->assertNull($lead->referrer);
        $this->assertSame('desktop', $lead->metadata['device']);
    }

    public function test_demo_lead_requires_name_and_email(): void
    {
        $this->from('/demo')->post('/demo', [
            'website' => 'not-a-url',
        ])->assertSessionHasErrors(['name', 'email', 'website']);

        $this->assertSame(0, Lead::query()->count());
    }
}
