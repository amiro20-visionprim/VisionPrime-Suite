<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('provider');
            $t->text('encrypted_config');
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['organization_id', 'provider']);
        });
        Schema::create('ai_prompt_templates', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $t->string('key');
            $t->unsignedInteger('version')->default(1);
            $t->json('input_schema')->nullable();
            $t->longText('template');
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['organization_id', 'key', 'version']);
        });
        Schema::create('ai_generations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('template_id')->nullable()->constrained('ai_prompt_templates')->nullOnDelete();
            $t->json('input_redacted')->nullable();
            $t->string('output_status')->default('draft');
            $t->unsignedBigInteger('current_version_id')->nullable();
            $t->json('usage')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_generation_versions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('generation_id')->constrained('ai_generations')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('output');
            $t->json('model_meta')->nullable();
            $t->string('status')->default('draft');
            $t->timestamps();
            $t->unique(['generation_id', 'version']);
        });
        Schema::create('ai_usage_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();
            $t->string('provider');
            $t->string('model')->nullable();
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->decimal('cost', 12, 6)->nullable();
            $t->timestamp('occurred_at');
        });
        Schema::create('review_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('status')->default('pending_review');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('due_at')->nullable();
            $t->json('policy_snapshot')->nullable();
            $t->timestamps();
        });
        Schema::create('review_decisions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('review_item_id')->constrained()->cascadeOnDelete();
            $t->string('decision');
            $t->text('note')->nullable();
            $t->foreignId('decided_by')->constrained('users')->cascadeOnDelete();
            $t->timestamp('decided_at');
        });
    }

    public function down(): void
    {
        foreach (['review_decisions', 'review_items', 'ai_usage_logs', 'ai_generation_versions', 'ai_generations', 'ai_prompt_templates', 'ai_provider_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
