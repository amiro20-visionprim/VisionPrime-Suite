<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('company')->nullable();
            $t->string('website')->nullable();
            $t->text('message')->nullable();
            $t->string('source')->default('demo');
            $t->string('status')->default('new');
            $t->json('metadata')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
