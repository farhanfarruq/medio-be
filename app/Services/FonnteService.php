<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected string $token;

    public function __construct()
    {
        $this->token = env('FONNTE_TOKEN', '');
    }

    /**
     * Kirim pesan WhatsApp
     */
    public function sendMessage(string $target, string $message): bool
    {
        if (empty($this->token)) {
            Log::warning('Fonnte token is not set in .env');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->post('https://api.fonnte.com/send', [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62', // Default Indonesia
            ]);

            if ($response->successful()) {
                Log::info("WhatsApp sent to {$target} via Fonnte");
                return true;
            }

            Log::error("Fonnte API Error: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Fonnte Exception: " . $e->getMessage());
            return false;
        }
    }
}
