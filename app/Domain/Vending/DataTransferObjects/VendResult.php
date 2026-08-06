<?php

namespace App\Domain\Vending\DataTransferObjects;

/** Result of a single vend attempt against a DisCo/telecom provider. */
final readonly class VendResult
{
    public function __construct(
        public bool $successful,
        public ?string $token = null,
        public ?string $providerReference = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
