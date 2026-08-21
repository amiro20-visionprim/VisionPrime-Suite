<?php

declare(strict_types=1);

use App\Http\Controllers\App\AiDraftController;
use App\Http\Controllers\App\ContentApiController;
use App\Http\Controllers\App\ContentGuardrailController;
use App\Http\Controllers\App\PromptTemplateController;
use App\Http\Controllers\App\ContentCommandCenterController;
use App\Http\Controllers\App\AutomationPolicyController;
use App\Http\Controllers\App\ClientController;
use App\Http\Controllers\App\CommandController;
use App\Http\Controllers\App\CommandDecisionController;
use App\Http\Controllers\App\CommandDispatchController;
use App\Http\Controllers\App\ContentCalendarController;
use App\Http\Controllers\App\ConversionRiskController;
use App\Http\Controllers\App\CurrentOrganizationController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\GscAnalyzeController;
use App\Http\Controllers\App\GscDashboardController;
use App\Http\Controllers\App\GscImportController;
use App\Http\Controllers\App\GscMetricsController;
use App\Http\Controllers\App\GscOAuthController;
use App\Http\Controllers\App\GscPropertyController;
use App\Http\Controllers\App\MarketingLeadController;
use App\Http\Controllers\App\MoneyPageController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\OpportunityController;
use App\Http\Controllers\App\OrganizationOnboardingController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\RecommendationController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\ReportPublishController;
use App\Http\Controllers\App\ReviewController;
use App\Http\Controllers\App\ReviewDecisionController;
use App\Http\Controllers\App\ReviewDetailController;
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
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\Auth\OtpRegisterController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Client\ClientActivityController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientDecisionController;
use App\Http\Controllers\Client\ClientDecisionsController;
use App\Http\Controllers\Client\ClientGrowthController;
use App\Http\Controllers\Client\ClientPrioritiesController;
use App\Http\Controllers\Client\ClientReportController;
use App\Http\Controllers\Client\ClientSiteHealthController;
use App\Http\Controllers\Client\CurrentClientController;
use App\Http\Controllers\Connector\CommandResultController;
use App\Http\Controllers\Connector\HealthCheckController;
use App\Http\Controllers\Connector\PairSiteController;
use App\Http\Controllers\Marketing\AssistantController;
use App\Http\Controllers\Marketing\LeadController;
use App\Http\Controllers\Platform\PlatformBillingController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformDecisionController;
use App\Http\Controllers\Platform\PlatformEmergencyController;
use App\Http\Controllers\Platform\PlatformImpersonationController;
use App\Http\Controllers\Platform\PlatformMfaController;
use App\Http\Controllers\Platform\PlatformOperationsController;
use App\Http\Controllers\Platform\PlatformOrganizationController;
use App\Http\Controllers\Platform\PlatformPaymentGatewayController;
use App\Http\Controllers\Platform\PlatformReportController;
use App\Http\Controllers\Platform\PlatformSmsController;
use App\Http\Controllers\TrainingController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/connector/pair', PairSiteController::class)->name('connector.pair');
Route::post('/connector/health', HealthCheckController::class)->name('connector.health');
Route::post('/connector/command-result', CommandResultController::class)->name('connector.command-result');

Route::get('/up', function (): JsonResponse {
    try {
        DB::select('select 1');
    } catch (Throwable) {
        abort(503);
    }

    return response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]);
})->name('health');

Route::get('/', fn () => Inertia::render('Home'))->name('home');
Route::get('/for-agencies', fn () => Inertia::render('Marketing/ForAgencies'))->name('marketing.for-agencies');
Route::get('/for-ecommerce', fn () => Inertia::render('Marketing/ForEcommerce'))->name('marketing.for-ecommerce');
Route::get('/for-clinics', fn () => Inertia::render('Marketing/ForClinics'))->name('marketing.for-clinics');
Route::get('/for-education', fn () => Inertia::render('Marketing/ForEducation'))->name('marketing.for-education');
Route::get('/for-hospitality', fn () => Inertia::render('Marketing/ForHospitality'))->name('marketing.for-hospitality');
Route::get('/product', fn () => Inertia::render('Marketing/Product'))->name('marketing.product');
Route::get('/features', fn () => Inertia::render('Marketing/Features'))->name('marketing.features');
Route::get('/pricing', fn () => Inertia::render('Marketing/Pricing'))->name('marketing.pricing');
Route::get('/demo', fn () => Inertia::render('Marketing/Demo'))->name('marketing.demo');
Route::post('/demo', [LeadController::class, 'store'])->name('marketing.lead')->middleware('throttle:10,1');

