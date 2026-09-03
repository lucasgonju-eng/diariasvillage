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

    public function selectAll(
        string $table,
        string $query = '',
        int $pageSize = 500,
        int $maxPages = 100
    ): array {
        $pageSize = max(1, min(1000, $pageSize));
        $maxPages = max(1, min(1000, $maxPages));
        $baseQuery = trim($query, '&');
        $rows = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $pageQuery = $baseQuery;
            if ($pageQuery !== '') {
                $pageQuery .= '&';
            }
            $pageQuery .= 'limit=' . $pageSize . '&offset=' . ($page * $pageSize);
            $result = $this->select($table, $pageQuery);
            if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
                return $result;
            }

            $pageRows = array_values($result['data']);
            array_push($rows, ...$pageRows);
            if (count($pageRows) < $pageSize) {
                $result['data'] = $rows;
                return $result;
            }
        }

        return [
            'ok' => false,
            'status' => 503,
            'data' => [],
            'error' => 'Paginação excedeu o limite seguro configurado.',
        ];
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

    public function rpc(string $functionName, array $payload = []): array
    {
        $url = $this->url . '/rest/v1/rpc/' . $functionName;
        return $this->http->request('POST', $url, $this->headers(['Prefer' => 'return=representation']), $payload);
    }

    public function delete(string $table, string $query): array
    {
        $url = $this->url . '/rest/v1/' . $table . '?' . $query;
        return $this->http->request('DELETE', $url, $this->headers(['Prefer' => 'return=representation']));
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
