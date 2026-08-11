<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t): void {
            $t->string('utm_source')->nullable()->after('status');
            $t->string('utm_medium')->nullable()->after('utm_source');
            $t->string('utm_campaign')->nullable()->after('utm_medium');
            $t->string('utm_term')->nullable()->after('utm_campaign');
            $t->string('utm_content')->nullable()->after('utm_term');
            $t->string('landing_page')->nullable()->after('utm_content');
            $t->string('referrer')->nullable()->after('landing_page');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $t): void {
            $t->dropColumn([
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'landing_page',
                'referrer',
            ]);
        });
    }
};
