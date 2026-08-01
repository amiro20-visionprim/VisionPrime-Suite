<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_page_audits', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('url_profile_id')->constrained()->cascadeOnDelete();
            $t->decimal('score', 8, 3);
            $t->json('summary')->nullable();
            $t->timestamp('audited_at');
            $t->timestamps();
        });
        Schema::create('money_page_issues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('money_page_audit_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('severity');
            $t->text('explanation');
            $t->timestamps();
        });
        Schema::create('conversion_risks', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('url_profile_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('severity');
            $t->decimal('score', 8, 3);
            $t->text('explanation');
            $t->timestamps();
        });
        Schema::create('conversion_risk_factors', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('conversion_risk_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->decimal('weight', 8, 4);
            $t->decimal('value', 8, 4)->nullable();
            $t->text('explanation');
            $t->timestamps();
        });
        Schema::create('recommendations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('source_type');
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('title');
            $t->text('body');
            $t->string('priority');
            $t->string('status')->default('draft');
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('due_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['recommendations', 'conversion_risk_factors', 'conversion_risks', 'money_page_issues', 'money_page_audits'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
