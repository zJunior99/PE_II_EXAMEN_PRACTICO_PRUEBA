<?php

final class Proyecto
{
    public static function create(SupabaseClient $supabase, int $creadorId, string $nombre): int
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/proyecto',
            [],
            $headers,
            [
                'creador_id' => $creadorId,
                'nombre' => $nombre,
            ]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo crear el proyecto.'));
        }

        $row = (is_array($response['data']) && isset($response['data'][0]) && is_array($response['data'][0]))
            ? $response['data'][0]
            : null;

        $id = is_array($row) ? ($row['id_proyecto'] ?? null) : null;
        if ($id === null) {
            throw new RuntimeException('No se recibió id_proyecto al crear el proyecto.');
        }

        return (int) $id;
    }

    public static function listByCreador(SupabaseClient $supabase, int $creadorId): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto',
            [
                'select' => 'id_proyecto,nombre,creador_id',
                'creador_id' => 'eq.' . $creadorId,
                'order' => 'id_proyecto.desc',
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo listar proyectos.'));
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public static function listByIds(SupabaseClient $supabase, array $ids): array
    {
        return self::listByIdsPaged($supabase, $ids, 200, 0, 'id_proyecto.desc');
    }

    public static function listByIdsPaged(SupabaseClient $supabase, array $ids, int $limit, int $offset, string $order): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, fn ($v) => $v > 0);
        if (empty($ids)) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $order = trim($order) !== '' ? $order : 'id_proyecto.desc';

        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto',
            [
                'select' => 'id_proyecto,nombre,creador_id',
                'id_proyecto' => 'in.(' . implode(',', $ids) . ')',
                'order' => $order,
                'limit' => $limit,
                'offset' => $offset,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo listar proyectos.'));
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public static function findById(SupabaseClient $supabase, int $idProyecto): ?array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto',
            [
                'select' => 'id_proyecto,nombre,creador_id',
                'id_proyecto' => 'eq.' . $idProyecto,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo cargar el proyecto.'));
        }

        if (!is_array($response['data']) || empty($response['data']) || !is_array($response['data'][0] ?? null)) {
            return null;
        }

        return $response['data'][0];
    }

    public static function findOwnedById(SupabaseClient $supabase, int $idProyecto, int $creadorId): ?array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto',
            [
                'select' => 'id_proyecto,nombre,creador_id',
                'id_proyecto' => 'eq.' . $idProyecto,
                'creador_id' => 'eq.' . $creadorId,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo cargar el proyecto.'));
        }

        if (!is_array($response['data']) || empty($response['data']) || !is_array($response['data'][0] ?? null)) {
            return null;
        }

        return $response['data'][0];
    }

    public static function updateNombre(SupabaseClient $supabase, int $idProyecto, string $nombre): void
    {
        $response = $supabase->request(
            'PATCH',
            '/rest/v1/proyecto',
            [
                'id_proyecto' => 'eq.' . $idProyecto,
            ],
            self::restHeaders($supabase),
            [
                'nombre' => $nombre,
            ]
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo actualizar el proyecto.'));
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
