<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_questions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('subject_type'); // command | review
            $t->unsignedBigInteger('subject_id');
            $t->foreignId('asked_by_id')->constrained('users')->cascadeOnDelete();
            $t->text('question');
            $t->string('status')->default('open'); // open | answered
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_questions');
    }
};
