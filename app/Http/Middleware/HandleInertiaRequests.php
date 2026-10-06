<?php

namespace App\Http\Middleware;

use App\Guide\Channels;
use App\Guide\EventCatalog;
use App\Http\Resources\SportResource;
use App\Http\Resources\Web\ChannelCard;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Время показывается в часовом поясе, по которому расписание делится на дни.
            'timezone' => config('sports.timezone'),
            // Виды спорта — в боковой панели.
            'sports' => fn () => $request->user()
                ? SportResource::collection(app(EventCatalog::class)->sports())->resolve()
                : [],
            // Каналы с матчами — в боковой панели (красная точка — матч идёт).
            'sidebarChannels' => fn () => $request->user()
                ? ChannelCard::collection(app(Channels::class)->withMatches())->resolve()
                : [],
        ];
    }
}
