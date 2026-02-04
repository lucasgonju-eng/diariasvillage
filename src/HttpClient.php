<?php

namespace App;

class HttpClient
{
    public function request(string $method, string $url, array $headers = [], ?array $json = null): array
    {
        $ch = curl_init();
        $userAgent = null;
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'User-Agent') === 0) {
                $userAgent = $value;
                unset($headers[$key]);
                break;
            }
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
        ];
        if ($userAgent !== null) {
            $options[CURLOPT_USERAGENT] = $userAgent;
        }

        if ($json !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($json);
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => $error,
                'data' => null,
                'raw' => null,
            ];
        }

        $data = json_decode($response, true);
        $raw = $data === null ? $response : null;
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => is_array($data) ? ($data['message'] ?? null) : null,
            'data' => $data,
            'raw' => $raw,
        ];
    }

    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = $key . ': ' . $value;
        }
        return $formatted;
    }
}