Route::get('/assistant/knowledge', [AssistantController::class, 'knowledge'])->name('marketing.assistant.knowledge');
Route::post('/assistant/chat', [AssistantController::class, 'chat'])->name('marketing.assistant.chat')->middleware('throttle:30,1');
Route::post('/assistant/contact', [AssistantController::class, 'contact'])->name('marketing.assistant.contact')->middleware('throttle:5,1');
Route::get('/security', fn () => Inertia::render('Marketing/Security'))->name('marketing.security');
Route::get('/about', fn () => Inertia::render('Marketing/About'))->name('marketing.about');
Route::get('/contact', fn () => Inertia::render('Marketing/Contact'))->name('marketing.contact');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store')->middleware('throttle:10,1');
    Route::post('/register/otp', [OtpRegisterController::class, 'request'])->name('register.otp')->middleware('throttle:5,1');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::post('/login/otp', [OtpLoginController::class, 'request'])->name('login.otp')->middleware('throttle:5,1');
    Route::post('/login/otp/verify', [OtpLoginController::class, 'verify'])->name('login.otp.verify')->middleware('throttle:10,1');

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
    Route::get('/training', [TrainingController::class, 'client'])->name('client.training');
    Route::get('/reports', [ClientReportController::class, 'index'])->name('client.reports.index');
    Route::put('/current-client/{client}', [CurrentClientController::class, 'update'])->name('client.current-client.update');

    Route::get('/site-health', ClientSiteHealthController::class)->name('client.site-health');
    Route::get('/opportunities', ClientPrioritiesController::class)->name('client.opportunities');
    Route::get('/decisions', ClientDecisionsController::class)->name('client.decisions');
    Route::get('/activity', ClientActivityController::class)->name('client.activity');
    Route::post('/decisions/commands/{command}', [ClientDecisionController::class, 'command'])->name('client.decisions.command')->middleware('throttle:20,1');
    Route::post('/decisions/questions', [ClientDecisionController::class, 'question'])->name('client.decisions.question')->middleware('throttle:10,1');
    Route::post('/decisions/reviews/{review}', [ClientDecisionController::class, 'review'])->name('client.decisions.review')->middleware('throttle:20,1');
});

