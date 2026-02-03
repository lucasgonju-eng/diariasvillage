<?php

namespace App;

class SupabaseClient
{
    private string $url;
    private string $serviceKey;
    private HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->url = rtrim(Env::get('SUPABASE_URL', ''), '/');
        $this->serviceKey = Env::get('SUPABASE_SERVICE_ROLE_KEY', '');
        $this->http = $http;
    }

    public function select(string $table, string $query = ''): array
    {
        $url = $this->url . '/rest/v1/' . $table;
        if ($query !== '') {
            $url .= '?' . $query;
        }

        return $this->http->request('GET', $url, $this->headers());
    }

    public function insert(string $table, array $payload): array
    {
        $url = $this->url . '/rest/v1/' . $table;
        return $this->http->request('POST', $url, $this->headers(['Prefer' => 'return=representation']), $payload);
    }

    public function update(string $table, string $query, array $payload): array
    {
        $url = $this->url . '/rest/v1/' . $table . '?' . $query;
        return $this->http->request('PATCH', $url, $this->headers(['Prefer' => 'return=representation']), $payload);
    }

    private function headers(array $extra = []): array
    {
        return array_merge([
            'apikey' => $this->serviceKey,
            'Authorization' => 'Bearer ' . $this->serviceKey,
            'Content-Type' => 'application/json',
        ], $extra);
    }
}
