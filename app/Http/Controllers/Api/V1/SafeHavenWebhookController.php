<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\SafeHavenFundingService;
use App\Domain\Payments\SafeHavenGateway;
use App\Http\Controllers\Controller;
use App\Models\WalletFundingIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafeHavenWebhookController extends Controller
{
    public function __construct(private readonly SafeHavenFundingService $funding, private readonly SafeHavenGateway $safeHaven) {}

    public function handle(Request $request): JsonResponse
    {
        $data = $request->input('data', []);
        $virtualId = $data['virtualAccount'] ?? null;
        $intent = WalletFundingIntent::where('reference', $data['externalReference'] ?? '')
            ->when($virtualId, fn ($query) => $query->orWhere('meta->virtual_account_id', $virtualId))->first();
        if ($intent && in_array($request->input('type'), ['virtualAccount.transfer', 'checkout.transfer'], true)) {
            // Never trust a public webhook body as proof of payment. Safe Haven's
            // API is queried server-to-server and only that response can credit a wallet.
            $verified = $virtualId
                ? $this->safeHaven->virtualAccountTransaction($virtualId)
                : $this->safeHaven->verifyCheckout($intent->reference);
            if ($verified) $this->funding->confirm($intent->reference, $verified);
        }
        return response()->json(['received' => true]);
    }
}
