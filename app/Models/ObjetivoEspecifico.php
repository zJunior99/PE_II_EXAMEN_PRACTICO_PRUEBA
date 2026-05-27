<?php

final class ObjetivoEspecifico
{
    public static function listByProyecto(SupabaseClient $supabase, int $idProyecto): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/objetivo_especifico',
            [
                'select' => 'id_objetivo_esp,id_objetivo_est,descripcion,objetivo_estrategico!inner(id_proyecto)',
                'objetivo_estrategico.id_proyecto' => 'eq.' . $idProyecto,
                'order' => 'id_objetivo_est.desc,id_objetivo_esp.desc',
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudieron cargar los objetivos específicos.'));
        }

        if (!is_array($response['data'])) {
            return [];
        }

        $rows = [];
        foreach ($response['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            unset($row['objetivo_estrategico']);
            $rows[] = $row;
        }

        return $rows;
    }

    public static function existsInObjetivoEstrategico(SupabaseClient $supabase, int $idObjetivoEsp, int $idObjetivoEst): bool
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/objetivo_especifico',
            [
                'select' => 'id_objetivo_esp',
                'id_objetivo_esp' => 'eq.' . $idObjetivoEsp,
                'id_objetivo_est' => 'eq.' . $idObjetivoEst,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo validar el objetivo específico.'));
        }

        return is_array($response['data']) && !empty($response['data']);
    }

    public static function create(SupabaseClient $supabase, int $idObjetivoEst, string $descripcion): int
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/objetivo_especifico',
            [],
            $headers,
            [
                'id_objetivo_est' => $idObjetivoEst,
                'descripcion' => $descripcion,
            ]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo crear el objetivo específico.'));
        }

        $row = (is_array($response['data']) && is_array($response['data'][0] ?? null)) ? $response['data'][0] : null;
        return (int) ($row['id_objetivo_esp'] ?? 0);
    }

    public static function update(SupabaseClient $supabase, int $idObjetivoEsp, int $idObjetivoEst, string $descripcion): bool
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'PATCH',
            '/rest/v1/objetivo_especifico',
            [
                'id_objetivo_esp' => 'eq.' . $idObjetivoEsp,
                'id_objetivo_est' => 'eq.' . $idObjetivoEst,
            ],
            $headers,
            ['descripcion' => $descripcion]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo actualizar el objetivo específico.'));
        }

        return is_array($response['data']) && !empty($response['data']);
    }

    public static function delete(SupabaseClient $supabase, int $idObjetivoEsp, int $idObjetivoEst): bool
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'DELETE',
            '/rest/v1/objetivo_especifico',
            [
                'id_objetivo_esp' => 'eq.' . $idObjetivoEsp,
                'id_objetivo_est' => 'eq.' . $idObjetivoEst,
            ],
            $headers
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo eliminar el objetivo específico.'));
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
