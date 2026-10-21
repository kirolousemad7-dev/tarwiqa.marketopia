<?php

namespace App\Http\Controllers;

use App\Contracts\Services\MessagingServiceInterface;
use App\Http\Requests\Messages\SendMessageRequest;
use App\Models\AdminSetting;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(private readonly MessagingServiceInterface $messages) {}

    public function partners(): View
    {
        request()->merge(['audience' => 'partners']);

        return $this->index();
    }

    public function index(): View
    {
        $adminId = request()->user()->id;
        $box = request('box', 'inbox');
        $conversations = Conversation::query()
            ->with(['participants:id,name,role', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->when($box === 'starred', fn ($query) => $query->where('is_starred', true))
            ->when($box === 'sent', fn ($query) => $query->where('box', 'sent'))
            ->when($box === 'junk', fn ($query) => $query->where('box', 'junk'))
            ->when(! in_array($box, ['starred', 'sent', 'junk'], true), fn ($query) => $query->where('box', 'inbox'))
            ->when(request('audience') === 'partners', fn ($query) => $query->whereHas('participants', fn ($people) => $people->where('role', 'partner')))
            ->when(request()->filled('partner'), fn ($query) => $query->whereHas('participants', fn ($people) => $people->whereKey(request()->integer('partner'))))
            ->when(request()->filled('q'), function ($query): void {
                $term = '%'.request('q').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('subject', 'like', $term)->orWhereHas('participants', fn ($people) => $people->where('name', 'like', $term));
                });
            })
            ->latest('last_message_at')
            ->get();

        return view('messages.index', [
            'conversations' => $conversations,
            'rules' => AdminSetting::getValue('inbox.rules'),
            'metrics' => [
                'total_messages' => Message::count(),
                'unread' => (int) DB::table('conversation_participants')->where('user_id', $adminId)->whereNull('last_read_at')->count(),
                'pending_replies' => (int) DB::table('conversation_participants')->where('user_id', $adminId)->whereNull('last_read_at')->count(),
                'starred_threads' => Conversation::query()->where('is_starred', true)->count(),
                'junk' => Conversation::query()->where('box', 'junk')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('messages.create', ['recipients' => $this->messages->recipients()]);
    }

    public function store(SendMessageRequest $request): RedirectResponse
    {
        $this->messages->send($request->user(), $request->validated());

        return to_route('admin.messages.index')->with('status', 'Message sent successfully.');
    }
}
