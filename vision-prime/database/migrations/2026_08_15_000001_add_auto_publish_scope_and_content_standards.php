<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_automation_policies', function (Blueprint $t): void {
            // دامنهٔ انتشار خودکار برای سایت‌های کم‌حساسیت (opt-in صریح ادمین).
            // none (پیش‌فرض) | meta | article | product | all
            $t->string('auto_publish_scope')->default('none')->after('overrides_json');
        });

        Schema::table('commands', function (Blueprint $t): void {
            $t->string('content_type')->nullable()->after('type'); // meta | article | product
        });

        Schema::create('site_content_standard_learnings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('content_type');
            $t->string('subtype');
            $t->unsignedInteger('learned_word_min')->default(0);
            $t->unsignedInteger('learned_min_headings')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['site_id', 'content_type', 'subtype'], 'site_std_learning_key');
        });

        Schema::create('content_standards', function (Blueprint $t): void {
            $t->id();
            $t->string('content_type');          // article | product | meta | landing
            $t->string('subtype');               // مثلاً: tutorial | comparison | review | short_desc | long_desc ...
            $t->string('intent');                // informational | commercial | transactional | navigational
            $t->unsignedInteger('word_min')->default(0);
            $t->unsignedInteger('word_max')->nullable();
            $t->unsignedInteger('min_headings')->default(0);
            $t->json('required_elements')->nullable();   // ['faq','table','cta','pros_cons', ...]
            $t->string('tone')->nullable();              // informative | persuasive | neutral | technical
            $t->json('keyword_guidance')->nullable();    // { density_max, title_required, intro_required }
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('source')->default('seed');       // seed | learned | manual | serp
            $t->timestamps();
            $t->unique(['content_type', 'subtype', 'intent', 'version'], 'content_standards_key_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_standards');
        Schema::dropIfExists('site_content_standard_learnings');
        Schema::table('commands', function (Blueprint $t): void {
            $t->dropColumn('content_type');
        });
        Schema::table('site_automation_policies', function (Blueprint $t): void {
            $t->dropColumn('auto_publish_scope');
        });
    }
};
