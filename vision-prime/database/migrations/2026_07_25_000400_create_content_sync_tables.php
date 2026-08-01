<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('content');
            $t->string('status')->default('queued')->index();
            $t->string('cursor')->nullable();
            $t->json('summary')->nullable();
            $t->json('error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sync_run_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('sync_run_id')->constrained()->cascadeOnDelete();
            $t->string('external_id');
            $t->string('url', 2048)->nullable();
            $t->string('status')->index();
            $t->string('action')->nullable();
            $t->json('error')->nullable();
            $t->timestamps();
            $t->unique(['sync_run_id', 'external_id']);
        });
        Schema::create('url_profiles', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->ulid('public_id')->unique();
            $t->string('external_content_id')->nullable();
            $t->string('canonical_url', 2048);
            $t->string('slug')->nullable();
            $t->string('content_type');
            $t->string('post_status');
            $t->json('metadata')->nullable();
            $t->string('current_hash', 64)->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
            $t->unique(['site_id', 'canonical_url']);
        });
        Schema::create('content_snapshots', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('url_profile_id')->constrained()->cascadeOnDelete();
            $t->string('content_hash', 64);
            $t->string('title')->nullable();
            $t->json('meta')->nullable();
            $t->json('headings')->nullable();
            $t->longText('content')->nullable();
            $t->unsignedInteger('word_count')->default(0);
            $t->timestamp('captured_at')->index();
            $t->unique(['url_profile_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_snapshots');
        Schema::dropIfExists('url_profiles');
        Schema::dropIfExists('sync_run_items');
        Schema::dropIfExists('sync_runs');
    }
};
