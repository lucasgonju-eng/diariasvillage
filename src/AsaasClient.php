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

    public function updateCustomer(string $customerId, array $payload): array
    {
        return $this->http->request('POST', $this->baseUrl . '/customers/' . $customerId, $this->headers(), $payload);
    }

    public function getPayment(string $paymentId): array
    {
        return $this->http->request('GET', $this->baseUrl . '/payments/' . $paymentId, $this->headers());
    }

    public function findPaymentByInvoiceUrl(string $invoiceUrl): array
    {
        $url = $this->baseUrl . '/payments?invoiceUrl=' . urlencode($invoiceUrl);
        return $this->http->request('GET', $url, $this->headers());
    }

    public function findPaymentByInvoiceNumber(string $invoiceNumber): array
    {
        $url = $this->baseUrl . '/payments?invoiceNumber=' . urlencode($invoiceNumber);
        return $this->http->request('GET', $url, $this->headers());
    }

    public function findPaymentByExternalReference(string $externalReference): array
    {
        $url = $this->baseUrl . '/payments?externalReference=' . urlencode($externalReference);
        return $this->http->request('GET', $url, $this->headers());
    }

    private function headers(): array
    {
        return [
            'access_token' => $this->apiKey,
            'User-Agent' => 'DiariasVillage/1.0',
            'Content-Type' => 'application/json',
        ];
    }
}
