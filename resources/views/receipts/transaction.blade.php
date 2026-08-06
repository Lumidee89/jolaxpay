<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>JolaxPay Receipt {{ $transaction->reference }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; font-size: 12px; }
        .header { border-bottom: 2px solid #7a1f2b; padding-bottom: 12px; margin-bottom: 16px; }
        .header h1 { color: #7a1f2b; font-size: 20px; margin: 0; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td, th { text-align: left; padding: 6px 0; border-bottom: 1px solid #eee; }
        .total-row td { font-weight: bold; border-top: 2px solid #1a1a1a; border-bottom: none; }
        .insight { margin-top: 20px; padding: 10px; background: #f7f2f2; border-radius: 4px; color: #444; }
        .footer { margin-top: 24px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h1>JolaxPay</h1>
        <div>Payment Receipt</div>
    </div>

    <div class="row"><span>Reference</span><strong>{{ $transaction->reference }}</strong></div>
    <div class="row"><span>Date</span><span>{{ $transaction->created_at?->format('d M Y, H:i') }}</span></div>
    <div class="row"><span>Status</span><span>{{ $transaction->status->label() }}</span></div>
    @if($transaction->meter)
        <div class="row"><span>Meter</span><span>{{ $transaction->meter->label }} ({{ $transaction->meter->meter_number }})</span></div>
    @endif
    @if($transaction->token)
        <div class="row"><span>Token</span><strong>{{ $transaction->token }}</strong></div>
    @endif

    <table>
        <tr><th>Description</th><th style="text-align:right">Amount</th></tr>
        <tr><td>{{ ucfirst(str_replace('_', ' ', $transaction->service_type->value)) }}</td><td style="text-align:right">{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</td></tr>
        <tr><td>Convenience fee</td><td style="text-align:right">{{ $transaction->currency }} {{ number_format((float) $transaction->fee, 2) }}</td></tr>
        <tr class="total-row"><td>Total</td><td style="text-align:right">{{ $transaction->currency }} {{ number_format((float) $transaction->total(), 2) }}</td></tr>
    </table>

    <div class="insight">
        This is your {{ $purchaseCount }}{{ $purchaseCount == 1 ? 'st' : ($purchaseCount == 2 ? 'nd' : ($purchaseCount == 3 ? 'rd' : 'th')) }} purchase with JolaxPay.
    </div>

    <div class="footer">
        JolaxPay — this receipt confirms payment and, where applicable, token delivery. For support, contact us from the app.
    </div>
</body>
</html>
