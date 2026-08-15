<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * پروفایل‌های سیستمی (kind=system) بدون سازمان هستند و برای همه در دسترس‌اند؛
     * پروفایل‌های سفارشی (kind=custom) متعلق به سازمان‌سازنده‌اند و فقط همان سازمان
     * می‌تواند آن‌ها را ببیند/انتخاب کند — جلوگیری از نشت پیکربندی اتوماسیون بین‌سازمانی.
     */
    public function up(): void
    {
        Schema::table('automation_profiles', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('automation_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
