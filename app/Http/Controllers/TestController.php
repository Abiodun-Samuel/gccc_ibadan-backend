<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class TestController extends Controller
{

    public function index()
    {
        $url = "https://api.zeptomail.com/v1.1/email";

        $payload = [
            "from" => [
                "address" => "samyemidele@gmail.com", // Replace with a valid sender email
            ],
            "to" => [
                [
                    "email_address" => [
                        "address" => "abiodunsamyemi@gmail.com",
                        "name" => "abiodun"
                    ]
                ]
            ],
            "subject" => "Test Email",
            "htmlbody" => "<div><b> Test email sent successfully. </b></div>",
        ];

        $response = Http::withHeaders([
            "accept" => "application/json",
            "authorization" => "Zoho-enczapikey wSsVR60g8hf0W6wrzjWlIbw6zVhVUlL1F0su0VDw6HP0GPqUoMcywRDJAgKuFKQXFzZhETsSpLx6zh8JgToKiI5/y1gIDCiF9mqRe1U4J3x17qnvhDzDV2xakBWBJIkIxgtjnGNhEslu",
            "cache-control" => "no-cache",
            "content-type" => "application/json",
        ])->post($url, $payload);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to send email',
                'details' => $response->body()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $response->json()
        ]);
    }

}
