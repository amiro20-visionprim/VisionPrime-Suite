<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domains\Ai\Actions\DecideReviewItem;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReviewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_assigned_reviewer_is_rejected(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $assigned = User::factory()->create();
        $other = User::factory()->create();
        $review = \DB::table('review_items')->insertGetId(['site_id' => $s->id, 'subject_type' => 'ai_generation', 'subject_id' => 1, 'status' => 'pending_review', 'assigned_to' => $assigned->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->expectException(HttpException::class);
        app(DecideReviewItem::class)->handle($review, $other, 'approved');
    }
}
