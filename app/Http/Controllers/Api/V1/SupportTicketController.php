<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Support\Events\SupportMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSupportTicketMessageRequest;
use App\Http\Requests\Api\V1\StoreSupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Support ticketing (PRD §7.13). */
class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()->latest()->get();

        return response()->json(['data' => SupportTicketResource::collection($tickets)]);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $data = $request->validated();

        $ticket = $request->user()->supportTickets()->create([
            'transaction_id' => $data['transaction_id'] ?? null,
            'subject' => $data['subject'],
            'priority' => $data['priority'] ?? 'normal',
            'category' => $this->categoryFor($data['subject'], $data['message']),
            'status' => 'open',
        ]);

        $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'is_staff_reply' => false,
            'body' => $data['message'],
        ]);

        return response()->json(['data' => SupportTicketResource::make($ticket->load('messages.author'))], 201);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorizeAccess($request, $supportTicket);

        return response()->json(['data' => SupportTicketResource::make($supportTicket->load('messages.author'))]);
    }

    public function addMessage(StoreSupportTicketMessageRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorizeAccess($request, $supportTicket);

        $message = $supportTicket->messages()->create([
            'author_id' => $request->user()->id,
            'is_staff_reply' => $request->user()->isStaff(),
            'body' => $request->validated('body'),
        ]);
        $message->load('author');

        if ($supportTicket->status === 'resolved' || $supportTicket->status === 'closed') {
            $supportTicket->update(['status' => 'open']);
        }

        broadcast(new SupportMessageSent($message))->toOthers();

        return response()->json(['data' => \App\Http\Resources\SupportTicketMessageResource::make($message)], 201);
    }

    protected function authorizeAccess(Request $request, SupportTicket $supportTicket): void
    {
        $user = $request->user();
        abort_unless($supportTicket->user_id === $user->id || $user->isStaff(), 403);
    }

    /**
     * PRD §23 "support tickets broken down by category" — a keyword
     * heuristic over the subject+message, not something the buyer picks
     * themselves (extra required field = extra friction on an already
     * stressful "something's wrong" moment). Staff can see/correct it in
     * Admin if it's ever off.
     */
    protected function categoryFor(string $subject, string $message): string
    {
        $text = strtolower($subject.' '.$message);

        $map = [
            'billing' => ['wallet', 'refund', 'charge', 'debit', 'fund', 'withdraw', 'payment', 'card', 'transfer', 'balance'],
            'purchase' => ['token', 'electricity', 'airtime', 'data', 'meter', 'recharge', 'bundle', 'subscription', 'tv'],
            'account' => ['login', 'password', 'otp', 'account', 'session', 'device', 'verify', 'security'],
            'technical' => ['bug', 'error', 'crash', 'not working', 'freeze', 'app', 'glitch'],
        ];

        foreach ($map as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $category;
                }
            }
        }

        return 'other';
    }
}
