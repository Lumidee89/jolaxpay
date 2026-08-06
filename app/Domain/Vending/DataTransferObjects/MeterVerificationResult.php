<?php

namespace App\Domain\Vending\DataTransferObjects;

/**
 * Result of a pre-purchase meter/biller lookup (VTpass's "merchant-verify").
 * Lets the mobile app show the customer's name before they confirm a
 * payment — the meter-number equivalent of a name check, and cheap
 * insurance against a mistyped meter number (PRD §7.9 Price Transparency
 * extends naturally to "who am I paying").
 */
final readonly class MeterVerificationResult
{
    public function __construct(
        public bool $valid,
        public ?string $customerName = null,
        public ?string $address = null,
        public ?string $minimumAmount = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
