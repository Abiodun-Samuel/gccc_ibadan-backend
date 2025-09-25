<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MailService
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.zeptomail.base_url');
        $this->apiKey = config('services.zeptomail.api_key');
    }

    public function sendEmail(array $data)
    {
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'authorization' => "Zoho-enczapikey {$this->apiKey}",
            'cache-control' => 'no-cache',
            'content-type' => 'application/json'
        ])
            ->timeout(60)
            ->post($this->apiUrl, $data);

        if (!$response->successful()) {
            $data = json_decode($response->json(), true);
            $message = $data->error->details[0]->message;
            throw new \Exception("ZeptoMail API Error: $message");
        }

        return $response->json();
    }
}
