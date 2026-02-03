<?php

namespace App;

class AsaasClient
{
    private HttpClient $http;
    private string $baseUrl;
    private string $apiKey;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
        $env = Env::get('ASAAS_ENV', 'production');
        $this->baseUrl = $env === 'sandbox'
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://www.asaas.com/api/v3';
        $this->apiKey = Env::get('ASAAS_API_KEY', '');
    }

    public function createCustomer(array $payload): array
    {
        return $this->http->request('POST', $this->baseUrl . '/customers', $this->headers(), $payload);
    }

    public function createPayment(array $payload): array
    {
        return $this->http->request('POST', $this->baseUrl . '/payments', $this->headers(), $payload);
    }

    private function headers(): array
    {
        return [
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
