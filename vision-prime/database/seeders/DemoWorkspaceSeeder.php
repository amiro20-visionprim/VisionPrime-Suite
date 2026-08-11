<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(['slug' => 'vision-prime-demo'], ['public_id' => (string) Str::ulid(), 'name' => 'Vision Prime Demo Agency', 'status' => 'active']);
        $user = User::firstOrCreate(['email' => 'demo@visionprime.test'], ['name' => 'Demo Admin', 'password' => Hash::make('DemoAdmin2024!Secure#')]);
        $role = \DB::table('roles')->where('key', 'agency-admin')->value('id');
        if ($role) {
            \DB::table('memberships')->updateOrInsert(['organization_id' => $org->id, 'user_id' => $user->id], ['role_id' => $role, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]);
        }$client = Client::firstOrCreate(['organization_id' => $org->id, 'name' => 'نمونه کلینیک آفتاب'], ['public_id' => (string) Str::ulid(), 'status' => 'active']);
        $project = Project::firstOrCreate(['organization_id' => $org->id, 'client_id' => $client->id, 'name' => 'رشد ارگانیک'], ['public_id' => (string) Str::ulid(), 'status' => 'active']);
        $site = Site::firstOrCreate(['organization_id' => $org->id, 'canonical_url' => 'https://demo.example.ir'], ['project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'سایت نمونه', 'status' => 'active']);

        $this->seedDemoIntelligence($site);
        $this->seedMarketingUser($org);
        $this->seedDemoLeads();
    }

    private function seedMarketingUser(Organization $org): void
    {
        $marketingUser = User::firstOrCreate(['email' => 'marketing@visionprime.test'], ['name' => 'Marketing Manager', 'password' => Hash::make('Marketing2026!Secure#')]);
        $role = \DB::table('roles')->where('key', 'marketing-manager')->value('id');
        if ($role) {
            \DB::table('memberships')->updateOrInsert(['organization_id' => $org->id, 'user_id' => $marketingUser->id], ['role_id' => $role, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    private function seedDemoLeads(): void
    {
        $now = now();
        $leads = [
            [
                'email' => 'demo.lead1@example.ir',
                'name' => 'آژانس رشد نوین',
                'company' => 'آژانس رشد نوین',
                'website' => 'https://roshdnovin.ir',
                'message' => '۵ سایت مشتری داریم؛ دنبال وایتدلیبل برای پرتال مشتری هستیم.',
                'source' => 'demo',
                'status' => 'qualified',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'launch_1405',
                'landing_page' => '/pricing',
                'created_at' => $now->copy()->subDays(6),
            ],
            [
                'email' => 'demo.lead2@example.ir',
                'name' => 'فروشگاه دیجی استایل',
                'company' => 'دیجی استایل',
                'website' => 'https://digistyle.ir',
                'message' => 'می‌خواهیم کنترل تغییرات روی سایت وردپرسیمان داشته باشیم.',
                'source' => 'demo',
                'status' => 'contacted',
                'utm_source' => 'instagram',
                'utm_medium' => 'social',
                'utm_campaign' => 'brand_awareness',
                'landing_page' => '/demo',
                'created_at' => $now->copy()->subDays(4),
            ],
            [
                'email' => 'demo.lead3@example.ir',
                'name' => 'کلینیک زیبایی آرتا',
                'company' => 'کلینیک آرتا',
                'website' => 'https://arta-clinic.ir',
                'message' => 'سلامت سایت و گزارش ماهانه برای کارفرما مهم است.',
                'source' => 'demo',
                'status' => 'new',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'seo_services_1405',
                'utm_term' => 'مدیریت سئو',
                'landing_page' => '/',
                'created_at' => $now->copy()->subHours(20),
            ],
            [
                'email' => 'demo.lead4@example.ir',
                'name' => 'آموزشگاه زبان پارسیان',
                'company' => 'پارسیان',
                'website' => 'https://parsian-lang.ir',
                'message' => 'چند سایت آموزشی داریم؛ می‌خواهیم فرصت‌ها را ببینیم.',
                'source' => 'demo',
                'status' => 'new',
                'utm_source' => 'sms',
                'utm_medium' => 'cpm',
                'utm_campaign' => 'sms_summer_1405',
                'landing_page' => '/demo',
                'created_at' => $now->copy()->subHours(6),
            ],
            [
                'email' => 'demo.lead5@example.ir',
                'name' => 'رضا کریمی',
                'company' => null,
                'website' => null,
                'message' => 'در اتصال پلاگین وردپرس مشکل دارم، لطفاً راهنمایی کنید.',
                'source' => 'support',
                'status' => 'contacted',
                'contact' => '09121234567',
                'created_at' => $now->copy()->subDays(2),
            ],
            [
                'email' => 'demo.lead6@example.ir',
                'name' => 'آژانس تبلیغات مهر',
                'company' => 'آژانس مهر',
                'website' => 'https://mehrads.ir',
                'message' => 'قیمت پلن آژانس چقدر است؟ امکان برند اختصاصی داریم؟',
                'source' => 'demo',
                'status' => 'new',
                'utm_source' => 'telegram',
                'utm_medium' => 'social',
                'utm_campaign' => 'agency_offer',
                'landing_page' => '/pricing',
                'created_at' => $now->copy()->subDay(),
            ],
            [
                'email' => 'demo.lead7@example.ir',
                'name' => 'هتل آسمان',
                'company' => 'هتل آسمان',
                'website' => 'https://asemanhotel.ir',
                'message' => '۱ سایت داریم؛ پلن پایه برای شروع مناسب است؟',
                'source' => 'demo',
                'status' => 'unqualified',
                'utm_source' => 'google',
                'utm_medium' => 'organic',
                'utm_campaign' => null,
                'landing_page' => '/pricing',
                'created_at' => $now->copy()->subDays(9),
            ],
            [
                'email' => 'demo.lead8@example.ir',
                'name' => 'سارا محمدی',
                'company' => 'استارتاپ بوم',
                'website' => 'https://boomapp.ir',
                'message' => 'سؤال درباره امنیت داده‌ها و محل استقرار.',
                'source' => 'support',
                'status' => 'new',
                'contact' => 'sara@boomapp.ir',
                'created_at' => $now->copy()->subHours(3),
            ],
        ];

        foreach ($leads as $attributes) {
            $contact = $attributes['contact'] ?? null;
            $metadata = [
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'device' => 'desktop',
                'locale' => 'fa',
            ];
            if ($contact !== null) {
                $metadata['contact'] = $contact;
            }
            unset($attributes['contact']);
            $attributes['metadata'] = json_encode($metadata, JSON_UNESCAPED_UNICODE);

            $lead = \DB::table('leads')->where('email', $attributes['email'])->first();
            if ($lead === null) {
                $leadId = \DB::table('leads')->insertGetId($attributes);
            } else {
                $leadId = $lead->id;
            }

            if ($attributes['email'] === 'demo.lead1@example.ir' && ! \DB::table('lead_notes')->where('lead_id', $leadId)->exists()) {
                \DB::table('lead_notes')->insert([
                    ['lead_id' => $leadId, 'user_id' => null, 'body' => 'پیشنهاد: دموی اختصاصی با تمرکز روی وایتدلیبل و پرتال مشتری هماهنگ شود.', 'created_at' => $now->copy()->subDays(5), 'updated_at' => $now->copy()->subDays(5)],
                    ['lead_id' => $leadId, 'user_id' => null, 'body' => 'نکته: بودجهٔ تخمینی مشتری بالای ۱۰ میلیون است — پلن آژانس را پیشنهاد دهیم.', 'created_at' => $now->copy()->subDays(4), 'updated_at' => $now->copy()->subDays(4)],
                ]);
            }
        }
    }

    private function seedDemoIntelligence(Site $site): void
    {
        if (\DB::table('url_profiles')->where('site_id', $site->id)->exists()) {
            return;
        }

        $now = now();
        $profiles = [
            ['slug' => 'services/seo', 'content_type' => 'page', 'canonical_url' => 'https://demo.example.ir/services/seo'],
            ['slug' => 'services/ads', 'content_type' => 'page', 'canonical_url' => 'https://demo.example.ir/services/ads'],
            ['slug' => 'blog/seo-guide', 'content_type' => 'post', 'canonical_url' => 'https://demo.example.ir/blog/seo-guide'],
            ['slug' => 'blog/pricing-guide', 'content_type' => 'post', 'canonical_url' => 'https://demo.example.ir/blog/pricing-guide'],
        ];

        $profileIds = [];
        foreach ($profiles as $index => $profile) {
            $profileIds[$index] = \DB::table('url_profiles')->insertGetId([
                'site_id' => $site->id,
                'public_id' => (string) Str::ulid(),
                'canonical_url' => $profile['canonical_url'],
                'slug' => $profile['slug'],
                'content_type' => $profile['content_type'],
                'post_status' => 'publish',
                'last_synced_at' => $now->copy()->subDays(2),
                'created_at' => $now->copy()->subDays(12),
                'updated_at' => $now->copy()->subDays(2),
            ]);
        }

        $scores = [55, 72, 64, 81];
        foreach ($profileIds as $index => $profileId) {
            $auditId = \DB::table('money_page_audits')->insertGetId([
                'url_profile_id' => $profileId,
                'score' => $scores[$index],
                'summary' => json_encode(['word_count' => [420, 780, 310, 950][$index]]),
                'audited_at' => $now->copy()->subDay(),
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now->copy()->subDay(),
            ]);

            if ($scores[$index] < 70) {
                $issues = [
                    ['key' => 'missing_meta_title', 'severity' => 'high', 'explanation' => 'عنوان متا برای صفحه تعریف نشده است.'],
                    ['key' => 'thin_content', 'severity' => 'high', 'explanation' => 'محتوای صفحه برای یک صفحه تجاری بیش از حد کوتاه است.'],
                    ['key' => 'missing_meta_description', 'severity' => 'medium', 'explanation' => 'توضیحات متا برای صفحه تعریف نشده است.'],
                ];
                foreach ($issues as $issue) {
                    \DB::table('money_page_issues')->insert([
                        'money_page_audit_id' => $auditId,
                        'key' => $issue['key'],
                        'severity' => $issue['severity'],
                        'explanation' => $issue['explanation'],
                        'created_at' => $now->copy()->subDay(),
                        'updated_at' => $now->copy()->subDay(),
                    ]);
                }
            }
        }

        $risks = [
            ['key' => 'thin_content', 'severity' => 'high', 'score' => 75, 'explanation' => 'محتوای صفحه برای تصمیم‌گیری کاربر کافی نیست.'],
            ['key' => 'weak_cta', 'severity' => 'high', 'score' => 68, 'explanation' => 'دعوت به اقدام واضحی در صفحه وجود ندارد.'],
            ['key' => 'unclear_offer', 'severity' => 'medium', 'score' => 42, 'explanation' => 'پیشنهاد اصلی صفحه به‌وضوح بیان نشده است.'],
        ];
        foreach ($risks as $index => $risk) {
            $riskId = \DB::table('conversion_risks')->insertGetId([
                'url_profile_id' => $profileIds[$index],
                'key' => $risk['key'],
                'severity' => $risk['severity'],
                'score' => $risk['score'],
                'explanation' => $risk['explanation'],
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now->copy()->subDay(),
            ]);
            \DB::table('conversion_risk_factors')->insert([
                'conversion_risk_id' => $riskId,
                'key' => $risk['key'],
                'weight' => 1,
                'value' => $risk['score'] / 100,
                'explanation' => $risk['explanation'],
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now->copy()->subDay(),
            ]);
        }

        $opportunities = [
            ['type' => 'conversion_boost', 'score' => 86, 'confidence' => 0.82, 'explanation' => 'صفحه خدمات سئو با کمی بهبود CTA و عمق محتوا می‌تواند تبدیل بیشتری داشته باشد.'],
            ['type' => 'ctr_gap', 'score' => 74, 'confidence' => 0.71, 'explanation' => 'عنوان متا برای عبارت «قیمت سئو سایت» تعریف نشده و نرخ کلیک می‌تواند رشد کند.'],
            ['type' => 'content_gap', 'score' => 66, 'confidence' => 0.64, 'explanation' => 'راهنمای قیمت‌گذاری می‌تواند با افزودن مقایسه و پرسش‌های متداول کامل‌تر شود.'],
        ];
        foreach ($opportunities as $index => $opportunity) {
            \DB::table('opportunities')->insert([
                'site_id' => $site->id,
                'url_profile_id' => $profileIds[$index % count($profileIds)],
                'type' => $opportunity['type'],
                'score' => $opportunity['score'],
                'confidence' => $opportunity['confidence'],
                'status' => 'open',
                'explanation' => $opportunity['explanation'],
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now->copy()->subDay(),
            ]);
        }

        \DB::table('commands')->insert([
            'site_id' => $site->id,
            'source_type' => 'recommendation',
            'source_id' => null,
            'type' => 'update_meta_title',
            'risk_tier' => 'R2',
            'payload' => json_encode(['title' => 'خدمات سئو حرفه‌ای | کلینیک آفتاب']),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending_approval',
            'expires_at' => $now->copy()->addDays(7),
            'policy_version' => 1,
            'created_at' => $now->copy()->subHours(5),
            'updated_at' => $now->copy()->subHours(5),
        ]);

        \DB::table('review_items')->insert([
            'site_id' => $site->id,
            'subject_type' => 'money_page_audit',
            'subject_id' => \DB::table('money_page_audits')->where('url_profile_id', $profileIds[0])->value('id'),
            'status' => 'pending_review',
            'due_at' => $now->copy()->addDays(3),
            'created_at' => $now->copy()->subHours(3),
            'updated_at' => $now->copy()->subHours(3),
        ]);

        \DB::table('recommendations')->insert([
            'site_id' => $site->id,
            'source_type' => 'opportunity',
            'source_id' => null,
            'title' => 'بازنویسی صفحه خدمات سئو با CTA واضح',
            'body' => 'تقویت دعوت به اقدام و افزودن نمونه‌کار برای افزایش تبدیل بازدیدکننده به مشتری.',
            'priority' => 'high',
            'status' => 'active',
            'owner_id' => null,
            'due_at' => $now->copy()->addDays(14),
            'created_at' => $now->copy()->subHours(6),
            'updated_at' => $now->copy()->subHours(6),
        ]);
    }
}
