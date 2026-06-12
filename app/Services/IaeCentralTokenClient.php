<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class IaeCentralTokenClient
{
    public function bearerToken(): string
    {
        $configuredToken = (string) config('services.central.bearer_token', '');

        if ($configuredToken !== '') {
            return $configuredToken;
        }

        $apiKey = (string) config('services.central.api_key', '');

        if ($apiKey !== '') {
            return $this->fetchMachineToken($apiKey);
        }

        $requestToken = request()->bearerToken();

        if ($requestToken) {
            return $requestToken;
        }

        throw new ConnectionException('IAE central API key is not configured');
    }

    private function fetchMachineToken(string $apiKey): string
    {
        $response = Http::timeout((int) config('services.central.timeout', 5))
            ->acceptJson()
            ->post((string) config('services.central.token_url'), [
                'api_key' => $apiKey,
            ]);

        if (! $response->successful()) {
            throw new ConnectionException("IAE central token endpoint returned HTTP {$response->status()}");
        }

        $token = $response->json('access_token')
            ?? $response->json('token')
            ?? $response->json('data.access_token')
            ?? $response->json('data.token');

        if (! is_string($token) || $token === '') {
            throw new ConnectionException('IAE central token response did not include access token');
        }

        return $token;
    }
}
