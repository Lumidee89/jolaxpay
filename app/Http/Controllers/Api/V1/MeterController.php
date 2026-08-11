<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Vending\VendingManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeterRequest;
use App\Http\Requests\Api\V1\UpdateMeterRequest;
use App\Http\Requests\Api\V1\VerifyMeterRequest;
use App\Http\Resources\MeterResource;
use App\Models\Disco;
use App\Models\Meter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Saved Meters (PRD §7.2). */
class MeterController extends Controller
{
    public function __construct(private readonly VendingManager $vending) {}

    public function index(Request $request): JsonResponse
    {
        $meters = $request->user()->meters()
            ->where('is_saved', true)
            ->with('disco')
            ->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => MeterResource::collection($meters)]);
    }

    public function store(StoreMeterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $meter = $request->user()->meters()->firstOrCreate(
            ['meter_number' => $data['meter_number']],
            $data,
        );

        // A previously one-time meter becomes visible in saved meters when
        // the user explicitly chooses to save it on a later purchase.
        if (($data['is_saved'] ?? false) && ! $meter->is_saved) {
            $meter->update([...$data, 'is_saved' => true]);
        }

        // create() doesn't reflect column defaults applied at the DB level
        // (meter_type, is_favorite) — refresh so the response is accurate.
        return response()->json(['data' => MeterResource::make($meter->refresh()->load('disco'))], 201);
    }

    public function show(Request $request, Meter $meter): JsonResponse
    {
        $this->authorizeOwnership($request, $meter);

        return response()->json(['data' => MeterResource::make($meter->load('disco'))]);
    }

    public function update(UpdateMeterRequest $request, Meter $meter): JsonResponse
    {
        $this->authorizeOwnership($request, $meter);

        $meter->update($request->validated());

        return response()->json(['data' => MeterResource::make($meter->fresh('disco'))]);
    }

    public function destroy(Request $request, Meter $meter): JsonResponse
    {
        $this->authorizeOwnership($request, $meter);
        $meter->delete();

        return response()->json(['message' => 'Meter removed.']);
    }

    public function toggleFavorite(Request $request, Meter $meter): JsonResponse
    {
        $this->authorizeOwnership($request, $meter);
        $meter->update(['is_favorite' => ! $meter->is_favorite]);

        return response()->json(['data' => MeterResource::make($meter->fresh('disco'))]);
    }

    /**
     * Pre-purchase (or pre-save) meter check: shows the customer name on
     * the meter before the user commits to it, catching typos before a
     * payment is made (PRD §7.9 Price Transparency extends naturally to
     * "who am I paying").
     */
    public function verify(VerifyMeterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $disco = Disco::findOrFail($data['disco_id']);

        $transientMeter = new Meter([
            'disco_id' => $disco->id,
            'meter_number' => $data['meter_number'],
            'meter_type' => $data['meter_type'] ?? 'prepaid',
            'label' => 'verification-only',
        ]);
        $transientMeter->setRelation('disco', $disco);

        $result = $this->vending->electricityDriverFor()->verifyMeter($transientMeter);

        return response()->json([
            'valid' => $result->valid,
            'customer_name' => $result->customerName,
            'address' => $result->address,
            'minimum_amount' => $result->minimumAmount,
            'message' => $result->message,
        ], $result->valid ? 200 : 422);
    }

    protected function authorizeOwnership(Request $request, Meter $meter): void
    {
        abort_unless($meter->user_id === $request->user()->id, 403);
    }
}
