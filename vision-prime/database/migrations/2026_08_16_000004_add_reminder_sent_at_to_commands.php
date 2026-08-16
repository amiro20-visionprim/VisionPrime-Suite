<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * یادآوری موعد انتشار — زمان ارسال آخرین یادآوری برای dedupe (هر کامند حداکثر یک‌بار).
     */
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $t): void {
            $t->timestamp('reminder_sent_at')->nullable()->after('scheduled_for');
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $t): void {
            $t->dropColumn('reminder_sent_at');
        });
    }
};
