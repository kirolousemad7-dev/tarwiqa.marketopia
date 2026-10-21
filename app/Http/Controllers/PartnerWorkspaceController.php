<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Maid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PartnerWorkspaceController extends Controller
{
    public function orders(Request $request): View
    {
        $partner = $request->user();

        return view('partner.orders', [
            'orders' => Order::query()
                ->with('customer:id,name')
                ->where('partner_id', $partner->id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function maids(Request $request): View
    {
        return view('partner.maids', [
            'maids' => Maid::query()
                ->where('partner_id', $request->user()->id)
                ->where('is_draft', false)
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function messages(Request $request): View
    {
        $partner = $request->user();
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($partner->id))
            ->with(['latestMessage.sender:id,name', 'participants'])
            ->latest('last_message_at')
            ->get();

        $conversations->each(function (Conversation $conversation) use ($partner): void {
            $conversation->setAttribute('read_label', $this->readLabel($conversation, $partner));
        });

        $selected = null;
        if ($request->filled('conversation')) {
            $selected = $conversations->firstWhere('id', $request->integer('conversation'));
            abort_unless($selected, 404);
            $selected->load(['messages.sender:id,name']);
            $selected->participants()->updateExistingPivot($partner->id, ['last_read_at' => now()]);
            $selected->setAttribute('read_label', 'Read');
        }

        return view('partner.messages', [
            'conversations' => $conversations,
            'selected' => $selected,
        ]);
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(
            $conversation->participants()->whereKey($request->user()->id)->exists(),
            403,
        );
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
            'type' => 'text',
        ]);
        $conversation->update(['last_message_at' => now()]);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return redirect()->route('partner.messages', ['conversation' => $conversation->id])->with('status', 'Reply saved.');
    }

    public function profile(Request $request): View
    {
        $partner = $request->user()->load(['governorate:id,name', 'partnerAreas:id,name']);

        return view('partner.profile', [
            'partner' => $partner,
            'maids' => $partner->maids()->where('is_draft', false)->count(),
            'completedOrders' => $partner->assignedOrders()->whereIn('status', ['completed', 'done', 'done_orders'])->count(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $request->user()->update($data);

        return back()->with('status', 'Profile saved.');
    }

    private function readLabel(Conversation $conversation, User $partner): string
    {
        $pivot = $conversation->participants->firstWhere('id', $partner->id)?->pivot;
        $readAt = $pivot && $pivot->last_read_at ? Carbon::parse($pivot->last_read_at) : null;
        $lastMessage = $conversation->last_message_at;

        return $readAt && $lastMessage && $readAt->greaterThanOrEqualTo($lastMessage) ? 'Read' : 'Unread';
    }
}
