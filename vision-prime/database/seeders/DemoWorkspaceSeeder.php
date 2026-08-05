<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(['slug' => 'vision-prime-demo'], ['public_id' => (string) Str::ulid(), 'name' => 'Vision Prime Demo Agency', 'status' => 'active']);
        $user = User::firstOrCreate(['email' => 'demo@visionprime.test'], ['name' => 'Demo Admin', 'password' => Hash::make('DemoAdmin2024!Secure#')]);
        $role = \DB::table('roles')->where('key', 'agency-admin')->value('id');
        if ($role) {
            \DB::table('memberships')->updateOrInsert(['organization_id' => $org->id, 'user_id' => $user->id], ['role_id' => $role, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]);
        }$client = Client::firstOrCreate(['organization_id' => $org->id, 'name' => 'نمونه کلینیک آفتاب'], ['public_id' => (string) Str::ulid(), 'status' => 'active']);
        $project = Project::firstOrCreate(['organization_id' => $org->id, 'client_id' => $client->id, 'name' => 'رشد ارگانیک'], ['public_id' => (string) Str::ulid(), 'status' => 'active']);
        Site::firstOrCreate(['organization_id' => $org->id, 'canonical_url' => 'https://demo.example.ir'], ['project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'سایت نمونه', 'status' => 'active']);
    }
}
