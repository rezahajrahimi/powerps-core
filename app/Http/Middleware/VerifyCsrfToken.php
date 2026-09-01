<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/cryptomus/callback', // Exclude Cryptomus callback route
        '/payback', // Exclude NowPayments callback route if needed (based on existing routes/web.php)
        '/swappay/return',
        // Add other webhook routes here if necessary
    ];
}
