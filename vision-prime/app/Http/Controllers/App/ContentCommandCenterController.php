<?php
declare(strict_types=1);
namespace AppHttpControllersApp;

use AppDomainsOrganizationContractsCurrentOrganization;
use AppDomainsWorkspaceModelsSite;
use AppHttpControllersController;
use IlluminateHttpJsonResponse;
use IlluminateHttpRequest;
use IlluminateSupportFacadesDB;
use InertiaInertia;
use InertiaResponse;

class ContentCommandCenterController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $sites = Site::query()->where("organization_id", $org->id())->orderBy("name")->get(["id","name"])->values()->all();
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        return Inertia::render("App/ContentCommandCenter/Index", ["sites" => $sites, "isSuperAdmin" => $isSuperAdmin]);
    }

    public function performance(Request $request, CurrentOrganization $org): JsonResponse
    {
        $siteId = $request->integer("site_id");
        $days = $request->integer("days", 28);
        $start = now()->subDays($days)->toDateString();
        $q = DB::table("content_performance_metrics")->where("organization_id", $org->id())->where("date", ">=", $start);
        if ($siteId) $q->where("site_id", $siteId);
        $summary = (clone $q)->selectRaw("content_type, SUM(clicks) clicks, SUM(impressions) impressions, AVG(ctr) ctr, AVG(position) position, COUNT(DISTINCT url) pages")->groupBy("content_type")->get();
        $trend = (clone $q)->selectRaw("date, SUM(clicks) clicks, SUM(impressions) impressions, AVG(ctr) ctr")->groupBy("date")->orderBy("date")->get();
        $top = (clone $q)->selectRaw("url, title, content_type, SUM(clicks) clicks, AVG(position) position")->groupBy("url","title","content_type")->orderByDesc("clicks")->limit(20)->get();
        return response()->json(["summary"=>$summary, "trend"=>$trend, "top_pages"=>$top]);
    }

    public function serpAnalyze(Request $request, CurrentOrganization $org): JsonResponse
    {
        $d = $request->validate(["site_id"=>"required|integer","keyword"=>"required|string|max:512","target_url"=>"nullable|string"]);
        $id = DB::table("serp_analyses")->insertGetId(["site_id"=>$d["site_id"],"organization_id"=>$org->id(),"keyword"=>$d["keyword"],"target_url"=>$d["target_url"]??null,"results"=>json_encode([]),"status"=>"pending","created_at"=>now(),"updated_at"=>now()]);
        return response()->json(["id"=>$id,"status"=>"pending"]);
    }

    public function serpResults(Request $request, CurrentOrganization $org): JsonResponse
    {
        $q = DB::table("serp_analyses")->where("organization_id", $org->id());
        if ($request->integer("site_id")) $q->where("site_id", $request->integer("site_id"));
        return response()->json(["analyses" => $q->orderByDesc("created_at")->limit(50)->get()]);
    }

    public function keywords(Request $request, CurrentOrganization $org): JsonResponse
    {
        $q = DB::table("keyword_clusters")->where("organization_id", $org->id())->where("is_active", true);
        if ($request->integer("site_id")) $q->where("site_id", $request->integer("site_id"));
        return response()->json(["clusters" => $q->orderByDesc("total_clicks")->get()]);
    }

    public function calendar(Request $request, CurrentOrganization $org): JsonResponse
    {
        $q = DB::table("content_calendar_items")->where("organization_id", $org->id());
        if ($request->integer("site_id")) $q->where("site_id", $request->integer("site_id"));
        return response()->json(["items" => $q->orderBy("planned_date")->get()]);
    }

    public function calendarStore(Request $request, CurrentOrganization $org): JsonResponse
    {
        $d = $request->validate(["site_id"=>"required|integer","title"=>"required|string","planned_date"=>"required|date","content_type"=>"nullable|string","subtype"=>"nullable|string","priority_score"=>"nullable|numeric","seo_context"=>"nullable|array","content_brief"=>"nullable|array","notes"=>"nullable|string"]);
        $id = DB::table("content_calendar_items")->insertGetId(array_merge($d, ["organization_id"=>$org->id(),"status"=>"planned","created_at"=>now(),"updated_at"=>now()]));
        return response()->json(["id"=>$id]);
    }

    public function experiments(Request $request, CurrentOrganization $org): JsonResponse
    {
        $q = DB::table("content_experiments")->where("organization_id", $org->id());
        if ($request->integer("site_id")) $q->where("site_id", $request->integer("site_id"));
        return response()->json(["experiments" => $q->orderByDesc("created_at")->get()]);
    }

    public function suggestions(Request $request, CurrentOrganization $org): JsonResponse
    {
        $q = DB::table("smart_content_suggestions")->where("organization_id", $org->id())->where("status", $request->string("status")->toString() ?: "pending");
        if ($request->integer("site_id")) $q->where("site_id", $request->integer("site_id"));
        if ($t = $request->string("type")->toString()) $q->where("suggestion_type", $t);
        $stats = DB::table("smart_content_suggestions")->where("organization_id", $org->id())->selectRaw("status, COUNT(*) c")->groupBy("status")->pluck("c","status");
        return response()->json(["suggestions"=>$q->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 ELSE 3 END")->limit(50)->get(), "stats"=>$stats]);
    }

    public function suggestionAction(Request $request, CurrentOrganization $org, int $id): JsonResponse
    {
        $s = $request->validate(["status"=>"required|in:pending,accepted,rejected,implemented"]);
        DB::table("smart_content_suggestions")->where("id", $id)->where("organization_id", $org->id())->update(["status"=>$s["status"],"updated_at"=>now()]);
        return response()->json(["ok"=>true]);
    }
}