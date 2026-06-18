<?php

namespace App\Modules\Promotions\Application\DTOs;

class ListPromotionsFilter
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $status = null,
        public readonly ?string $search = null,
        public readonly int $perPage = 10,
    ) {}
}
