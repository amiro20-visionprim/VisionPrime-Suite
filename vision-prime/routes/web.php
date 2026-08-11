<?php

declare(strict_types=1);

use App\Http\Controllers\App\ClientController;
use App\Http\Controllers\App\CommandController;
use App\Http\Controllers\App\CommandDispatchController;
use App\Http\Controllers\App\ConversionRiskController;
use App\Http\Controllers\App\CurrentOrganizationController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\GscDashboardController;
use App\Http\Controllers\App\GscAnalyzeController;
use App\Http\Controllers\App\GscImportController;
use App\Http\Controllers\App\GscMetricsController;
use App\Http\Controllers\App\GscOAuthController;
use App\Http\Controllers\App\GscPropertyController;
use App\Http\Controllers\App\MoneyPageController;
use App\Http\Controllers\App\OpportunityController;
use App\Http\Controllers\App\OrganizationOnboardingController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\RecommendationController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\ReportPublishController;
use App\Http\Controllers\App\ReviewController;
use App\Http\Controllers\App\ReviewDecisionController;
use App\Http\Controllers\App\ReviewDetailController;
use App\Http\Controllers\App\AiDraftController;
use App\Http\Controllers\App\Settings\AiSettingsController;
use App\Http\Controllers\App\Settings\AuditLogSettingsController;
use App\Http\Controllers\App\Settings\IntegrationsSettingsController;
use App\Http\Controllers\App\Settings\OrganizationSettingsController;
use App\Http\Controllers\App\SiteConnectorController;
use App\Http\Controllers\App\SiteConnectorTokenController;
use App\Http\Controllers\App\SiteController;
use App\Http\Controllers\App\SiteDisconnectController;
use App\Http\Controllers\App\SiteSyncController;
use App\Http\Controllers\App\SiteSyncStatusController;
use App\Http\Controllers\App\UrlProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Client\ClientActivityController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientDecisionController;
use App\Http\Controllers\Client\ClientDecisionsController;
use App\Http\Controllers\Client\ClientGrowthController;
use App\Http\Controllers\Client\ClientPrioritiesController;
use App\Http\Controllers\Client\ClientReportController;
use App\Http\Controllers\Client\ClientSiteHealthController;
use App\Http\Controllers\Client\CurrentClientController;
use App\Http\Controllers\Connector\HealthCheckController;
use App\Http\Controllers\Marketing\LeadController;
use App\Http\Controllers\Connector\CommandResultController;
use App\Http\Controllers\Connector\PairSiteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/connector/pair', PairSiteController::class)->name('connector.pair');
Route::post('/connector/health', HealthCheckController::class)->name('connector.health');
Route::post('/connector/command-result', CommandResultController::class)->name('connector.command-result');

Route::get('/up', function (): \Illuminate\Http\JsonResponse {
    try {
        \Illuminate\Support\Facades\DB::select('select 1');
    } catch (\Throwable) {
        abort(503);
    }

    return response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]);
})->name('health');

Route::get('/', fn () => Inertia::render('Home'))->name('home');
Route::get('/product', fn () => Inertia::render('Marketing/Product'))->name('marketing.product');
Route::get('/features', fn () => Inertia::render('Marketing/Features'))->name('marketing.features');
Route::get('/pricing', fn () => Inertia::render('Marketing/Pricing'))->name('marketing.pricing');
Route::get('/demo', fn () => Inertia::render('Marketing/Demo'))->name('marketing.demo');
Route::post('/demo', [LeadController::class, 'store'])->name('marketing.lead')->middleware('throttle:10,1');
Route::get('/security', fn () => Inertia::render('Marketing/Security'))->name('marketing.security');
Route::get('/about', fn () => Inertia::render('Marketing/About'))->name('marketing.about');
Route::get('/contact', fn () => Inertia::render('Marketing/Contact'))->name('marketing.contact');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store')->middleware('throttle:10,1');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/app/onboarding', [OrganizationOnboardingController::class, 'create'])->name('app.onboarding');
    Route::post('/app/onboarding', [OrganizationOnboardingController::class, 'store'])->name('app.onboarding.store');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'current.organization', 'client.portal'])->prefix('client')->group(function (): void {
    Route::get('/dashboard', ClientDashboardController::class)->name('client.dashboard');
    Route::get('/growth', ClientGrowthController::class)->name('client.growth');
    Route::get('/reports', [ClientReportController::class, 'index'])->name('client.reports.index');
    Route::put('/current-client/{client}', [CurrentClientController::class, 'update'])->name('client.current-client.update');

    Route::get('/site-health', ClientSiteHealthController::class)->name('client.site-health');
    Route::get('/opportunities', ClientPrioritiesController::class)->name('client.opportunities');
    Route::get('/decisions', ClientDecisionsController::class)->name('client.decisions');
    Route::get('/activity', ClientActivityController::class)->name('client.activity');
    Route::post('/decisions/commands/{command}', [ClientDecisionController::class, 'command'])->name('client.decisions.command')->middleware('throttle:20,1');
    Route::post('/decisions/reviews/{review}', [ClientDecisionController::class, 'review'])->name('client.decisions.review')->middleware('throttle:20,1');
});

