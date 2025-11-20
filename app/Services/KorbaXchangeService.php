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
//        $message = http_build_query($payload, '', '&');
//        dd($message);
        $signature = hash_hmac('sha256', $message, $this->secretKey);
//        dd($signature);
        $headers = [
            'Authorization' => 'HMAC ' . $this->clientKey . ':' . $signature,
            'Content-Type'  => 'application/json',
        ];

//        dd($this->clientKey);
//        dd($payload['client_id']);
//        dd($headers,$this->secretKey,$endpoint,$payload);
        $response = Http::withoutVerifying()->withHeaders($headers)
            ->post($this->baseUrl . $endpoint . '/', $payload);

//        dd($response->json());
        return $response->json();
    }
    public function collect(array $data)
    {
//        dd($data);
        return $this->makeRequest('collect', $data);
    }

    public function disburse(array $data)
    {
        return $this->makeRequest('disburse', $data);
    }


}
