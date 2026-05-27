<?php

final class ProyectoMiembro
{
    public static function listProyectoIdsByPersona(SupabaseClient $supabase, int $idPersona): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto_miembro',
            [
                'select' => 'id_proyecto,rol',
                'id_persona' => 'eq.' . $idPersona,
                'order' => 'id.asc',
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            return [];
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public static function getRol(SupabaseClient $supabase, int $idProyecto, int $idPersona): ?string
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto_miembro',
            [
                'select' => 'rol',
                'id_proyecto' => 'eq.' . $idProyecto,
                'id_persona' => 'eq.' . $idPersona,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            return null;
        }

        if (!is_array($response['data']) || empty($response['data']) || !is_array($response['data'][0] ?? null)) {
            return null;
        }

        $rol = (string) ($response['data'][0]['rol'] ?? '');
        return $rol !== '' ? $rol : null;
    }

    public static function exists(SupabaseClient $supabase, int $idProyecto, int $idPersona): bool
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto_miembro',
            [
                'select' => 'id',
                'id_proyecto' => 'eq.' . $idProyecto,
                'id_persona' => 'eq.' . $idPersona,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo validar el miembro.'));
        }

        return is_array($response['data']) && !empty($response['data']);
    }

    public static function createInvitado(SupabaseClient $supabase, int $idProyecto, int $idPersona): bool
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/proyecto_miembro',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'id_persona' => $idPersona,
                'rol' => 'INVITADO',
            ]
        );

        if ($response['status'] === 409) {
            return false;
        }

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo invitar al miembro.'));
        }

        return true;
    }

    public static function createCreador(SupabaseClient $supabase, int $idProyecto, int $idPersona): bool
    {
        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/proyecto_miembro',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'id_persona' => $idPersona,
                'rol' => 'CREADOR',
            ]
        );

        if ($response['status'] === 409) {
            return false;
        }

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo registrar el creador como miembro.'));
        }

        return true;
    }

    public static function delete(SupabaseClient $supabase, int $idProyecto, int $idPersona): bool
    {
        $response = $supabase->request(
            'DELETE',
            '/rest/v1/proyecto_miembro',
            [
                'id_proyecto' => 'eq.' . $idProyecto,
                'id_persona' => 'eq.' . $idPersona,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudo eliminar el miembro.'));
        }

        return true;
    }

    public static function listByProyecto(SupabaseClient $supabase, int $idProyecto): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto_miembro',
            [
                'select' => 'id_persona,rol',
                'id_proyecto' => 'eq.' . $idProyecto,
                'order' => 'id.asc',
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            throw new RuntimeException((string) ($response['data']['message'] ?? $response['data']['msg'] ?? 'No se pudieron cargar los miembros.'));
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public static function listByProyectoWithPersona(SupabaseClient $supabase, int $idProyecto): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/proyecto_miembro',
            [
                'select' => 'id_persona,rol,persona(nombre,email)',
                'id_proyecto' => 'eq.' . $idProyecto,
                'order' => 'id.asc',
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            return [];
        }

        return is_array($response['data']) ? $response['data'] : [];
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

