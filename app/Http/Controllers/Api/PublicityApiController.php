<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicityService;
use Illuminate\Http\Request;

class PublicityApiController extends Controller
{
    protected $service;

    public function __construct(PublicityService $service)
    {
        $this->service = $service;
    }

    public function redirect(Request $request, $key)
    {
        $anonymousUserId = $request->header('X-Anonymous-User', 'anonymous');

        $url = $this->service->handleRedirect($key, $anonymousUserId);

        if (!$url) {
            return response()->json(['message' => 'No se encontró la publicidad'], 404);
        }

        return response()->json(['redirect_to' => $url]);
    }
}
