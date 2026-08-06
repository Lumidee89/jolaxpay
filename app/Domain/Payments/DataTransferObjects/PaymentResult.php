<?php

namespace App\Domain\Payments\DataTransferObjects;

final readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public ?string $processorReference = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
