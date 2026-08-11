<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>JolaxPay Wallet Receipt {{ $entry->reference }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; font-size: 12px; }
        .header { border-bottom: 2px solid #7a1f2b; padding-bottom: 12px; margin-bottom: 16px; }
        .header h1 { color: #7a1f2b; font-size: 20px; margin: 0; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td, th { text-align: left; padding: 6px 0; border-bottom: 1px solid #eee; }
        .total-row td { font-weight: bold; border-top: 2px solid #1a1a1a; border-bottom: none; }
        .amount-credit { color: #138A4A; }
        .amount-debit { color: #B42318; }
        .footer { margin-top: 24px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h1>JolaxPay</h1>
        <div>Wallet Receipt</div>
    </div>

    @php
        $reasonLabels = [
            'purchase' => 'Purchase debit',
            'refund' => 'Refund',
            'wallet_funding' => 'Wallet funding',
            'referral_reward' => 'Referral reward',
            'adjustment' => 'Adjustment',
            'transfer_out' => 'Wallet transfer sent',
            'transfer_in' => 'Wallet transfer received',
            'withdrawal' => 'Withdrawal to bank',
            'withdrawal_reversal' => 'Withdrawal reversal',
        ];
        $reasonValue = $entry->reason instanceof \App\Enums\LedgerReason ? $entry->reason->value : $entry->reason;
        $typeValue = $entry->type instanceof \App\Enums\LedgerEntryType ? $entry->type->value : $entry->type;
        $isCredit = $typeValue === 'credit';
    @endphp

    <div class="row"><span>Reference</span><strong>{{ $entry->reference }}</strong></div>
    <div class="row"><span>Date</span><span>{{ $entry->created_at?->format('d M Y, H:i') }}</span></div>
    <div class="row"><span>Type</span><span>{{ $reasonLabels[$reasonValue] ?? ucfirst(str_replace('_', ' ', $reasonValue)) }}</span></div>
    <div class="row"><span>Wallet</span><span>{{ $wallet->wallet_address }}</span></div>
    @if($entry->transaction)
        <div class="row"><span>Related transaction</span><span>{{ $entry->transaction->reference }}</span></div>
    @endif

    <table>
        <tr><th>Description</th><th style="text-align:right">Amount</th></tr>
        <tr>
            <td>{{ $reasonLabels[$reasonValue] ?? ucfirst(str_replace('_', ' ', $reasonValue)) }}</td>
            <td style="text-align:right" class="{{ $isCredit ? 'amount-credit' : 'amount-debit' }}">
                {{ $isCredit ? '+' : '-' }}{{ $entry->currency }} {{ number_format((float) $entry->amount, 2) }}
            </td>
        </tr>
        <tr class="total-row">
            <td>Balance after</td>
            <td style="text-align:right">{{ $entry->currency }} {{ number_format((float) $entry->balance_after, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        JolaxPay — this receipt confirms a wallet ledger entry. For support, contact us from the app.
    </div>
</body>
</html>
