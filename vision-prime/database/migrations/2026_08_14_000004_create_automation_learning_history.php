<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_learning_history', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $t->string('command_type');
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('successful')->default(0);
            $t->timestamp('window_start')->nullable();
            $t->timestamp('window_end')->nullable();
            $t->timestamps();
            $t->unique(['site_id', 'command_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_learning_history');
    }
};
