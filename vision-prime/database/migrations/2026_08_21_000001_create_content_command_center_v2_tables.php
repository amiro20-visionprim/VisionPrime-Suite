<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Module 1: Content Performance
        Schema::create('content_performance_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('url', 2048);
            $t->string('title')->nullable();
            $t->string('content_type')->default('article');
            $t->string('subtype')->nullable();
            $t->date('date');
            $t->unsignedBigInteger('clicks')->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->decimal('ctr', 8, 6)->default(0);
            $t->decimal('position', 8, 3)->nullable();
            $t->decimal('position_change', 8, 3)->nullable();
            $t->unsignedBigInteger('prev_clicks')->nullable();
            $t->unsignedBigInteger('prev_impressions')->nullable();
            $t->decimal('prev_position', 8, 3)->nullable();
            $t->string('source')->default('gsc');
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['site_id', 'date', 'url', 'content_type']);
            $t->index(['organization_id', 'date']);
            $t->index(['site_id', 'content_type', 'date']);
        });

        // Module 2: SERP Intelligence
        Schema::create('serp_analyses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('keyword', 512);
            $t->string('target_url', 2048)->nullable();
            $t->string('country')->default('IR');
            $t->string('language')->default('fa');
            $t->json('results');
            $t->json('people_also_ask')->nullable();
            $t->json('related_queries')->nullable();
            $t->json('featured_snippet')->nullable();
            $t->json('serp_features')->nullable();
            $t->decimal('avg_word_count', 10, 0)->nullable();
            $t->decimal('avg_heading_count', 10, 0)->nullable();
            $t->string('dominant_content_type')->nullable();
            $t->json('competitive_gaps')->nullable();
            $t->json('suggestions')->nullable();
            $t->string('status')->default('completed');
            $t->timestamps();
            $t->index(['site_id', 'keyword']);
            $t->index(['organization_id', 'created_at']);
        });

        // Module 3: Keyword Architecture
        Schema::create('keyword_clusters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('pillar_keyword')->nullable();
            $t->string('cluster_type')->default('topic');
            $t->integer('estimated_volume')->nullable();
            $t->integer('keyword_count')->default(0);
            $t->integer('page_count')->default(0);
            $t->decimal('avg_position', 8, 3)->nullable();
            $t->unsignedBigInteger('total_clicks')->default(0);
            $t->decimal('commercial_value', 5, 2)->default(0);
            $t->json('keywords')->nullable();
            $t->json('content_gaps')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['site_id', 'is_active']);
            $t->index(['organization_id']);
        });

        // Module 4: Smart Content Calendar
        Schema::create('content_calendar_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('keyword_cluster_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->string('content_type')->default('article');
            $t->string('subtype')->nullable();
            $t->string('status')->default('planned');
            $t->date('planned_date');
            $t->time('planned_time')->nullable();
            $t->decimal('priority_score', 5, 2)->default(50);
            $t->decimal('estimated_impact', 5, 2)->nullable();
            $t->string('assigned_to')->nullable();
            $t->json('seo_context')->nullable();
            $t->json('content_brief')->nullable();
            $t->text('notes')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->index(['site_id', 'planned_date', 'status']);
            $t->index(['organization_id', 'planned_date']);
        });

        // Module 5: A/B Experiments
        Schema::create('content_experiments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('experiment_type');
            $t->string('status')->default('draft');
            $t->string('metric')->default('ctr');
            $t->json('variants');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('winner_variant')->nullable();
            $t->decimal('confidence_level', 5, 2)->nullable();
            $t->json('learnings')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['site_id', 'status']);
            $t->index(['organization_id']);
        });

        // Module 6: Smart Suggestions
        Schema::create('smart_content_suggestions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('suggestion_type');
            $t->string('priority')->default('medium');
            $t->string('title');
            $t->text('description');
            $t->json('data')->nullable();
            $t->decimal('estimated_impact', 5, 2)->nullable();
            $t->string('source');
            $t->string('status')->default('pending');
            $t->string('target_url', 2048)->nullable();
            $t->json('action_data')->nullable();
            $t->timestamps();
            $t->index(['site_id', 'status', 'priority']);
            $t->index(['organization_id', 'suggestion_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_content_suggestions');
        Schema::dropIfExists('content_experiments');
        Schema::dropIfExists('content_calendar_items');
        Schema::dropIfExists('keyword_clusters');
        Schema::dropIfExists('serp_analyses');
        Schema::dropIfExists('content_performance_metrics');
    }
};
