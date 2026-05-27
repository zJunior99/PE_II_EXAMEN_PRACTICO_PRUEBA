<?php

final class Vision
{
    public static function findByProyecto(SupabaseClient $supabase, int $idProyecto): ?array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/vision',
            [
                'select' => 'id_vision,id_proyecto,descripcion',
                'id_proyecto' => 'eq.' . $idProyecto,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo cargar la visión.'));
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
                '/rest/v1/vision',
                ['id_proyecto' => 'eq.' . $idProyecto],
                $headers,
                ['descripcion' => $descripcion]
            );

            if ($response['status'] >= 400) {
                throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo actualizar la visión.'));
            }
            return;
        }

        $response = $supabase->request(
            'POST',
            '/rest/v1/vision',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'descripcion' => $descripcion,
            ]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo guardar la visión.'));
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
