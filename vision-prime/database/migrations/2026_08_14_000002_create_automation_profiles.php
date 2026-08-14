<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_profiles', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('kind')->default('system'); // system | custom
            $t->string('scope')->default('org'); // org | site
            $t->unsignedTinyInteger('automation_level')->default(1); // L0..L4
            $t->string('ai_policy')->default('draft_only'); // disabled | draft_only | approved_templates | bounded_auto
            $t->unsignedTinyInteger('confidence_threshold')->default(80);
            $t->unsignedTinyInteger('high_risk_threshold')->default(90);
            $t->string('risk_tier_max')->default('R1'); // R0..R3 (R4 هرگز)
            $t->json('enabled_content_types')->nullable(); // meta[] | article[] | product[]
            $t->unsignedInteger('daily_command_limit')->default(5);
            $t->unsignedInteger('daily_mutation_limit')->default(2);
            $t->json('execution_window')->nullable(); // { start, end, tz } | blackout
            $t->unsignedInteger('rollback_hours')->default(168);
            $t->boolean('auto_rollback')->default(false);
            $t->string('alert_level')->default('warn'); // none | warn | alert
            $t->string('reviewer_policy')->default('one'); // none | one | specific_roles | named_users
            $t->json('notification_policy')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('site_automation_policies', function (Blueprint $t): void {
            $t->foreignId('active_profile_id')->nullable()->after('level')->constrained('automation_profiles')->nullOnDelete();
            $t->json('overrides_json')->nullable()->after('active_profile_id');
        });

        $now = now()->toDateTimeString();
        $profiles = [
            [
                'name' => 'شروع امن',
                'slug' => 'safe-start',
                'automation_level' => 1,
                'ai_policy' => 'draft_only',
                'confidence_threshold' => 80,
                'high_risk_threshold' => 90,
                'risk_tier_max' => 'R1',
                'enabled_content_types' => json_encode(['meta'], JSON_UNESCAPED_UNICODE),
                'daily_command_limit' => 5,
                'daily_mutation_limit' => 2,
                'execution_window' => null,
                'rollback_hours' => 168,
                'auto_rollback' => false,
                'alert_level' => 'warn',
                'reviewer_policy' => 'one',
                'version' => 1,
            ],
            [
                'name' => 'رشد متعادل',
                'slug' => 'balanced-growth',
                'automation_level' => 2,
                'ai_policy' => 'bounded_auto',
                'confidence_threshold' => 80,
                'high_risk_threshold' => 90,
                'risk_tier_max' => 'R2',
                'enabled_content_types' => json_encode(['meta', 'product'], JSON_UNESCAPED_UNICODE),
                'daily_command_limit' => 10,
                'daily_mutation_limit' => 5,
                'execution_window' => null,
                'rollback_hours' => 168,
                'auto_rollback' => false,
                'alert_level' => 'warn',
                'reviewer_policy' => 'one',
                'version' => 1,
            ],
            [
                'name' => 'خودکار نظارت‌شده',
                'slug' => 'supervised-auto',
                'automation_level' => 3,
                'ai_policy' => 'bounded_auto',
                'confidence_threshold' => 80,
                'high_risk_threshold' => 90,
                'risk_tier_max' => 'R2',
                'enabled_content_types' => json_encode(['meta', 'product', 'article'], JSON_UNESCAPED_UNICODE),
                'daily_command_limit' => 25,
                'daily_mutation_limit' => 10,
                'execution_window' => null,
                'rollback_hours' => 336,
                'auto_rollback' => true,
                'alert_level' => 'alert',
                'reviewer_policy' => 'one',
                'version' => 1,
            ],
            [
                'name' => 'Autopilot محدود',
                'slug' => 'limited-autopilot',
                'automation_level' => 4,
                'ai_policy' => 'bounded_auto',
                'confidence_threshold' => 85,
                'high_risk_threshold' => 92,
                'risk_tier_max' => 'R2',
                'enabled_content_types' => json_encode(['meta', 'product', 'article'], JSON_UNESCAPED_UNICODE),
                'daily_command_limit' => 50,
                'daily_mutation_limit' => 20,
                'execution_window' => null,
                'rollback_hours' => 336,
                'auto_rollback' => true,
                'alert_level' => 'alert',
                'reviewer_policy' => 'specific_roles',
                'version' => 1,
            ],
        ];

        foreach ($profiles as $profile) {
            $profile['kind'] = 'system';
            $profile['scope'] = 'org';
            $profile['created_at'] = $now;
            $profile['updated_at'] = $now;
            DB::table('automation_profiles')->insert($profile);
        }
    }

    public function down(): void
    {
        Schema::table('site_automation_policies', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('active_profile_id');
            $t->dropColumn('overrides_json');
        });
        Schema::dropIfExists('automation_profiles');
    }
};
