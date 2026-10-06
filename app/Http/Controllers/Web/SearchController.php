<?php

namespace App\Http\Controllers\Web;

use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Web\EventCard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const int MAX_QUERY_LENGTH = 100;

    private const int MAX_RESULTS = 60;

    public function __invoke(Request $request, EventCatalog $catalog): Response
    {
        // Длинный запрос не ошибка для веба — просто обрезаем (API отвечает 422).
        // Битый UTF-8 (кривой %-код в адресе) иначе не сериализуется в пропсы.
        $query = Str::limit(mb_scrub($request->string('q')->trim()->toString()), self::MAX_QUERY_LENGTH, '');

        $events = $query === ''
            ? collect()
            : $catalog->events(new EventQuery(search: $query, limit: self::MAX_RESULTS));

        return Inertia::render('search', [
            'query' => $query,
            'events' => EventCard::collection($events)->resolve(),
        ]);
    }
}
