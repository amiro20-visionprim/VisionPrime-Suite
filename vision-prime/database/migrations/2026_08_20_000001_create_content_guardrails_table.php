<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_guardrails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('content_type', 20)->default('article'); // article, product
            $table->string('subtype', 50)->default('general');      // how_to, review, pillar, etc.

            // Guardrails
            $table->integer('max_characters')->default(8000);
            $table->integer('min_words')->default(400);
            $table->integer('max_words')->default(2000);
            $table->string('allowed_tone', 100)->default('informative');
            $table->json('allowed_tags')->nullable();        // ["h2","h3","p","ul","table"]
            $table->boolean('require_cta')->default(true);
            $table->boolean('require_faq')->default(false);
            $table->boolean('require_internal_links')->default(true);
            $table->integer('min_internal_links')->default(2);
            $table->boolean('require_brand_mention')->default(true);
            $table->json('forbidden_words')->nullable();     // ["word1","word2"]

            // Prompts (admin-customizable)
            $table->text('system_prompt')->nullable();       // Override system prompt
            $table->text('user_prompt_template')->nullable(); // Override user prompt template

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['organization_id', 'site_id', 'content_type', 'subtype'], 'guardrail_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_guardrails');
    }
};
