<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Contracts;

use App\Domains\Workspace\Models\Client;
use LogicException;

class CurrentClient
{
    private ?Client $client = null;

    public function set(Client $client): void
    {
        $this->client = $client;
    }

    public function clear(): void
    {
        $this->client = null;
    }

    public function has(): bool
    {
        return $this->client !== null;
    }

    public function get(): Client
    {
        if ($this->client === null) {
            throw new LogicException('No current client is set for this request.');
        }

        return $this->client;
    }

    public function id(): ?int
    {
        return $this->client?->getKey();
    }
}
