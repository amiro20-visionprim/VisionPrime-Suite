<?php
declare(strict_types=1);
namespace App\Domains\Content\Models;
use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    protected $fillable = [
        "title", "slug", "content_type", "subtype", "tone",
        "system_prompt", "user_prompt_template", "usage_count",
        "avg_quality_score", "is_featured", "is_active", "tags",
    ];
    protected $casts = [
        "tags" => "array",
        "is_featured" => "boolean",
        "is_active" => "boolean",
        "usage_count" => "integer",
        "avg_quality_score" => "float",
    ];
    public function scopeActive($q) { return $q->where("is_active", true); }
    public function scopeFeatured($q) { return $q->where("is_featured", true); }
    public function scopeForType($q, string $type) { return $q->where("content_type", $type); }
    public function incrementUsage(float $qualityScore = 0): void
    {
        $this->increment("usage_count");
        $current = $this->avg_quality_score;
        $count = $this->usage_count;
        $this->update(["avg_quality_score" => ($current * ($count - 1) + $qualityScore) / $count]);
    }
    public function render(string $title): string
    {
        return str_replace("{title}", $title, $this->user_prompt_template);
    }
}