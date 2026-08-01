<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_user_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('portal_role')->default('viewer');
            $table->timestamps();

            $table->unique(['client_id', 'user_id']);
            $table->index(['user_id', 'portal_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_user_assignments');
    }
};
