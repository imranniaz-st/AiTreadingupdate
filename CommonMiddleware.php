<?php

namespace Modules\Common\Http\Middleware;

use Closure;

class CommonMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Common middleware — no external license checks or remote code execution.
        return $next($request);
    }
}
