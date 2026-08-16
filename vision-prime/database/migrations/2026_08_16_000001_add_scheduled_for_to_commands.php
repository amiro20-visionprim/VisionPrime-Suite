<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تقویم محتوایی — زمان‌بندی انتشار پیش‌نویس مقاله/محصول.
     *
     * commands.scheduled_for = تاریخ/ساعت موعد انتشار برای کامندهای publish_new_article
     * که با status=scheduled در تقویم می‌نشینند و job آزادسازی (ReleaseScheduledCommands)
     * در لحظهٔ موعد آن‌ها را دوباره از AutoPublish عبور می‌دهد.
     */
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $t): void {
            $t->timestamp('scheduled_for')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $t): void {
            $t->dropColumn('scheduled_for');
        });
    }
};
