<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class KorbaXchangeService
{
    private $secretKey;
    private $clientKey;
    private $baseUrl;

    public function __construct()
    {
        $this->secretKey = env('KORBA_SECRET_KEY');
        $this->clientKey = env('KORBA_CLIENT_KEY');
        $this->baseUrl   = env('KORBA_BASE_URL', 'https://testxchange.korba365.com/api/v1.0/');
    }

    public function makeRequest(string $endpoint, array $payload)
    {
        ksort($payload);
        $message = urldecode(http_build_query($payload, '', '&'));
        $signature = hash_hmac('sha256', $message, $this->secretKey);
        $headers = [
            'Authorization' => 'HMAC ' . $this->clientKey . ':' . $signature,
            'Content-Type'  => 'application/json',
        ];


        $response = Http::withoutVerifying()->withHeaders($headers)
            ->post($this->baseUrl . $endpoint . '/', $payload);

        return $response->json();
    }
    public function collect(array $data)
    {
        return $this->makeRequest('collect', $data);
    }

    public function disburse(array $data)
    {
        return $this->makeRequest('disburse', $data);
    }

    public function ussdOTP(array $data)
    {
        return $this->makeRequest('ussd_otp', $data);
    }


}
