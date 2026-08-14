<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $t): void {
            $t->unsignedTinyInteger('confidence_score')->nullable()->after('risk_tier');
            $t->json('confidence_factors')->nullable()->after('confidence_score');
            $t->string('decision_source')->nullable()->after('status'); // policy | manual
            $t->timestamp('published_at')->nullable()->after('decision_source');
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $t): void {
            $t->dropColumn(['confidence_score', 'confidence_factors', 'decision_source', 'published_at']);
        });
    }
};
