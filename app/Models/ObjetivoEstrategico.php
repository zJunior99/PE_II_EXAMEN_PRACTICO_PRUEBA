<?php

final class ObjetivoEstrategico
{
    public static function listByProyecto(SupabaseClient $supabase, int $idProyecto): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/objetivo_estrategico',
            [
                'select' => 'id_objetivo_est,id_proyecto,descripcion',
                'id_proyecto' => 'eq.' . $idProyecto,
                'order' => 'id_objetivo_est.desc',
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudieron cargar los objetivos estratégicos.'));
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public static function existsInProyecto(SupabaseClient $supabase, int $idObjetivoEst, int $idProyecto): bool
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/objetivo_estrategico',
            [
                'select' => 'id_objetivo_est',
                'id_objetivo_est' => 'eq.' . $idObjetivoEst,
                'id_proyecto' => 'eq.' . $idProyecto,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo validar el objetivo estratégico.'));
        }

        return is_array($response['data']) && !empty($response['data']);
    }

    public static function create(SupabaseClient $supabase, int $idProyecto, string $descripcion): int
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/objetivo_estrategico',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'descripcion' => $descripcion,
            ]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo crear el objetivo estratégico.'));
        }

        $row = (is_array($response['data']) && is_array($response['data'][0] ?? null)) ? $response['data'][0] : null;
        return (int) ($row['id_objetivo_est'] ?? 0);
    }

    public static function update(SupabaseClient $supabase, int $idObjetivoEst, int $idProyecto, string $descripcion): bool
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'PATCH',
            '/rest/v1/objetivo_estrategico',
            [
                'id_objetivo_est' => 'eq.' . $idObjetivoEst,
                'id_proyecto' => 'eq.' . $idProyecto,
            ],
            $headers,
            ['descripcion' => $descripcion]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo actualizar el objetivo estratégico.'));
        }

        return is_array($response['data']) && !empty($response['data']);
    }

    public static function delete(SupabaseClient $supabase, int $idObjetivoEst, int $idProyecto): bool
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'DELETE',
            '/rest/v1/objetivo_estrategico',
            [
                'id_objetivo_est' => 'eq.' . $idObjetivoEst,
                'id_proyecto' => 'eq.' . $idProyecto,
            ],
            $headers
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo eliminar el objetivo estratégico.'));
        }

        return is_array($response['data']) && !empty($response['data']);
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
