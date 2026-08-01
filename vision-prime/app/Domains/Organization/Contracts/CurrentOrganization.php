<?php

declare(strict_types=1);

namespace App\Domains\Organization\Contracts;

use App\Domains\Organization\Models\Organization;
use LogicException;

class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    public function get(): Organization
    {
        if ($this->organization === null) {
            throw new LogicException('No current organization is set for this request.');
        }

        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }
}
