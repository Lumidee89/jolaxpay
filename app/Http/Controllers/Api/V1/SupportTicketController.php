<?php

namespace App\Http\Controllers\Api\V1;

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

        if ($supportTicket->status === 'resolved' || $supportTicket->status === 'closed') {
            $supportTicket->update(['status' => 'open']);
        }

        return response()->json(['data' => \App\Http\Resources\SupportTicketMessageResource::make($message->load('author'))], 201);
    }

    protected function authorizeAccess(Request $request, SupportTicket $supportTicket): void
    {
        $user = $request->user();
        abort_unless($supportTicket->user_id === $user->id || $user->isStaff(), 403);
    }
}
