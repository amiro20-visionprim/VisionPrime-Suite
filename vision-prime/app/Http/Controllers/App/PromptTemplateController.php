<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Content\Models\PromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PromptTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PromptTemplate::active();
        if ($type = $request->input("content_type")) {
            $query->forType($type);
        }
        if ($featured = $request->boolean("featured")) {
            $query->featured();
        }
        return response()->json($query->orderByDesc("usage_count")->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "title" => "required|string|max:255",
            "content_type" => "required|string|in:article,product",
            "subtype" => "nullable|string|max:100",
            "tone" => "nullable|string|max:50",
            "system_prompt" => "required|string",
            "user_prompt_template" => "required|string",
            "tags" => "nullable|array",
            "is_featured" => "boolean",
        ]);
        $validated["slug"] = \Str::slug($validated["title"]);
        $template = PromptTemplate::create($validated);
        return response()->json($template, 201);
    }

    public function show(PromptTemplate $template): JsonResponse
    {
        return response()->json($template);
    }

    public function update(Request $request, PromptTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            "title" => "sometimes|string|max:255",
            "content_type" => "sometimes|string|in:article,product",
            "subtype" => "nullable|string|max:100",
            "tone" => "nullable|string|max:50",
            "system_prompt" => "sometimes|string",
            "user_prompt_template" => "sometimes|string",
            "tags" => "nullable|array",
            "is_featured" => "boolean",
            "is_active" => "boolean",
        ]);
        $template->update($validated);
        return response()->json($template);
    }

    public function destroy(PromptTemplate $template): JsonResponse
    {
        $template->delete();
        return response()->json(["ok" => true]);
    }

    public function render(Request $request, PromptTemplate $template): JsonResponse
    {
        $title = $request->input("title", "");
        return response()->json([
            "rendered_prompt" => $template->render($title),
            "system_prompt" => $template->system_prompt,
        ]);
    }

    public function stats(): JsonResponse
    {
        $templates = PromptTemplate::active()->orderByDesc("avg_quality_score")->get();
        return response()->json([
            "total" => $templates->count(),
            "featured" => $templates->where("is_featured", true)->count(),
            "avg_score" => $templates->avg("avg_quality_score"),
            "total_usage" => $templates->sum("usage_count"),
            "top_templates" => $templates->take(5)->map(fn($t) => [
                "id" => $t->id,
                "title" => $t->title,
                "usage_count" => $t->usage_count,
                "avg_quality_score" => round($t->avg_quality_score, 1),
            ]),
        ]);
    }
}
