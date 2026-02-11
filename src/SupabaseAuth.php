<?php

namespace App;

class SupabaseAuth
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

    public function signUp(string $email, string $password, array $options = []): array
    {
        $payload = array_merge([
            'email' => $email,
            'password' => $password,
        ], $options);

        return $this->http->request(
            'POST',
            $this->url . '/auth/v1/signup',
            $this->headers(),
            $payload
        );
    }

    public function signIn(string $email, string $password): array
    {
        $payload = [
            'grant_type' => 'password',
            'email' => $email,
            'password' => $password,
        ];

        return $this->http->request(
            'POST',
            $this->url . '/auth/v1/token?grant_type=password',
            $this->headers(),
            $payload
        );
    }

    private function headers(): array
    {
        return [
            'apikey' => $this->serviceKey,
            'Authorization' => 'Bearer ' . $this->serviceKey,
            'Content-Type' => 'application/json',
        ];
    }
}
