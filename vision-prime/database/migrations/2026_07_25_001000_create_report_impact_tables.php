<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('source_type');
            $t->unsignedBigInteger('source_id')->nullable();
            $t->json('baseline')->nullable();
            $t->json('outcome')->nullable();
            $t->text('attribution_note')->nullable();
            $t->timestamp('observed_at');
            $t->timestamps();
        });
        Schema::create('reports', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status')->default('draft');
            $t->json('content')->nullable();
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('impact_events');
    }
};
