<?php

namespace App\Support;

use App\Models\User;

readonly class InventoryContext
{
    public function __construct(
        public User $actor,
        public string $operationKey,
        public string $referenceType,
        public int $referenceId,
        public ?string $reason = null,
        public ?string $notes = null,
    ) {}
}
