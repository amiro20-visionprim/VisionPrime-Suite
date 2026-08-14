<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_profile_routes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('profile_id')->constrained('automation_profiles')->cascadeOnDelete();
            $t->string('content_type'); // meta | article | product
            $t->timestamps();
            $t->unique(['site_id', 'content_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_profile_routes');
    }
};
