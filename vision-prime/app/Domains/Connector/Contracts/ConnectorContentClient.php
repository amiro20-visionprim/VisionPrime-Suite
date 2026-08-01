<?php

declare(strict_types=1);

namespace App\Domains\Connector\Contracts;

interface ConnectorContentClient
{
    /** @return array<string,mixed> */
    public function get(object $connection, string $path, array $query = []): array;
}
