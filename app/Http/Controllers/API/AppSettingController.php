<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    public function index()
    {
        $settings = AppSetting::all()->pluck('value', 'key');
        
        // Decode JSON values if type is json
        $settings = $settings->map(function ($value, $key) {
            $setting = AppSetting::where('key', $key)->first();
            if ($setting && $setting->type === 'json') {
                return json_decode($value, true);
            }
            return $value;
        });

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }
}
