<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Токены Sanctum для ТВ-клиентов: по одному на устройство.
 */
class TokenController extends Controller
{
    public function store(StoreTokenRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->email())->first();

        if (! $user || ! Hash::check($request->password(), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $token = $user->createToken($request->deviceName())->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
