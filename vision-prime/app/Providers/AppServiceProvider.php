<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Connector\Contracts\ConnectorContentClient;
use App\Domains\Connector\Services\SignedConnectorClient;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Domains\Workspace\Policies\ClientPolicy;
use App\Domains\Workspace\Policies\ProjectPolicy;
use App\Domains\Workspace\Policies\SitePolicy;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConnectorContentClient::class, SignedConnectorClient::class);
        $this->app->scoped(CurrentOrganization::class, fn (): CurrentOrganization => new CurrentOrganization);
        $this->app->scoped(CurrentClient::class, fn (): CurrentClient => new CurrentClient);
        $this->app->scoped(RequestContext::class, fn (): RequestContext => new RequestContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
    }
}
