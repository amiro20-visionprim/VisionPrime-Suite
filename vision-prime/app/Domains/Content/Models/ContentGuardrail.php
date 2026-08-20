<?php

declare(strict_types=1);

namespace App\Domains\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Site;

/**
 * Content Guardrails — control what the AI generates per subtype/site.
 *
 * Each guardrail defines:
 *   - structural limits (word count, tags, etc.)
 *   - content requirements (CTA, FAQ, internal links, brand mention)
 *   - custom system/user prompts that override the defaults
 *   - forbidden words
 *
 * Resolution priority: site-specific → org-wide → defaults
 */
class ContentGuardrail extends Model
{
    protected $fillable = [
        'organization_id',
        'site_id',
        'content_type',
        'subtype',
        'max_characters',
        'min_words',
        'max_words',
        'allowed_tone',
        'allowed_tags',
        'require_cta',
        'require_faq',
        'require_internal_links',
        'min_internal_links',
        'require_brand_mention',
        'forbidden_words',
        'system_prompt',
        'user_prompt_template',
        'is_active',
    ];

    protected $casts = [
        'allowed_tags' => 'array',
        'forbidden_words' => 'array',
        'require_cta' => 'boolean',
        'require_faq' => 'boolean',
        'require_internal_links' => 'boolean',
        'require_brand_mention' => 'boolean',
        'is_active' => 'boolean',
        'max_characters' => 'integer',
        'min_words' => 'integer',
        'max_words' => 'integer',
        'min_internal_links' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Resolve guardrails for a specific org/site/content_type/subtype.
     * Priority: site-specific → org-wide → hardcoded defaults.
     */
    public static function resolve(int $organizationId, ?int $siteId, string $contentType, string $subtype): self
    {
        // 1. Try site-specific
        $guardrail = self::where('organization_id', $organizationId)
            ->where('site_id', $siteId)
            ->where('content_type', $contentType)
            ->where('subtype', $subtype)
            ->where('is_active', true)
            ->first();

        if ($guardrail !== null) {
            return $guardrail;
        }

        // 2. Try org-wide (site_id = null)
        $guardrail = self::where('organization_id', $organizationId)
            ->whereNull('site_id')
            ->where('content_type', $contentType)
            ->where('subtype', $subtype)
            ->where('is_active', true)
            ->first();

        if ($guardrail !== null) {
            return $guardrail;
        }

        // 3. Return default
        return self::defaults($contentType, $subtype);
    }

    /**
     * Hardcoded defaults for when no guardrail is configured.
     */
    public static function defaults(string $contentType, string $subtype): self
    {
        return new self([
            'content_type' => $contentType,
            'subtype' => $subtype,
            'max_characters' => 8000,
            'min_words' => 400,
            'max_words' => 2000,
            'allowed_tone' => 'informative',
            'allowed_tags' => ['h1', 'h2', 'h3', 'p', 'ul', 'ol', 'table', 'strong', 'a', 'img'],
            'require_cta' => true,
            'require_faq' => in_array($subtype, ['how_to', 'guide', 'review'], true),
            'require_internal_links' => true,
            'min_internal_links' => 2,
            'require_brand_mention' => true,
            'forbidden_words' => [],
            'system_prompt' => null,
            'user_prompt_template' => null,
        ]);
    }

    /**
     * Convert to array for prompt injection.
     */
    public function toPromptArray(): array
    {
        return [
            'max_characters' => $this->max_characters,
            'min_words' => $this->min_words,
            'max_words' => $this->max_words,
            'allowed_tone' => $this->allowed_tone,
            'allowed_tags' => $this->allowed_tags ?? ['h1', 'h2', 'h3', 'p', 'ul', 'ol', 'table', 'strong', 'a'],
            'require_cta' => $this->require_cta,
            'require_faq' => $this->require_faq,
            'require_internal_links' => $this->require_internal_links,
            'min_internal_links' => $this->min_internal_links,
            'require_brand_mention' => $this->require_brand_mention,
            'forbidden_words' => $this->forbidden_words ?? [],
            'system_prompt' => $this->system_prompt,
            'user_prompt_template' => $this->user_prompt_template,
        ];
    }
}
