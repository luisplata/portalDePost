<?php

namespace App\Services;

use App\ConfigPublicity;
use App\Publicity;

class PublicityService
{
    public function handleRedirect($key, $anonymousUserId)
    {
        $config = ConfigPublicity::where('key', $key)->first();

        if (!$config) {
            return null;
        }

        if (!$anonymousUserId) {
            $anonymousUserId = 'anonymous';
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

        return $config->GetValue()->url;
    }
}
