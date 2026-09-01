<?php
namespace App\Http\Middleware;

use App\Services\LicenseCheckService;
use Closure;
use Illuminate\Http\Request;

class CheckPowerPsLicense
{
    public function __construct(private LicenseCheckService $licenseCheckService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $this->licenseCheckService->isMiddlewareLicenseValid()) {
            return response()->json(['error' => 'License is not valid.'], 403);
        }

        return $next($request);
    }
}
