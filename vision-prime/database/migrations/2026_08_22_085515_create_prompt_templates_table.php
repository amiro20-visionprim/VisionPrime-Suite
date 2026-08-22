<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('content_type', 50)->default('article');
            $table->string('subtype', 50)->nullable();
            $table->string('tone', 50)->nullable();
            $table->longText('system_prompt')->nullable();
            $table->longText('user_prompt_template');
            $table->unsignedInteger('usage_count')->default(0);
            $table->decimal('avg_quality_score', 5, 2)->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_user_created')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            
            $table->index(['content_type', 'is_active']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};