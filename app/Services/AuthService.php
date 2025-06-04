<?php

namespace App\Services;

use App\Usuario;

class AuthService
{
    public function autenticar($email, $pass)
    {
        $user = Usuario::where("email", $email)->first();

        if (!$user || sha1($pass) !== $user->pass) {
            return null;
        }

        return $user;
    }
}