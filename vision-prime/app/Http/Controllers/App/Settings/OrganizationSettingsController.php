<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function __construct(
        private readonly OrganizationPermission $organizationPermission,
        private readonly RecordAuditLog $audit,
    ) {}

    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();
        $user = $request->user();

        if ($user === null || ! $this->organizationPermission->allows($user, $organization, 'member.view.organization')) {
            abort(403, 'شما دسترسی مشاهدهٔ اعضای سازمان را ندارید.');
        }

        $members = Membership::query()
            ->where('organization_id', $organization->getKey())
            ->with(['user:id,name,email', 'role:id,key,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Membership $membership): array => $this->memberItem($membership))
            ->values();

        return Inertia::render('App/Settings/Organization', [
            'organization' => [
                'id' => $organization->getKey(),
                'publicId' => $organization->public_id,
                'name' => $organization->name,
                'status' => $organization->status,
                'createdAt' => $organization->created_at?->toIso8601String(),
            ],
            'members' => $members,
            'roles' => Role::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->getKey(),
                    'key' => $role->key,
                    'name' => $role->name,
                    'description' => $role->description,
                ])
                ->values(),
            'canManage' => $this->canManage($request->user(), $organization),
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $this->authorizeManage($organization);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $email = Str::lower($data['email']);
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return back()->withErrors(['email' => 'کاربری با این ایمیل در سامانه یافت نشد.'])->withInput();
        }

        $existing = Membership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        $membership = Membership::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'user_id' => $user->getKey()],
            ['role_id' => $data['role_id'], 'status' => 'active'],
        );

        if ($existing !== null && $existing->role_id !== (int) $data['role_id']) {
            $this->audit->handle(
                action: 'organization.member_role_changed',
                subject: $membership,
                before: ['role_id' => $existing->role_id],
                after: ['user_id' => $user->getKey(), 'email' => $user->email, 'role_id' => $data['role_id']],
                organization: $organization,
            );

            return back()->with('status', 'نقش عضو به‌روزرسانی شد.');
        }

        $this->audit->handle(
            action: 'organization.member_added',
            subject: $membership,
            after: ['user_id' => $user->getKey(), 'email' => $user->email, 'role_id' => $data['role_id']],
            organization: $organization,
        );

        return back()->with('status', 'عضو با موفقیت به سازمان اضافه شد.');
    }

    public function update(Request $request, Membership $membership, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $this->authorizeManage($organization);
        $this->assertBelongsToOrganization($membership, $organization);

        $data = $request->validate(['role_id' => ['required', 'integer', 'exists:roles,id']]);
        $previousRoleId = $membership->role_id;
        $membership->update(['role_id' => $data['role_id']]);

        $this->audit->handle(
            action: 'organization.member_role_changed',
            subject: $membership,
            before: ['role_id' => $previousRoleId],
            after: ['role_id' => $data['role_id']],
            organization: $organization,
        );

        return back()->with('status', 'نقش عضو به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Membership $membership, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $this->authorizeManage($organization);
        $this->assertBelongsToOrganization($membership, $organization);

        if ($membership->user_id === $request->user()?->getKey()) {
            abort(422, 'نمی‌توانید عضویت خودتان را حذف کنید.');
        }

        $this->audit->handle(
            action: 'organization.member_removed',
            subject: $membership,
            before: ['user_id' => $membership->user_id, 'role_id' => $membership->role_id],
            organization: $organization,
        );

        $membership->delete();

        return back()->with('status', 'عضو از سازمان حذف شد.');
    }

    /** @return array<string, mixed> */
    private function memberItem(Membership $membership): array
    {
        return [
            'id' => $membership->getKey(),
            'name' => $membership->user?->name,
            'email' => $membership->user?->email,
            'roleId' => $membership->role_id,
            'roleName' => $membership->role?->name,
            'roleKey' => $membership->role?->key,
            'status' => $membership->status,
            'isSelf' => $membership->user_id === request()->user()?->getKey(),
            'joinedAt' => $membership->created_at?->toIso8601String(),
        ];
    }

    private function canManage(?User $user, Organization $organization): bool
    {
        return $user !== null && $this->organizationPermission->allows($user, $organization, 'member.manage.organization');
    }

    private function authorizeManage(Organization $organization): void
    {
        if (! $this->canManage(request()->user(), $organization)) {
            abort(403, 'شما دسترسی مدیریت اعضای سازمان را ندارید.');
        }
    }

    private function assertBelongsToOrganization(Membership $membership, Organization $organization): void
    {
        if ($membership->organization_id !== $organization->getKey()) {
            abort(404);
        }
    }
}
