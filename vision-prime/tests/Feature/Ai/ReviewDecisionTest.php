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
use Tests\TestCase;

class ReviewDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_reviewer_can_approve_item(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $u = User::factory()->create();
        $review = \DB::table('review_items')->insertGetId(['site_id' => $s->id, 'subject_type' => 'ai_generation', 'subject_id' => 1, 'status' => 'pending_review', 'assigned_to' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        app(DecideReviewItem::class)->handle($review, $u, 'approved', 'looks good');
        $this->assertDatabaseHas('review_items', ['id' => $review, 'status' => 'approved']);
        $this->assertDatabaseHas('review_decisions', ['review_item_id' => $review, 'decision' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.decided']);
    }
}
