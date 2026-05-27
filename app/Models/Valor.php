<?php

final class Valor
{
    public static function listByProyecto(SupabaseClient $supabase, int $idProyecto): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/valor',
            [
                'select' => 'id_valor,id_proyecto,descripcion',
                'id_proyecto' => 'eq.' . $idProyecto,
                'order' => 'id_valor.asc',
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo listar valores.'));
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public static function findById(SupabaseClient $supabase, int $idValor, int $idProyecto): ?array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/valor',
            [
                'select' => 'id_valor,id_proyecto,descripcion',
                'id_valor' => 'eq.' . $idValor,
                'id_proyecto' => 'eq.' . $idProyecto,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo cargar el valor.'));
        }

        if (!is_array($response['data']) || empty($response['data']) || !is_array($response['data'][0] ?? null)) {
            return null;
        }

        return $response['data'][0];
    }

    public static function create(SupabaseClient $supabase, int $idProyecto, string $descripcion): void
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/valor',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'descripcion' => $descripcion,
            ]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo guardar el valor.'));
        }
    }

    public static function update(SupabaseClient $supabase, int $idValor, int $idProyecto, string $descripcion): bool
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'PATCH',
            '/rest/v1/valor',
            [
                'id_valor' => 'eq.' . $idValor,
                'id_proyecto' => 'eq.' . $idProyecto,
            ],
            $headers,
            [
                'descripcion' => $descripcion,
            ]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo actualizar el valor.'));
        }

        return is_array($response['data']) && !empty($response['data']);
    }

    public static function replaceAll(SupabaseClient $supabase, int $idProyecto, array $descripciones): void
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=minimal';

        $delete = $supabase->request(
            'DELETE',
            '/rest/v1/valor',
            ['id_proyecto' => 'eq.' . $idProyecto],
            $headers
        );

        if ($delete['status'] >= 400) {
            throw new RuntimeException((string) ($delete['data']['message'] ?? $delete['data']['msg'] ?? 'No se pudieron eliminar los valores anteriores.'));
        }

        $payload = [];
        foreach ($descripciones as $descripcion) {
            $descripcion = trim((string) $descripcion);
            if ($descripcion === '') {
                continue;
            }
            $payload[] = [
                'id_proyecto' => $idProyecto,
                'descripcion' => $descripcion,
            ];
        }

        if (empty($payload)) {
            return;
        }

        $insertHeaders = self::restHeaders($supabase);
        $insertHeaders['Prefer'] = 'return=minimal';

        $insert = $supabase->request(
            'POST',
            '/rest/v1/valor',
            [],
            $insertHeaders,
            $payload
        );

        if ($insert['status'] >= 400) {
            throw new RuntimeException((string) ($insert['data']['message'] ?? $insert['data']['msg'] ?? 'No se pudieron guardar los valores.'));
        }
    }

    private static function restHeaders(SupabaseClient $supabase): array
    {
        $serverKey = $supabase->getServiceRoleKey();
        $apiKey = $serverKey ?: $supabase->getAnonKey();
        $authBearer = $serverKey ?: $supabase->getAnonKey();

        return [
            'apikey' => $apiKey,
            'Authorization' => 'Bearer ' . $authBearer,
        ];
    }
}
