<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class LegacyCsrfToken extends ValidateCsrfToken
{
    protected function getTokenFromRequest($request)
    {
        return parent::getTokenFromRequest($request) ?: $request->input('csrf');
    }
}
