<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_automation_policies', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('level')->default(1);
            $t->json('rules')->nullable();
            $t->timestamp('emergency_stopped_at')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('commands', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('source_type');
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('type');
            $t->string('risk_tier');
            $t->json('payload');
            $t->string('idempotency_key')->unique();
            $t->string('status')->default('draft');
            $t->timestamp('expires_at');
            $t->unsignedInteger('policy_version')->default(1);
            $t->timestamps();
        });
        Schema::create('command_approvals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('command_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $t->string('decision');
            $t->text('note')->nullable();
            $t->json('policy_snapshot')->nullable();
            $t->timestamps();
        });
        Schema::create('command_execution_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('command_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('attempt')->default(1);
            $t->string('status');
            $t->json('request_redacted')->nullable();
            $t->json('response_redacted')->nullable();
            $t->timestamp('executed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('rollback_snapshots', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('command_id')->constrained()->cascadeOnDelete();
            $t->string('target_ref');
            $t->text('snapshot_ciphertext');
            $t->timestamp('expires_at')->nullable();
            $t->string('status')->default('available');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['rollback_snapshots', 'command_execution_logs', 'command_approvals', 'commands', 'site_automation_policies'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
