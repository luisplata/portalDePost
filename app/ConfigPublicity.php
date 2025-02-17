<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConfigPublicity extends Model
{
    use HasFactory;

    public function GetValue()
    {
        return json_decode($this->value);
    }

    public function GetImage(Request $request, $key)
    {
        $publicity = ConfigPublicity::where('key', $key)->first();
        if (!$publicity) {
            return response()->json(['error' => 'Publicity not found'], 404);
        }

        $imageUrl = $publicity->GetValue()->image;

        // Redirect to the original image URL
        return redirect()->away($imageUrl);
    }
}
