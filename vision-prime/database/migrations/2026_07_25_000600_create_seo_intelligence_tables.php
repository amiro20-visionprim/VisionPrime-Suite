<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_insights', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('query_normalized', 2048);
            $t->foreignId('mapped_url_profile_id')->nullable()->constrained('url_profiles')->nullOnDelete();
            $t->json('latest_metrics')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['site_id', 'query_normalized']);
        });
        Schema::create('intent_classifications', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('keyword_insight_id')->constrained()->cascadeOnDelete();
            $t->string('intent');
            $t->decimal('confidence', 5, 4);
            $t->string('method');
            $t->text('explanation');
            $t->string('rules_version');
            $t->timestamps();
        });
        Schema::create('opportunities', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('url_profile_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('keyword_insight_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type');
            $t->decimal('score', 8, 3);
            $t->decimal('confidence', 5, 4);
            $t->string('status')->default('open');
            $t->text('explanation');
            $t->timestamps();
        });
        Schema::create('opportunity_factors', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->decimal('weight', 8, 4);
            $t->decimal('raw_value', 12, 4)->nullable();
            $t->decimal('normalized_value', 8, 4)->nullable();
            $t->text('explanation');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_factors');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('intent_classifications');
        Schema::dropIfExists('keyword_insights');
    }
};
