<?php

namespace App\Http\Controllers;

use App\Services\PublicityService;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;

class PublicityController extends Controller
{
    protected $service;

    public function __construct(PublicityService $service)
    {
        $this->service = $service;
    }

    public function redirect(Request $request, $key)
    {
        $anonymousUserId = Cookie::get('anonymous_user_id') ?? 'anonymous';
        $url = $this->service->handleRedirect($key, $anonymousUserId);

        return $url ? redirect($url) : redirect('/');
    }
}
