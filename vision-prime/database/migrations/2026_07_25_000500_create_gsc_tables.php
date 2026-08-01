<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_accounts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('google_subject')->index();
            $t->string('email');
            $t->text('token_ciphertext');
            $t->timestamp('token_expires_at')->nullable();
            $t->string('status')->default('connected');
            $t->timestamps();
            $t->unique(['organization_id', 'google_subject']);
        });
        Schema::create('gsc_properties', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('gsc_account_id')->constrained()->cascadeOnDelete();
            $t->string('property_uri', 2048);
            $t->string('property_type');
            $t->string('status')->default('selected');
            $t->timestamp('selected_at')->nullable();
            $t->timestamps();
            $t->unique(['site_id', 'property_uri']);
        });
        Schema::create('gsc_import_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('gsc_property_id')->constrained()->cascadeOnDelete();
            $t->date('date_start');
            $t->date('date_end');
            $t->json('dimensions')->nullable();
            $t->string('status')->default('queued');
            $t->json('summary')->nullable();
            $t->json('error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });
        foreach (['gsc_page_metrics' => 'page_url', 'gsc_query_metrics' => 'query'] as $table => $key) {
            Schema::create($table, function (Blueprint $t) use ($key): void {
                $t->id();
                $t->foreignId('gsc_property_id')->constrained()->cascadeOnDelete();
                $t->date('date');
                $t->string($key, 2048);
                $t->unsignedBigInteger('clicks')->default(0);
                $t->unsignedBigInteger('impressions')->default(0);
                $t->decimal('ctr', 8, 6)->default(0);
                $t->decimal('position', 8, 3)->nullable();
                $t->string('device')->nullable();
                $t->string('country')->nullable();
                $t->unique(['gsc_property_id', 'date', $key, 'device', 'country']);
            });
        }Schema::create('gsc_query_page_metrics', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('gsc_property_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->string('query', 2048);
            $t->string('page_url', 2048);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->decimal('ctr', 8, 6)->default(0);
            $t->decimal('position', 8, 3)->nullable();
            $t->unique(['gsc_property_id', 'date', 'query', 'page_url']);
        });
    }

    public function down(): void
    {
        foreach (['gsc_query_page_metrics', 'gsc_query_metrics', 'gsc_page_metrics', 'gsc_import_runs', 'gsc_properties', 'gsc_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
