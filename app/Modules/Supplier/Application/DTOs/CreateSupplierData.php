<?php

namespace App\Modules\Supplier\Application\DTOs;

class CreateSupplierData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly string $status,
        public readonly ?int $maxUsers,
        public readonly ?string $contactName,
        public readonly ?string $email,
        public readonly ?string $phone,
    ) {}
}
