<?php

namespace App\Enums;

/**
 * Mirrors the Payment Flow state machine (TRD §6). Ordered so `cases()`
 * reflects the happy-path sequence; `Failed` is the only non-linear
 * terminal state, reachable from any in-flight stage.
 */
enum TransactionStatus: string
{
    case FeeDisclosed = 'fee_disclosed';
    case PaymentInitiated = 'payment_initiated';
    case PaymentReceived = 'payment_received';
    case PaymentConfirmed = 'payment_confirmed';
    case GeneratingToken = 'generating_token';
    case TokenGenerated = 'token_generated';
    case Delivered = 'delivered';
    case OutcomeConfirmed = 'outcome_confirmed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::FeeDisclosed => 'Fee Disclosed',
            self::PaymentInitiated => 'Payment Initiated',
            self::PaymentReceived => 'Payment Received',
            self::PaymentConfirmed => 'Payment Confirmed',
            self::GeneratingToken => 'Generating Token',
            self::TokenGenerated => 'Token Generated',
            self::Delivered => 'Delivered',
            self::OutcomeConfirmed => 'Outcome Confirmed',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::OutcomeConfirmed || $this === self::Failed;
    }

    /** The stages the transaction is allowed to move to from this one. */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::FeeDisclosed => [self::PaymentInitiated, self::Failed],
            self::PaymentInitiated => [self::PaymentReceived, self::Failed],
            self::PaymentReceived => [self::PaymentConfirmed, self::Failed],
            self::PaymentConfirmed => [self::GeneratingToken, self::Failed],
            self::GeneratingToken => [self::TokenGenerated, self::Failed],
            self::TokenGenerated => [self::Delivered, self::Failed],
            self::Delivered => [self::OutcomeConfirmed, self::Failed],
            self::OutcomeConfirmed, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStates(), true);
    }
}
