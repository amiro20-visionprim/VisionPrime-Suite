<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_connections', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('status')->default('unpaired')->index();
            $t->string('platform_url', 2048)->nullable();
            $t->string('plugin_version', 50)->nullable();
            $t->text('secret_ciphertext')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->json('health')->nullable();
            $t->timestamps();
        });
        Schema::create('pairing_tokens', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 128)->unique();
            $t->timestamp('expires_at')->index();
            $t->timestamp('consumed_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('connector_nonces', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_connection_id')->constrained()->cascadeOnDelete();
            $t->string('nonce', 128);
            $t->timestamp('expires_at')->index();
            $t->timestamp('used_at')->nullable();
            $t->unique(['site_connection_id', 'nonce']);
        });
        Schema::create('connector_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('type')->index();
            $t->json('payload_redacted')->nullable();
            $t->timestamp('occurred_at')->index();
        });
        Schema::create('connector_sync_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('status')->index();
            $t->string('type');
            $t->json('summary')->nullable();
            $t->json('error')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_sync_logs');
        Schema::dropIfExists('connector_events');
        Schema::dropIfExists('connector_nonces');
        Schema::dropIfExists('pairing_tokens');
        Schema::dropIfExists('site_connections');
    }
};
