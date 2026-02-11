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

    /** Cria usuário via Admin API - sem enviar e-mail de confirmação do Supabase. */
    public function createUser(string $email, string $password, array $options = []): array
    {
        $payload = array_merge([
            'email' => $email,
            'password' => $password,
            'email_confirm' => true,
        ], $options);

        return $this->http->request(
            'POST',
            $this->url . '/auth/v1/admin/users',
            $this->headers(),
            $payload
        );
    }

    /** Lista usuários (Admin API) - retorna users do Supabase Auth. */
    public function listUsers(int $page = 1, int $perPage = 1000): array
    {
        $url = $this->url . '/auth/v1/admin/users?page=' . $page . '&per_page=' . $perPage;
        return $this->http->request('GET', $url, $this->headers());
    }

    /** Atualiza usuário via Admin API (ex.: senha, email_confirm). */
    public function updateUser(string $userId, array $attrs): array
    {
        return $this->http->request(
            'PUT',
            $this->url . '/auth/v1/admin/user/' . $userId,
            $this->headers(),
            $attrs
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
