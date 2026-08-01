<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketingRoutesTest extends TestCase
{
    #[DataProvider('publicRoutes')]
    public function test_public_marketing_pages_are_available(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function publicRoutes(): array
    {
        return [
            'home' => ['/'],
            'product' => ['/product'],
            'features' => ['/features'],
            'pricing' => ['/pricing'],
            'demo' => ['/demo'],
            'security' => ['/security'],
            'about' => ['/about'],
            'contact' => ['/contact'],
        ];
    }
}
