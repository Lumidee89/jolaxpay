<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\Events\SupportMessageSent;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Tickets, linked transaction/user (User Journey §7). */
class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = SupportTicket::with(['user:id,full_name,email', 'assignee:id,full_name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(SupportTicket $supportTicket): Response
    {
        return Inertia::render('Admin/Support/Show', [
            'ticket' => $supportTicket->load(['user', 'transaction', 'messages.author', 'assignee']),
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $message = $supportTicket->messages()->create([
            'author_id' => auth()->id(),
            'is_staff_reply' => true,
            'body' => $request->input('body'),
        ]);

        $supportTicket->update(['status' => 'pending']);

        broadcast(new SupportMessageSent($message->load('author')))->toOthers();

        return back()->with('success', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $request->validate(['status' => 'required|in:open,pending,resolved,closed']);

        $supportTicket->update([
            'status' => $request->input('status'),
            'resolved_at' => $request->input('status') === 'resolved' ? now() : $supportTicket->resolved_at,
            'assigned_to' => $supportTicket->assigned_to ?? auth()->id(),
        ]);

        return back()->with('success', 'Ticket updated.');
    }
}
