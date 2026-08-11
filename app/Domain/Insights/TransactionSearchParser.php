<?php

namespace App\Domain\Insights;

use App\Enums\ServiceType;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * PRD §11/§17 "natural-language transaction search" — a keyword/pattern
 * parser over the query string, not an LLM call (see InsightService's
 * docblock for why). Understands service type, status, amount
 * comparisons ("over 5000"), and relative dates ("last week"); anything
 * left over falls back to a plain substring search across reference,
 * recipient, and biller identifier. Good enough for queries like "failed
 * airtime last week" or "electricity over 5000 this month" without
 * needing a real NLP model.
 */
class TransactionSearchParser
{
    public function search(int $userId, string $query): Builder
    {
        $remaining = ' '.strtolower(trim($query)).' ';
        $builder = Transaction::where('user_id', $userId)->with(['meter', 'biller']);

        $remaining = $this->matchServiceType($builder, $remaining);
        $remaining = $this->matchStatus($builder, $remaining);
        $remaining = $this->matchAmount($builder, $remaining);
        $remaining = $this->matchDateRange($builder, $remaining);

        $leftover = trim(preg_replace('/\s+/', ' ', $remaining));
        // Strip filler words so "for my ikeja meter" leaves just "ikeja meter".
        $leftover = trim(preg_replace('/\b(for|my|the|a|an|of|in|on|to)\b/', '', $leftover));
        $leftover = trim(preg_replace('/\s+/', ' ', $leftover));

        if ($leftover !== '') {
            $builder->where(function (Builder $q) use ($leftover) {
                $q->where('reference', 'like', "%{$leftover}%")
                    ->orWhere('recipient_name', 'like', "%{$leftover}%")
                    ->orWhere('recipient_phone', 'like', "%{$leftover}%")
                    ->orWhere('biller_identifier', 'like', "%{$leftover}%")
                    ->orWhereHas('meter', fn ($m) => $m->where('label', 'like', "%{$leftover}%")->orWhere('meter_number', 'like', "%{$leftover}%"))
                    ->orWhereHas('biller', fn ($b) => $b->where('name', 'like', "%{$leftover}%"));
            });
        }

        return $builder->orderByDesc('created_at');
    }

    protected function matchServiceType(Builder $builder, string $text): string
    {
        $map = [
            'electricity' => ServiceType::Electricity, 'token' => ServiceType::Electricity, 'power' => ServiceType::Electricity, 'light' => ServiceType::Electricity,
            'airtime' => ServiceType::Airtime, 'recharge card' => ServiceType::Airtime,
            'data' => ServiceType::Data, 'bundle' => ServiceType::Data, 'internet' => ServiceType::Data,
            'cable' => ServiceType::CableTv, 'tv' => ServiceType::CableTv, 'dstv' => ServiceType::CableTv, 'gotv' => ServiceType::CableTv,
            'education' => ServiceType::Education, 'exam' => ServiceType::Education, 'waec' => ServiceType::Education, 'jamb' => ServiceType::Education,
        ];

        foreach ($map as $keyword => $serviceType) {
            if (str_contains($text, " {$keyword} ")) {
                $builder->where('service_type', $serviceType);
                $text = str_replace(" {$keyword} ", ' ', $text);
                break;
            }
        }

        return $text;
    }

    protected function matchStatus(Builder $builder, string $text): string
    {
        $map = [
            'failed' => [TransactionStatus::Failed], 'unsuccessful' => [TransactionStatus::Failed], 'declined' => [TransactionStatus::Failed],
            'successful' => [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed], 'success' => [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed], 'delivered' => [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed], 'completed' => [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed],
            'pending' => [TransactionStatus::PaymentInitiated, TransactionStatus::PaymentReceived, TransactionStatus::PaymentConfirmed, TransactionStatus::GeneratingToken, TransactionStatus::TokenGenerated],
            'processing' => [TransactionStatus::PaymentInitiated, TransactionStatus::PaymentReceived, TransactionStatus::PaymentConfirmed, TransactionStatus::GeneratingToken, TransactionStatus::TokenGenerated],
        ];

        foreach ($map as $keyword => $statuses) {
            if (str_contains($text, " {$keyword} ")) {
                $builder->whereIn('status', $statuses);
                $text = str_replace(" {$keyword} ", ' ', $text);
                break;
            }
        }

        return $text;
    }

    protected function matchAmount(Builder $builder, string $text): string
    {
        // "over/above/more than 5000", "under/below/less than 2000" — the
        // /u modifier is required here: ₦ is a multi-byte UTF-8 character,
        // and without it PCRE's byte-oriented matching only makes the
        // *last byte* of "₦?" optional, so the pattern never matches text
        // without a literal ₦ in it.
        if (preg_match('/\b(over|above|more than)\s+₦?([\d,]+)\b/u', $text, $m)) {
            $builder->where('amount', '>=', (float) str_replace(',', '', $m[2]));
            $text = str_replace($m[0], ' ', $text);
        } elseif (preg_match('/\b(under|below|less than)\s+₦?([\d,]+)\b/u', $text, $m)) {
            $builder->where('amount', '<=', (float) str_replace(',', '', $m[2]));
            $text = str_replace($m[0], ' ', $text);
        }

        return $text;
    }

    protected function matchDateRange(Builder $builder, string $text): string
    {
        $ranges = [
            'today' => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
            'yesterday' => [Carbon::now()->subDay()->startOfDay(), Carbon::now()->subDay()->endOfDay()],
            'this week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'last week' => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
            'this month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'last month' => [Carbon::now()->subMonthNoOverflow()->startOfMonth(), Carbon::now()->subMonthNoOverflow()->endOfMonth()],
        ];

        // Longer phrases first so "last week" doesn't get shadowed by a
        // hypothetical shorter "week" entry.
        uksort($ranges, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($ranges as $phrase => [$from, $to]) {
            if (str_contains($text, " {$phrase} ")) {
                $builder->whereBetween('created_at', [$from, $to]);
                $text = str_replace(" {$phrase} ", ' ', $text);
                break;
            }
        }

        return $text;
    }
}
