<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('slug', 250)->nullable();
            $table->longText('content');
            $table->string('meta_title', 100)->nullable();
            $table->text('meta_description')->nullable();
            $table->json('schemas')->nullable();
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->string('subtype', 50)->default('article');
            $table->string('model_used', 100)->nullable();
            $table->enum('status', ['draft', 'review', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_drafts');
    }
};
