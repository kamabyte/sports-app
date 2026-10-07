<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keep legacy broadcast pages out of the sports catalog interface. */
class DeferBroadcastPages
{
    public function handle(Request $request, Closure $next): Response
    {
        return redirect()->route('schedule');
    }
}
