<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تقویم محتوایی — زمان‌بندی از لحظهٔ ساخت پیش‌نویس.
     *
     * ai_generations.scheduled_for = موعد انتشار دلخواه که هنگام ساخت پیش‌نویس از داخل
     * تقویم ثبت می‌شود؛ وقتی بازبین پیش‌نویس را تأیید کند، DecideReviewItem به‌جای
     * انتشار فوری، کامند را با همین موعد زمان‌بندی می‌کند.
     */
    public function up(): void
    {
        Schema::table('ai_generations', function (Blueprint $t): void {
            $t->timestamp('scheduled_for')->nullable()->after('output_status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', function (Blueprint $t): void {
            $t->dropColumn('scheduled_for');
        });
    }
};