Route::middleware(['auth', 'current.organization'])->group(function (): void {
    Route::get('/app/dashboard', DashboardController::class)->name('app.dashboard');
    // Content Command Center v2
    Route::get('/app/content-command-center', [ContentCommandCenterController::class, 'index'])->name('app.content-command-center');
    Route::get('/api/command-center/performance', [ContentCommandCenterController::class, 'performance'])->name('api.command-center.performance');
    Route::get('/api/command-center/serp', [ContentCommandCenterController::class, 'serpResults'])->name('api.command-center.serp');
    Route::post('/api/command-center/serp/analyze', [ContentCommandCenterController::class, 'serpAnalyze'])->name('api.command-center.serp.analyze');
    Route::get('/api/command-center/keywords', [ContentCommandCenterController::class, 'keywords'])->name('api.command-center.keywords');
    Route::get('/api/command-center/calendar', [ContentCommandCenterController::class, 'calendar'])->name('api.command-center.calendar');
    Route::post('/api/command-center/calendar', [ContentCommandCenterController::class, 'calendarStore'])->name('api.command-center.calendar.store');
    Route::get('/api/command-center/experiments', [ContentCommandCenterController::class, 'experiments'])->name('api.command-center.experiments');
    Route::get('/api/command-center/suggestions', [ContentCommandCenterController::class, 'suggestions'])->name('api.command-center.suggestions');
    Route::post('/api/command-center/suggestions/{id}/action', [ContentCommandCenterController::class, 'suggestionAction'])->name('api.command-center.suggestions.action');
    Route::get('/app/training', [TrainingController::class, 'agency'])->name('app.training');

    Route::get('/app/notifications', [NotificationController::class, 'index'])->name('app.notifications.index');
    Route::put('/app/notifications/read-all', [NotificationController::class, 'readAll'])->name('app.notifications.read-all');
    Route::put('/app/notifications/{notification}/read', [NotificationController::class, 'read'])->name('app.notifications.read');

    Route::get('/app/marketing', [MarketingLeadController::class, 'index'])->name('app.marketing.index');
    Route::get('/app/marketing/leads/{lead}', [MarketingLeadController::class, 'show'])->name('app.marketing.leads.show');
    Route::put('/app/marketing/leads/{lead}/status', [MarketingLeadController::class, 'updateStatus'])->name('app.marketing.leads.status')->middleware('throttle:20,1');
    Route::post('/app/marketing/leads/{lead}/notes', [MarketingLeadController::class, 'storeNote'])->name('app.marketing.leads.notes')->middleware('throttle:20,1');
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
    Route::delete('/app/sites/{site}', [SiteController::class, 'destroy'])->name('app.sites.destroy')->middleware('impersonation.readonly');

    Route::get('/app/sites/{site}/automation', [AutomationPolicyController::class, 'show'])->name('app.sites.automation');
    Route::get('/app/sites/{site}/automation/trust', [AutomationPolicyController::class, 'trust'])->name('app.sites.automation.trust');
    Route::put('/app/sites/{site}/automation', [AutomationPolicyController::class, 'update'])->name('app.sites.automation.update');
    Route::post('/app/sites/{site}/automation/routes', [AutomationPolicyController::class, 'updateRoutes'])->name('app.sites.automation.routes');
    Route::post('/app/sites/{site}/automation/profiles/copy', [AutomationPolicyController::class, 'copyProfile'])->name('app.sites.automation.profiles.copy');
    Route::post('/app/sites/{site}/automation/emergency-stop', [AutomationPolicyController::class, 'emergencyStop'])->name('app.sites.automation.emergency-stop');
    Route::post('/app/sites/{site}/automation/resume', [AutomationPolicyController::class, 'resume'])->name('app.sites.automation.resume');

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
    Route::post('/app/commands/{command}/decision', [CommandDecisionController::class, 'store'])->name('app.commands.decision')->middleware('throttle:20,1');
    Route::get('/app/money-pages', [MoneyPageController::class, 'index'])->name('app.money-pages.index');
    Route::get('/app/money-pages/{audit}', [MoneyPageController::class, 'show'])->name('app.money-pages.show');
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

    Route::get('/app/content-calendar', [ContentCalendarController::class, 'index'])->name('app.content-calendar.index');
    Route::post('/app/content-calendar/commands/{command}/schedule', [ContentCalendarController::class, 'schedule'])->name('app.content-calendar.schedule')->middleware('throttle:20,1');
    Route::post('/app/content-calendar/drafts', [ContentCalendarController::class, 'storeDraft'])->name('app.content-calendar.drafts.store')->middleware('throttle:10,1');
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
    Route::post('/app/settings/ai-provider', [AiSettingsController::class, 'store'])->name('app.settings.ai-provider.store')->middleware('throttle:20,1', 'impersonation.readonly');
    Route::delete('/app/settings/ai-provider/{provider}', [AiSettingsController::class, 'destroy'])->name('app.settings.ai-provider.destroy')->middleware('impersonation.readonly');
    Route::get('/app/settings/audit-log', [AuditLogSettingsController::class, 'index'])->name('app.settings.audit-log');

    Route::post('/app/ai-drafts', [AiDraftController::class, 'store'])->name('app.ai-drafts.store')->middleware('throttle:10,1');
    Route::get('/app/ai-drafts/article/create', [AiDraftController::class, 'createArticle'])->name('app.ai-drafts.article.create');
    Route::post('/app/ai-drafts/article', [AiDraftController::class, 'storeArticle'])->name('app.ai-drafts.article')->middleware('throttle:10,1');
    Route::get('/app/ai-drafts/product/create', [AiDraftController::class, 'createProduct'])->name('app.ai-drafts.product.create');
    Route::post('/app/ai-drafts/product', [AiDraftController::class, 'storeProduct'])->name('app.ai-drafts.product')->middleware('throttle:10,1');
    Route::get('/app/ai-drafts', [\App\Http\Controllers\App\AiDraftController::class, 'index'])->name('app.ai-drafts.index');
    Route::get('/app/ai-drafts/{id}/edit', [\App\Http\Controllers\App\AiDraftController::class, 'edit'])->name('app.ai-drafts.edit');
    Route::put('/app/ai-drafts/{id}', [\App\Http\Controllers\App\AiDraftController::class, 'update'])->name('app.ai-drafts.update');


    // ─── Content API (AI Gateway + SEO Intelligence) ───
    Route::get("/api/content/research", [ContentApiController::class, "research"])->name("api.content.research");
    Route::post("/api/content/score", [ContentApiController::class, "score"])->name("api.content.score")->middleware("throttle:30,1");
    Route::post("/api/content/links", [ContentApiController::class, "links"])->name("api.content.links")->middleware("throttle:30,1");
    Route::post("/api/content/schema", [ContentApiController::class, "schema"])->name("api.content.schema")->middleware("throttle:30,1");
    Route::post("/api/content/serp-analysis", [ContentApiController::class, "serpAnalysis"])->name("api.content.serp-analysis")->middleware("throttle:10,1");
    Route::post("/api/content/outline", [ContentApiController::class, "outline"])->name("api.content.outline")->middleware("throttle:10,1");
    Route::post("/api/content/generate", [ContentApiController::class, "generate"])->name("api.content.generate")->middleware("throttle:5,1");
    Route::get("/api/content/gsc-context", [ContentApiController::class, "gscContext"])->name("api.content.gsc-context");
    Route::get("/api/content/providers", [ContentApiController::class, "providers"])->name("api.content.providers");
    Route::post("/api/content/test-provider", [ContentApiController::class, "testProvider"])->name("api.content.test-provider")->middleware("throttle:5,1");
    Route::post("/api/content/detect-models", [\App\Http\Controllers\App\AiModelDetectionController::class, "detectModels"])->name("api.content.detect-models")->middleware("throttle:10,1");
    Route::post("/api/content/provider-usage", [\App\Http\Controllers\App\AiModelDetectionController::class, "getUsage"])->name("api.content.provider-usage")->middleware("throttle:10,1");


        // Content Guardrails (Command Center)
        Route::get("/api/content/guardrails", [ContentGuardrailController::class, "index"])->name("api.content.guardrails.index");
        Route::get("/api/content/guardrails/resolve", [ContentGuardrailController::class, "resolve"])->name("api.content.guardrails.resolve");
        Route::post("/api/content/guardrails", [ContentGuardrailController::class, "store"])->name("api.content.guardrails.store")->middleware("throttle:20,1");
        Route::delete("/api/content/guardrails/{guardrail}", [ContentGuardrailController::class, "destroy"])->name("api.content.guardrails.destroy")->middleware("throttle:20,1");
        Route::post("/api/content/guardrails/seed", [ContentGuardrailController::class, "seed"])->name("api.content.guardrails.seed")->middleware("throttle:10,1");
        Route::post("/api/content/check-duplicate", [ContentApiController::class, "checkDuplicate"])->name("api.content.check-duplicate");
        // Prompt Templates Library
        Route::get("/api/content/prompt-templates", [PromptTemplateController::class, "index"])->name("api.content.prompt-templates.index");
        Route::post("/api/content/prompt-templates", [PromptTemplateController::class, "store"])->name("api.content.prompt-templates.store")->middleware("throttle:20,1");
        Route::get("/api/content/prompt-templates/stats", [PromptTemplateController::class, "stats"])->name("api.content.prompt-templates.stats");
        Route::get("/api/content/prompt-templates/{template}", [PromptTemplateController::class, "show"])->name("api.content.prompt-templates.show");
        Route::put("/api/content/prompt-templates/{template}", [PromptTemplateController::class, "update"])->name("api.content.prompt-templates.update")->middleware("throttle:20,1");
        Route::delete("/api/content/prompt-templates/{template}", [PromptTemplateController::class, "destroy"])->name("api.content.prompt-templates.destroy")->middleware("throttle:20,1");
        Route::post("/api/content/prompt-templates/{template}/render", [PromptTemplateController::class, "render"])->name("api.content.prompt-templates.render")->middleware("throttle:10,1");
        Route::post("/api/content/publish", [ContentApiController::class, "publishToWordPress"])->name("api.content.publish")->middleware("throttle:5,1");
        Route::post("/api/content/test-wp", [ContentApiController::class, "testWordPress"])->name("api.content.test-wp")->middleware("throttle:5,1");
        Route::get("/api/content/drafts", [ContentApiController::class, "listDrafts"])->name("api.content.drafts");
        Route::get("/api/content/drafts/{id}", [ContentApiController::class, "getDraft"])->name("api.content.drafts.show");
        Route::delete("/api/content/drafts/{id}", [ContentApiController::class, "deleteDraft"])->name("api.content.drafts.delete")->middleware("throttle:10,1");
        Route::post("/api/content/apply-suggestions", [ContentApiController::class, "applySuggestions"])->name("api.content.apply-suggestions")->middleware("throttle:10,1");
        Route::post("/api/content/save-user-template", [PromptTemplateController::class, "store"])->name("api.content.save-user-template")->middleware("throttle:20,1");
        Route::post("/api/content/regenerate-section", [ContentApiController::class, "regenerateSection"])->name("api.content.regenerate-section")->middleware("throttle:10,1");});

