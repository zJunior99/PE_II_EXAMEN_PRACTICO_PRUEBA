<?php

final class Mision
{
    public static function findByProyecto(SupabaseClient $supabase, int $idProyecto): ?array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/mision',
            [
                'select' => 'id_mision,id_proyecto,descripcion',
                'id_proyecto' => 'eq.' . $idProyecto,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo cargar la misión.'));
        }

        if (!is_array($response['data']) || empty($response['data']) || !is_array($response['data'][0] ?? null)) {
            return null;
        }

        return $response['data'][0];
    }

    public static function save(SupabaseClient $supabase, int $idProyecto, string $descripcion): void
    {
        $existing = self::findByProyecto($supabase, $idProyecto);
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        if ($existing) {
            $response = $supabase->request(
                'PATCH',
                '/rest/v1/mision',
                ['id_proyecto' => 'eq.' . $idProyecto],
                $headers,
                ['descripcion' => $descripcion]
            );

            if ($response['status'] >= 400) {
                throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo actualizar la misión.'));
            }
            return;
        }

        $response = $supabase->request(
            'POST',
            '/rest/v1/mision',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'descripcion' => $descripcion,
            ]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo guardar la misión.'));
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
