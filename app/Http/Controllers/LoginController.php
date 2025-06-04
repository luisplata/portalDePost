<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Services\AuthService;

class LoginController extends Controller
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
            return redirect("/login?mensaje=Usuario o Contraseña, no válidos");
        }

        Session::put('admin', $user);

        return redirect("/admin");
    }
}
