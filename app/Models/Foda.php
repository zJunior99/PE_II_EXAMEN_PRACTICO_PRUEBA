<?php

final class Foda
{
    public static function listByProyectoFuente(SupabaseClient $supabase, int $idProyecto, string $fuente): array
    {
        $fuente = trim($fuente);
        if ($idProyecto <= 0 || $fuente === '') {
            return [];
        }

        $response = $supabase->request(
            'GET',
            '/rest/v1/foda_item',
            [
                'select' => 'id_item,tipo,posicion,descripcion,fuente',
                'id_proyecto' => 'eq.' . $idProyecto,
                'fuente' => 'eq.' . $fuente,
                'order' => 'tipo.asc,posicion.asc,id_item.asc',
                'limit' => 500,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            return [];
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public static function replaceByProyectoFuente(SupabaseClient $supabase, int $idProyecto, string $fuente, array $items): bool
    {
        $fuente = trim($fuente);
        if ($idProyecto <= 0 || $fuente === '') {
            return false;
        }

        $delete = $supabase->request(
            'DELETE',
            '/rest/v1/foda_item',
            [
                'id_proyecto' => 'eq.' . $idProyecto,
                'fuente' => 'eq.' . $fuente,
            ],
            self::restHeaders($supabase)
        );

        if ($delete['status'] >= 400) {
            return false;
        }

        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $tipo = trim((string) ($item['tipo'] ?? ''));
            $posicion = (int) ($item['posicion'] ?? 0);
            $descripcion = trim((string) ($item['descripcion'] ?? ''));
            $updatedAt = (string) ($item['updated_at'] ?? '');

            if ($tipo !== 'FORTALEZA' && $tipo !== 'DEBILIDAD') {
                continue;
            }
            if ($posicion <= 0 || $descripcion === '') {
                continue;
            }

            $row = [
                'id_proyecto' => $idProyecto,
                'fuente' => $fuente,
                'tipo' => $tipo,
                'posicion' => $posicion,
                'descripcion' => $descripcion,
            ];
            if ($updatedAt !== '') {
                $row['updated_at'] = $updatedAt;
            }
            $clean[] = $row;
        }

        if (empty($clean)) {
            return true;
        }

        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=minimal';

        $insert = $supabase->request(
            'POST',
            '/rest/v1/foda_item',
            [],
            $headers,
            $clean
        );

        return $insert['status'] < 400;
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

