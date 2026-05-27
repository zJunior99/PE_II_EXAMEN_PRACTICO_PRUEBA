<?php

final class SupabaseClient
{
    private string $url;
    private string $anonKey;
    private ?string $serviceRoleKey;
    private array $dotenv = [];

    public function __construct()
    {
        $this->dotenv = $this->loadDotenv(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');

        $this->url = rtrim((string) $this->env('SUPABASE_URL'), '/');
        $this->anonKey = (string) $this->env('SUPABASE_ANON_KEY');
        $this->serviceRoleKey = $this->env('SUPABASE_SERVICE_ROLE_KEY');

        if ($this->url === '' || $this->anonKey === '') {
            throw new RuntimeException('Faltan SUPABASE_URL o SUPABASE_ANON_KEY en el archivo .env.');
        }
    }

    public function signInWithPassword(string $email, string $password): array
    {
        $response = $this->request(
            'POST',
            '/auth/v1/token',
            ['grant_type' => 'password'],
            [
                'apikey' => $this->anonKey,
                'Authorization' => 'Bearer ' . $this->anonKey,
            ],
            [
                'email' => $email,
                'password' => $password,
            ]
        );

        if ($response['status'] >= 400) {
            return [
                'ok' => false,
                'status' => $response['status'],
                'error' => $response['data']['error_description'] ?? $response['data']['msg'] ?? 'Credenciales inválidas.',
                'data' => null,
            ];
        }

        return [
            'ok' => true,
            'status' => $response['status'],
            'error' => null,
            'data' => $response['data'],
        ];
    }

    public function updatePassword(string $accessToken, string $newPassword): array
    {
        $response = $this->request(
            'PUT',
            '/auth/v1/user',
            [],
            [
                'apikey' => $this->anonKey,
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            [
                'password' => $newPassword,
            ]
        );

        if ($response['status'] >= 400) {
            return [
                'ok' => false,
                'status' => $response['status'],
                'error' => $response['data']['msg'] ?? $response['data']['error_description'] ?? 'No se pudo actualizar la contraseña.',
                'data' => null,
            ];
        }

        return [
            'ok' => true,
            'status' => $response['status'],
            'error' => null,
            'data' => $response['data'],
        ];
    }

    public function getServiceRoleKey(): ?string
    {
        return $this->serviceRoleKey;
    }

    public function getAnonKey(): string
    {
        return $this->anonKey;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function request(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        array|string|null $body = null
    ): array {
        $url = $this->url . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('No se pudo inicializar cURL.');
        }

        $normalizedHeaders = [
            'Accept: application/json',
        ];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[] = $name . ': ' . $value;
        }

        $payload = null;
        if (is_array($body)) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
            $normalizedHeaders[] = 'Content-Type: application/json';
        } elseif (is_string($body)) {
            $payload = $body;
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $normalizedHeaders,
            CURLOPT_TIMEOUT => 15,
        ]);

        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false) {
            return [
                'status' => 0,
                'data' => ['msg' => $error ?: 'Error de red al comunicar con Supabase.'],
            ];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = ['raw' => $raw];
        }

        return [
            'status' => $status,
            'data' => $decoded,
        ];
    }

    private function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (array_key_exists($key, $this->dotenv) && $this->dotenv[$key] !== '') {
            return $this->dotenv[$key];
        }

        return $default;
    }

    private function loadDotenv(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $vars = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '') {
                $vars[$key] = $value;
            }
        }

        return $vars;
    }
}