// بازگشت از درگاه پرداخت — عمومی است چون درگاه به آن ریدایرکت می‌کند (بدون لاگین)
Route::get('/platform/payments/callback/{gateway}/{transaction}', [PlatformPaymentGatewayController::class, 'callback'])->name('platform.payments.callback');

// چالش MFA — بعد از لاگین، قبل از ورود به اتاق فرماندهی (auth دارد ولی پلتفرم ندارد)
Route::middleware(['auth'])->prefix('platform/mfa')->group(function (): void {
    Route::get('/challenge', [PlatformMfaController::class, 'challenge'])->name('platform.mfa.challenge');
    Route::post('/verify', [PlatformMfaController::class, 'verify'])->name('platform.mfa.verify')->middleware('throttle:10,1');
});

// ─── اتاق فرماندهی پلتفرم (Super Admin) — بالای سازمان‌ها ───
Route::middleware(['auth', 'platform.only', 'platform.mfa'])->prefix('platform')->group(function (): void {
    Route::get('/dashboard', PlatformDashboardController::class)->name('platform.dashboard');
    Route::post('/events/{event}/resolve', [PlatformDecisionController::class, 'resolve'])->name('platform.events.resolve')->middleware('throttle:20,1');
    Route::get('/organizations', [PlatformOrganizationController::class, 'index'])->name('platform.organizations.index');
    Route::get('/organizations/{organization}', [PlatformOrganizationController::class, 'show'])->name('platform.organizations.show');
    Route::post('/organizations/{organization}/suspend', [PlatformOrganizationController::class, 'suspend'])->name('platform.organizations.suspend')->middleware('throttle:20,1', 'impersonation.readonly');
    Route::post('/organizations/{organization}/activate', [PlatformOrganizationController::class, 'activate'])->name('platform.organizations.activate')->middleware('throttle:20,1', 'impersonation.readonly');
    Route::post('/organizations/{organization}/impersonate/{user}', [PlatformImpersonationController::class, 'start'])->name('platform.organizations.impersonate')->middleware('throttle:10,1');
    Route::post('/impersonation/stop', [PlatformImpersonationController::class, 'stop'])->name('platform.impersonation.stop')->middleware('throttle:10,1');
    Route::get('/operations', PlatformOperationsController::class)->name('platform.operations');
    Route::post('/emergency-stop', [PlatformEmergencyController::class, 'store'])->name('platform.emergency-stop')->middleware('throttle:5,1', 'impersonation.readonly');

    Route::get('/plans', [PlatformBillingController::class, 'plans'])->name('platform.plans');
    Route::post('/plans', [PlatformBillingController::class, 'storePlan'])->name('platform.plans.store')->middleware('throttle:20,1', 'impersonation.readonly');
    Route::post('/plans/{plan}/toggle', [PlatformBillingController::class, 'togglePlan'])->name('platform.plans.toggle')->middleware('throttle:20,1', 'impersonation.readonly');

    Route::get('/subscriptions', [PlatformBillingController::class, 'subscriptions'])->name('platform.subscriptions');
    Route::post('/subscriptions', [PlatformBillingController::class, 'storeSubscription'])->name('platform.subscriptions.store')->middleware('throttle:20,1', 'impersonation.readonly');
    Route::post('/subscriptions/{subscription}/action', [PlatformBillingController::class, 'subscriptionAction'])->name('platform.subscriptions.action')->middleware('throttle:20,1', 'impersonation.readonly');

    Route::get('/payments', [PlatformBillingController::class, 'payments'])->name('platform.payments');
    Route::post('/payments', [PlatformBillingController::class, 'storePayment'])->name('platform.payments.store')->middleware('throttle:20,1', 'impersonation.readonly');
    Route::post('/payments/{payment}/action', [PlatformBillingController::class, 'paymentAction'])->name('platform.payments.action')->middleware('throttle:20,1', 'impersonation.readonly');

    Route::get('/invoices', [PlatformBillingController::class, 'invoices'])->name('platform.invoices');
    Route::post('/invoices', [PlatformBillingController::class, 'storeInvoice'])->name('platform.invoices.store')->middleware('throttle:20,1', 'impersonation.readonly');
    Route::post('/invoices/overdue-check', [PlatformBillingController::class, 'runOverdueCheck'])->name('platform.invoices.overdue-check')->middleware('throttle:10,1', 'impersonation.readonly');

    Route::get('/reports', [PlatformReportController::class, 'index'])->name('platform.reports');
    Route::get('/reports/export', [PlatformReportController::class, 'exportCsv'])->name('platform.reports.export');

    Route::get('/sms', [PlatformSmsController::class, 'index'])->name('platform.sms');
    Route::post('/sms', [PlatformSmsController::class, 'send'])->name('platform.sms.send')->middleware('throttle:10,1', 'impersonation.readonly');

    Route::get('/mfa', [PlatformMfaController::class, 'index'])->name('platform.mfa.settings');
    Route::post('/mfa/setup', [PlatformMfaController::class, 'setup'])->name('platform.mfa.setup')->middleware('throttle:10,1', 'impersonation.readonly');
    Route::post('/mfa/enable', [PlatformMfaController::class, 'enable'])->name('platform.mfa.enable')->middleware('throttle:10,1', 'impersonation.readonly');
    Route::post('/mfa/disable', [PlatformMfaController::class, 'disable'])->name('platform.mfa.disable')->middleware('throttle:10,1', 'impersonation.readonly');
    Route::post('/mfa/require', [PlatformMfaController::class, 'toggleRequirement'])->name('platform.mfa.require')->middleware('throttle:10,1', 'impersonation.readonly');

    Route::post('/payments/{payment}/pay/{gateway}', [PlatformPaymentGatewayController::class, 'pay'])->name('platform.payments.pay')->middleware('throttle:20,1', 'impersonation.readonly');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('/_design-system', fn () => Inertia::render('Development/DesignSystem'))->name('development.design-system');
    Route::get('/_localization', fn () => Inertia::render('Development/Localization'))->name('development.localization');
}
