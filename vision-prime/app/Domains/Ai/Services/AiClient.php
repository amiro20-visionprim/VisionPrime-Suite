<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

use App\Domains\Organization\Models\Organization;

/**
 * Client for AI content generation.
 *
 * NOW DELEGATES TO AiGateway for automatic failover.
 * This class maintains backward compatibility with existing code.
 */
class AiClient
{
    public const PROVIDERS = ['openai', 'openrouter', 'deepseek', 'anthropic', 'gapgpt'];

    public function __construct(private readonly AiGateway $gateway) {}

    /**
     * @param  array<string, mixed>  
     * @return array{content: string, model: string, source: string, usage: array<string, mixed>}
     */
    public function generateMetaDraft(Organization $org, array $context): array
    {
        return $this->gateway->generateMetaDraft($org, $context);
    }

    /**
     * Generate article draft — delegates to AiGateway with failover.
     */
    public function generateArticleDraft(Organization $org, array $context): array
    {
        return $this->gateway->generateArticleDraft($org, $context);
    }
}
