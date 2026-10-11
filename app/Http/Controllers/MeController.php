<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Кто вошёл и как показывать данные: часовой пояс дней расписания и язык названий.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'timezone' => config('sports.timezone'),
            'locale' => config('sports.catalog.locale'),
        ]);
    }
}
