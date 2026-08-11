<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'organization.manage.organization', 'member.view.organization', 'member.manage.organization', 'role.manage.organization',
            'client.view.organization', 'client.manage.organization', 'project.view.organization', 'project.manage.organization',
            'site.view.organization', 'site.manage.organization', 'site.view.assigned', 'connector.view.assigned',
            'connector.manage.assigned', 'connector.pair.assigned', 'connector.disconnect.assigned', 'gsc.view.assigned',
            'gsc.manage.assigned', 'gsc.import.assigned', 'intelligence.view.assigned', 'intelligence.override.assigned',
            'recommendation.view.assigned', 'recommendation.create.assigned', 'recommendation.manage.assigned', 'ai.use.assigned',
            'ai.template.manage.organization', 'ai.provider.manage.organization', 'review.view.assigned', 'review.decide.assigned',
            'command.view.assigned', 'command.create.assigned', 'command.approve.assigned', 'command.dispatch.assigned',
            'command.execute.assigned', 'command.stop.assigned', 'rollback.view.assigned', 'rollback.execute.assigned',
            'automation_policy.view.assigned', 'automation_policy.manage.assigned', 'report.view.assigned', 'report.generate.assigned',
            'report.publish.assigned', 'report.export.assigned', 'audit.view.organization', 'billing.view.organization', 'billing.manage.organization',
            'marketing.view.organization', 'marketing.manage.organization',
        ];

        foreach ($permissions as $key) {
            [$domain] = explode('.', $key, 2);
            Permission::query()->updateOrCreate(['key' => $key], [
                'domain' => $domain,
                'description' => $key,
            ]);
        }

        $roles = [
            'super-admin' => ['name' => 'Super Admin', 'description' => 'مدیریت سطح پلتفرم', 'permissions' => $permissions],
            'agency-admin' => ['name' => 'Agency Admin', 'description' => 'مدیریت سازمان و عملیات', 'permissions' => array_values(array_filter($permissions, fn (string $key): bool => ! str_starts_with($key, 'billing.manage')))],
            'seo-manager' => ['name' => 'SEO Manager', 'description' => 'مدیریت عملیات SEO', 'permissions' => $this->matching($permissions, ['client.view', 'project.', 'site.', 'gsc.', 'intelligence.', 'recommendation.', 'ai.use', 'report.'])],
            'content-manager' => ['name' => 'Content Manager', 'description' => 'مدیریت توصیه‌های محتوایی', 'permissions' => $this->matching($permissions, ['site.view', 'intelligence.view', 'recommendation.', 'ai.use', 'report.view'])],
            'expert-reviewer' => ['name' => 'Expert Reviewer', 'description' => 'بررسی و تأیید موارد عملیاتی', 'permissions' => $this->matching($permissions, ['site.view', 'intelligence.view', 'review.', 'command.view', 'command.approve', 'rollback.view', 'report.view'])],
            'developer' => ['name' => 'Developer', 'description' => 'مدیریت اتصال و اجرای فنی', 'permissions' => $this->matching($permissions, ['site.view', 'connector.', 'command.view', 'command.execute', 'command.stop', 'rollback.', 'report.view'])],
            'client-viewer' => ['name' => 'Client Viewer', 'description' => 'مشاهده خروجی‌های مشتری', 'permissions' => $this->matching($permissions, ['report.view', 'intelligence.view'])],
            'client-approver' => ['name' => 'Client Approver', 'description' => 'مشاهده و تأیید موارد مشتری', 'permissions' => $this->matching($permissions, ['report.view', 'intelligence.view', 'review.view', 'review.decide'])],
            'marketing-manager' => ['name' => 'Marketing Manager', 'description' => 'مدیریت بازاریابی، لیدها و داده‌های تبلیغات', 'permissions' => $this->matching($permissions, ['marketing.', 'member.view.organization', 'report.view', 'intelligence.view'])],
        ];

        foreach ($roles as $key => $attributes) {
            $role = Role::query()->updateOrCreate(['key' => $key], [
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'is_system' => true,
            ]);

            $role->permissions()->sync(Permission::query()->whereIn('key', $attributes['permissions'])->pluck('id'));
        }
    }

    /** @param array<int, string> $permissions
     * @param  array<int, string>  $prefixes
     * @return array<int, string>
     */
    private function matching(array $permissions, array $prefixes): array
    {
        return array_values(array_filter($permissions, function (string $permission) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($permission, $prefix)) {
                    return true;
                }
            }

            return false;
        }));
    }
}
