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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
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

        // Windows PHP ships without a CA bundle (curl.cainfo/openssl.cafile are empty),
        // which breaks every outbound HTTPS call (GSC token exchange, connector, ...)
        // with cURL error 60. Point the HTTP client at the bundled CA roots instead of
        // relying on the system php.ini.
        $caBundle = storage_path('certs/cacert.pem');
        if (is_file($caBundle)) {
            Http::globalOptions(['verify' => $caBundle]);
        }

        // Funnel/Tailscale terminates TLS in front of :80, so the app only ever
        // sees plain HTTP. Force https scheme so generated asset URLs (mixed
        // content) keep working when served behind the public https endpoint,
        // while local dev (127.0.0.1) keeps plain http.
        $host = (string) request()?->getHost();
        if (str_ends_with($host, '.ts.net')) {
            URL::forceScheme('https');
        }
    }
}
