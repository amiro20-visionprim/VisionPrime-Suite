<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * متریک‌های ساعتی سرچ کنسول (تقویم محتوایی — پیشنهاد هوشمند ساعت).
     *
     * دادهٔ `date × hour` در سطح property (نه per-page) تا حجم محدود بماند؛
     * منبع پیشنهاد «بهترین ساعت انتشار» از دادهٔ واقعی GSC (D-019/D-020).
     */
    public function up(): void
    {
        Schema::create('gsc_hourly_metrics', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('gsc_property_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->unsignedTinyInteger('hour');
            $t->unsignedBigInteger('clicks')->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->float('ctr')->nullable();
            $t->float('position')->nullable();
            $t->timestamps();

            $t->unique(['gsc_property_id', 'date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_hourly_metrics');
    }
};
