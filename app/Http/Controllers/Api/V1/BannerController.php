<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BannerResource;
use App\Models\Ad;
use App\Models\AdEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $banners = Cache::remember('api:banners:active', 300, fn () => Ad::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get());

        return apiResponse(data: BannerResource::collection($banners)->resolve($request), message: 'Banners retrieved successfully.');
    }

    public function impression(Request $request, Ad $ad): JsonResponse
    {
        abort_unless($ad->status === 'active', 404);
        $ad->increment('impressions');
        AdEvent::query()->create([
            'ad_id' => $ad->id,
            'event_type' => 'impression',
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
        ]);
        Cache::forget('api:banners:active');

        return apiResponse(data: ['impressions' => (int) $ad->fresh()->impressions], message: 'Impression recorded.');
    }
}
