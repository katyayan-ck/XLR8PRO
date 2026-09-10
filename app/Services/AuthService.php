<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class AiGatewayService
{
    protected string $baseUrl;
    protected string $masterKey;

    public function __construct()
    {
        $this->baseUrl = config('services.xcelr8_gateway.base_url');
        $this->masterKey = config('services.xcelr8_gateway.master_key');
    }

    /**
     * Send a completion request through the unified gateway layer.
     */
    public function ask(string $modelKey, array $messages, float $temperature = 0.7): string
    {
        $modelIdentifier = config("services.xcelr8_gateway.models.{$modelKey}");

        if (!$modelIdentifier) {
            throw new Exception("Target model configuration key [{$modelKey}] is not defined.");
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->masterKey}",
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/chat/completions", [
            'model'       => $modelIdentifier,
            'messages'    => $messages,
            'temperature' => $temperature,
        ]);

        if ($response->failed()) {
            throw new Exception("Gateway error: " . $response->body());
        }

        return $response->json('choices.0.message.content') ?? '';
    }
}
