<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use App\Domains\Seo\Services\RuleBasedIntentClassifier;
use Tests\TestCase;

class RuleBasedIntentClassifierTest extends TestCase
{
    public function test_classifies_persian_and_english_intents(): void
    {
        $c = app(RuleBasedIntentClassifier::class);
        $this->assertSame('transactional', $c->classify('خرید لپ تاپ')['intent']);
        $this->assertSame('commercial', $c->classify('best seo tool')['intent']);
        $this->assertSame('informational', $c->classify('آموزش سئو چیست')['intent']);
        $this->assertSame('local', $c->classify('کلینیک در تهران')['intent']);
        $this->assertSame('unknown', $c->classify('عبارت نامشخص')['intent']);
    }
}
