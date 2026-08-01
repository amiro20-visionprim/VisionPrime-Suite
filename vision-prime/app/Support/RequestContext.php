<?php

declare(strict_types=1);

namespace App\Support;

class RequestContext
{
    private ?string $requestId = null;

    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }
}
