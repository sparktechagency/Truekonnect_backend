<?php

namespace App\Traits;

use Google\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait SendNotification
{
//    public function sendNotification($title, $body)
//    {
//        $fcm = FCMToken::whereNotNull('token')->get()->unique('token');
//
//        foreach ($fcm as $token) {
//            $this->sendFcmV1($token->token, $title, $body);
//        }
//    }

    public function sendFcmV1($token, $title, $body)
    {
//        dd($token);
        $client = new Client();

        $client->setAuthConfig(Storage::path('always-update-12b14.json'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $accessToken = $client->getAccessToken()["access_token"];

        $projectId = 'always-update-12b14';
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $payload = [
            "message" => [
                "token" => $token,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ]
            ]
        ];

        $headers = [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            Log::error("FCM Send Error: $err");
            return false;
        }

    }
}
