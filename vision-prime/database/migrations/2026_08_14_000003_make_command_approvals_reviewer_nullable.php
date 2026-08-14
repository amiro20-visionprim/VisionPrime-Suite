<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('command_approvals', function (Blueprint $t): void {
            $t->unsignedBigInteger('reviewer_id')->nullable()->change();
            $t->string('reviewer_type')->default('user')->after('reviewer_id'); // user | system
        });
    }

    public function down(): void
    {
        Schema::table('command_approvals', function (Blueprint $t): void {
            $t->dropColumn('reviewer_type');
            $t->unsignedBigInteger('reviewer_id')->nullable(false)->change();
        });
    }
};
