<?php

namespace App\Services;

use GuzzleHttp\Client;

class CurrencyService
{
    protected $token;

    protected $baseUrl;

    public function __construct()
    {
        $this->token = env('UNIRATE_API_TOKEN');
        $this->baseUrl = env('UNIRATE_API_URL', 'https://api.unirateapi.com');
    }

    public function convertIdrToUsd(string $amount)
    {
        $client = self::guzzleClient($this->baseUrl);
        $request = $client->request(
            'GET',
            '/api/convert',
            [
                'query' => [
                    'api_key' => $this->token,
                    'amount' => $amount,
                    'from' => 'IDR',
                    'to' => 'USD',
                    'format' => 'json',
                ],
            ]
        );

        $response = $request->getBody()->getContents();

        return json_decode($response, true)['result'] ?? null;
    }

    protected static function guzzleClient(string $url)
    {
        return new Client([
            'base_uri' => $url,
            'timeout' => 30,
            'verify' => false,
        ]);
    }
}