Route::middleware(['auth', 'current.organization'])->group(function (): void {
    Route::get('/app/dashboard', DashboardController::class)->name('app.dashboard');
    Route::put('/app/current-organization/{organization}', [CurrentOrganizationController::class, 'update'])->name('app.current-organization.update');

    Route::get('/app/sites', [SiteController::class, 'index'])->name('app.sites.index');
    Route::get('/app/sites/create', [SiteController::class, 'create'])->name('app.sites.create');
    Route::post('/app/sites', [SiteController::class, 'store'])->name('app.sites.store');
    Route::get('/app/sites/{site}', [SiteController::class, 'show'])->name('app.sites.show');
    Route::get('/app/sites/{site}/connector', [SiteConnectorController::class, 'show'])->name('app.sites.connector');
    Route::post('/app/sites/{site}/connector/pairing-token', [SiteConnectorTokenController::class, 'store'])->name('app.sites.connector.pairing-token');
    Route::post('/app/sites/{site}/connector/disconnect', SiteDisconnectController::class)->name('app.sites.connector.disconnect');
    Route::get('/app/sites/{site}/sync', [SiteSyncStatusController::class, 'show'])->name('app.sites.sync.show');
    Route::post('/app/sites/{site}/sync', [SiteSyncController::class, 'store'])->name('app.sites.sync.store');
    Route::get('/app/url-profiles', [UrlProfileController::class, 'index'])->name('app.url-profiles.index');
    Route::get('/app/url-profiles/{urlProfile}', [UrlProfileController::class, 'show'])->name('app.url-profiles.show');
    Route::get('/app/sites/{site}/edit', [SiteController::class, 'edit'])->name('app.sites.edit');
    Route::put('/app/sites/{site}', [SiteController::class, 'update'])->name('app.sites.update');
    Route::delete('/app/sites/{site}', [SiteController::class, 'destroy'])->name('app.sites.destroy');

    Route::get('/app/gsc', GscDashboardController::class)->name('app.gsc.index');
    Route::get('/app/gsc/connect', [GscOAuthController::class, 'redirect'])->name('app.gsc.connect');
    Route::get('/app/gsc/callback', [GscOAuthController::class, 'callback'])->name('app.gsc.callback');
    Route::get('/app/gsc/pages', [GscMetricsController::class, 'pages'])->name('app.gsc.pages');
    Route::get('/app/gsc/queries', [GscMetricsController::class, 'queries'])->name('app.gsc.queries');
    Route::get('/app/gsc/properties', [GscPropertyController::class, 'index'])->name('app.gsc.properties');
    Route::post('/app/gsc/properties', [GscPropertyController::class, 'store'])->name('app.gsc.properties.store');
    Route::post('/app/gsc/import', [GscImportController::class, 'store'])->name('app.gsc.import')->middleware('throttle:10,1');
    Route::post('/app/gsc/analyze', [GscAnalyzeController::class, 'store'])->name('app.gsc.analyze')->middleware('throttle:10,1');

    Route::get('/app/commands', [CommandController::class, 'index'])->name('app.commands.index');
    Route::get('/app/commands/{command}', [CommandController::class, 'show'])->name('app.commands.show');
    Route::post('/app/commands/{command}/dispatch', [CommandDispatchController::class, 'store'])->name('app.commands.dispatch')->middleware('throttle:10,1');
    Route::get('/app/money-pages', [MoneyPageController::class, 'index'])->name('app.money-pages.index');
    Route::get('/app/conversion-risks', [ConversionRiskController::class, 'index'])->name('app.conversion-risks.index');
    Route::get('/app/opportunities', [OpportunityController::class, 'index'])->name('app.opportunities.index');
    Route::get('/app/opportunities/{opportunity}', [OpportunityController::class, 'show'])->name('app.opportunities.show');
    Route::post('/app/opportunities/{opportunity}/recommendation', [RecommendationController::class, 'fromOpportunity'])->name('app.opportunities.recommendation');

    Route::get('/app/recommendations', [RecommendationController::class, 'index'])->name('app.recommendations.index');
    Route::get('/app/recommendations/create', [RecommendationController::class, 'create'])->name('app.recommendations.create');
    Route::post('/app/recommendations', [RecommendationController::class, 'store'])->name('app.recommendations.store');
    Route::put('/app/recommendations/{recommendation}', [RecommendationController::class, 'update'])->name('app.recommendations.update');
    Route::post('/app/recommendations/{recommendation}/command', [RecommendationController::class, 'toCommand'])->name('app.recommendations.command')->middleware('throttle:10,1');
    Route::get('/app/reviews', [ReviewController::class, 'index'])->name('app.reviews.index');
    Route::get('/app/reviews/{review}', [ReviewDetailController::class, 'show'])->name('app.reviews.show');
    Route::post('/app/reviews/{review}/decision', [ReviewDecisionController::class, 'store'])->name('app.reviews.decision');
    Route::get('/app/reports', [ReportController::class, 'index'])->name('app.reports.index');
    Route::post('/app/reports', [ReportController::class, 'store'])->name('app.reports.store');
    Route::post('/app/reports/{report}/publish', [ReportPublishController::class, 'store'])->name('app.reports.publish');

    Route::get('/app/projects', [ProjectController::class, 'index'])->name('app.projects.index');
    Route::get('/app/projects/create', [ProjectController::class, 'create'])->name('app.projects.create');
    Route::post('/app/projects', [ProjectController::class, 'store'])->name('app.projects.store');
    Route::get('/app/projects/{project}', [ProjectController::class, 'show'])->name('app.projects.show');
    Route::get('/app/projects/{project}/edit', [ProjectController::class, 'edit'])->name('app.projects.edit');
    Route::put('/app/projects/{project}', [ProjectController::class, 'update'])->name('app.projects.update');
    Route::delete('/app/projects/{project}', [ProjectController::class, 'destroy'])->name('app.projects.destroy');

    Route::get('/app/clients', [ClientController::class, 'index'])->name('app.clients.index');
    Route::get('/app/clients/create', [ClientController::class, 'create'])->name('app.clients.create');
    Route::post('/app/clients', [ClientController::class, 'store'])->name('app.clients.store');
    Route::get('/app/clients/{client}', [ClientController::class, 'show'])->name('app.clients.show');
    Route::get('/app/clients/{client}/edit', [ClientController::class, 'edit'])->name('app.clients.edit');
    Route::put('/app/clients/{client}', [ClientController::class, 'update'])->name('app.clients.update');
    Route::delete('/app/clients/{client}', [ClientController::class, 'destroy'])->name('app.clients.destroy');
    Route::post('/app/clients/{client}/assignments', [ClientController::class, 'assignUser'])->name('app.clients.assignments.store');
    Route::delete('/app/clients/{client}/assignments/{assignment}', [ClientController::class, 'removeUserAssignment'])->name('app.clients.assignments.destroy');

    Route::get('/app/settings/organization', [OrganizationSettingsController::class, 'index'])->name('app.settings.organization');
    Route::post('/app/settings/organization/members', [OrganizationSettingsController::class, 'store'])->name('app.settings.organization.members.store')->middleware('throttle:20,1');
    Route::put('/app/settings/organization/members/{membership}', [OrganizationSettingsController::class, 'update'])->name('app.settings.organization.members.update');
    Route::delete('/app/settings/organization/members/{membership}', [OrganizationSettingsController::class, 'destroy'])->name('app.settings.organization.members.destroy');
    Route::get('/app/settings/integrations', [IntegrationsSettingsController::class, 'index'])->name('app.settings.integrations');
    Route::post('/app/settings/ai-provider', [AiSettingsController::class, 'store'])->name('app.settings.ai-provider.store')->middleware('throttle:20,1');
    Route::delete('/app/settings/ai-provider/{provider}', [AiSettingsController::class, 'destroy'])->name('app.settings.ai-provider.destroy');
    Route::get('/app/settings/audit-log', [AuditLogSettingsController::class, 'index'])->name('app.settings.audit-log');

    Route::post('/app/ai-drafts', [AiDraftController::class, 'store'])->name('app.ai-drafts.store')->middleware('throttle:10,1');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('/_design-system', fn () => Inertia::render('Development/DesignSystem'))->name('development.design-system');
    Route::get('/_localization', fn () => Inertia::render('Development/Localization'))->name('development.localization');
}
