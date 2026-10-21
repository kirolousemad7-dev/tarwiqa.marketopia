<?php

namespace App\Http\Controllers;

use App\Models\Maid;
use App\Models\Order;
use App\Models\SupporterSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SupporterWorkspaceController extends Controller
{
    public function orders(Request $request): View
    {
        $supporter = $request->user();
        $this->assertSettingEnabled($supporter, 'can_view_orders');
        $orders = $this->scopedOrders($supporter)->latest()->paginate(20);

        return view('supporter.orders', [
            'orders' => $orders,
            'governorates' => $supporter->governorates()->orderBy('name')->get(),
        ]);
    }

    public function maids(Request $request): View
    {
        $supporter = $request->user();
        $maids = $this->scopedMaids($supporter)->orderBy('name')->paginate(20);

        return view('supporter.maids', [
            'maids' => $maids,
            'governorates' => $supporter->governorates()->orderBy('name')->get(),
        ]);
    }

    public function profile(Request $request): View
    {
        $supporter = $request->user()->load('governorates');
        $sessions = SupporterSession::query()->where('user_id', $supporter->id)->latest('started_at')->limit(10)->get();

        return view('supporter.profile', [
            'supporter' => $supporter,
            'sessions' => $sessions,
            'openSession' => $sessions->first(fn (SupporterSession $session): bool => $session->isOnline()),
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

    public function security(Request $request): View
    {
        return view('supporter.security', ['supporter' => $request->user()]);
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password updated.');
    }

    private function scopedOrders(User $supporter)
    {
        return Order::query()
            ->with(['customer:id,name', 'partner:id,name,governorate_id'])
            ->whereHas('partner', fn ($query) => $query->whereIn('governorate_id', $this->governorateIds($supporter)));
    }

    private function scopedMaids(User $supporter)
    {
        return Maid::query()
            ->with(['partner:id,name,governorate_id', 'partner.governorate:id,name'])
            ->where('is_draft', false)
            ->whereHas('partner', fn ($query) => $query->whereIn('governorate_id', $this->governorateIds($supporter)));
    }

    private function governorateIds(User $supporter): Collection
    {
        return $supporter->governorates()->pluck('governorates.id');
    }

    private function assertSettingEnabled(User $supporter, string $key): void
    {
        $settings = $supporter->settings ?? [];
        if (array_key_exists($key, $settings) && ! filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN)) {
            abort(403);
        }
    }
}
