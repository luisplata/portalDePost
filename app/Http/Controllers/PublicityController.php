<?php

namespace App\Http\Controllers;


use App\ConfigPublicity;
use App\Publicity;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;

class PublicityController extends Controller
{
    //
    public function redirect(Request $request, $key)
    {
        $config = ConfigPublicity::where('key', $key)->first();
        if ($config) {
            $nameOfCookie = 'anonymous_user_id';
            $anonymousUserId = Cookie::get($nameOfCookie);
            if (!$anonymousUserId) {
                $anonymousUserId = "anonymous";
            }
            $publicity = Publicity::where('config_publicities_id', $config->id)
                ->where('uuid_user', $anonymousUserId)
                ->first();
            if (!$publicity) {
                $publicity = new Publicity();
                $publicity->config_publicities_id = $config->id;
                $publicity->uuid_user = $anonymousUserId;
                $publicity->save();
            } else {
                $publicity->touch();
                $publicity->increment('count');
            }
            return redirect($config->GetValue()->url);
        }
        return redirect('/');
    }
}
