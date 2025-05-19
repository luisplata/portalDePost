<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AuthService;

class AuthApiController extends Controller
{
    protected $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    public function login(Request $request)
    {
        $user = $this->auth->autenticar($request->email, $request->pass);

        if (!$user) {
            return response()->json([
                "message" => "Usuario o contraseña no válidos"
            ], 401);
        }

        return response()->json([
            "message" => "Login correcto",
            "user" => [
                "id" => $user->id,
                "email" => $user->email,
                "nombre" => $user->nombre ?? null
            ]
        ]);
    }
}
