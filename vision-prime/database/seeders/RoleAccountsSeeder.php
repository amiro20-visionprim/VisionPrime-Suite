<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoleAccountsSeeder extends Seeder
{
    /** @var array<string, array{name: string, email: string}> */
    private const ACCOUNTS = [
        'super-admin' => ['name' => 'Super Admin', 'email' => 'superadmin@visionprime.test'],
        'agency-admin' => ['name' => 'Admin', 'email' => 'admin@visionprime.test'],
        'seo-manager' => ['name' => 'SEO Manager', 'email' => 'seo.manager@visionprime.test'],
        'content-manager' => ['name' => 'Content Manager', 'email' => 'content.manager@visionprime.test'],
        'expert-reviewer' => ['name' => 'Expert Reviewer', 'email' => 'reviewer@visionprime.test'],
        'developer' => ['name' => 'Developer', 'email' => 'developer@visionprime.test'],
        'client-viewer' => ['name' => 'Client Viewer', 'email' => 'client@visionprime.test'],
        'client-approver' => ['name' => 'Client Approver', 'email' => 'client.approver@visionprime.test'],
    ];

    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'vision-prime-demo'],
            ['public_id' => (string) Str::ulid(), 'name' => 'Vision Prime Demo Agency', 'status' => 'active']
        );

        $client = Client::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'نمونه کلینیک آفتاب'],
            ['public_id' => (string) Str::ulid(), 'status' => 'active']
        );
        $project = Project::firstOrCreate(
            ['organization_id' => $org->id, 'client_id' => $client->id, 'name' => 'رشد ارگانیک'],
            ['public_id' => (string) Str::ulid(), 'status' => 'active']
        );
        Site::firstOrCreate(
            ['organization_id' => $org->id, 'canonical_url' => 'https://demo.example.ir'],
            ['project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'سایت نمونه', 'status' => 'active']
        );

        $password = env('DEMO_ACCOUNTS_PASSWORD', 'VisionPrime@Suite-2026!');

        foreach (self::ACCOUNTS as $roleKey => $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => Hash::make($password)]
            );
            $role = Role::where('key', $roleKey)->firstOrFail();

            \DB::table('memberships')->updateOrInsert(
                ['organization_id' => $org->id, 'user_id' => $user->id],
                [
                    'role_id' => $role->id,
                    'status' => 'active',
                    'assigned_scope' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            if (in_array($roleKey, ['client-viewer', 'client-approver'], true)) {
                ClientUserAssignment::firstOrCreate(
                    ['client_id' => $client->id, 'user_id' => $user->id],
                    ['portal_role' => $roleKey === 'client-approver' ? 'approver' : 'viewer']
                );
            }
        }

        $this->command?->info('RoleAccountsSeeder: 8 accounts created. Shared password: '.$password);
    }
}
