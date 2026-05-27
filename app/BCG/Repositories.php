<?php

final class BcgProductRepository
{
    public function listByProyecto(SupabaseClient $supabase, int $idProyecto): array
    {
        $res = $supabase->request(
            'GET',
            '/rest/v1/bcg_producto',
            [
                'select' => 'id_producto_bcg,id_proyecto,nombre,ventas_empresa,porcentaje_ventas,tcm,prm,clasificacion,created_at,updated_at',
                'id_proyecto' => 'eq.' . $idProyecto,
                'order' => 'id_producto_bcg.asc',
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudieron cargar productos BCG.'));
        }
        $out = [];
        foreach ((array) ($res['data'] ?? []) as $row) {
            if (is_array($row)) {
                $out[] = BcgProductMapper::fromRow($row);
            }
        }
        return $out;
    }

    public function create(SupabaseClient $supabase, int $idProyecto, string $nombre, float $ventasEmpresa): BcgProductEntity
    {
        $headers = $this->headers($supabase);
        $headers['Prefer'] = 'return=representation';

        $res = $supabase->request(
            'POST',
            '/rest/v1/bcg_producto',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'nombre' => $nombre,
                'ventas_empresa' => $ventasEmpresa,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo crear el producto.'));
        }
        $row = (is_array($res['data']) && is_array($res['data'][0] ?? null)) ? $res['data'][0] : null;
        if (!is_array($row)) {
            throw new RuntimeException('No se recibió el producto creado.');
        }
        return BcgProductMapper::fromRow($row);
    }

    public function update(SupabaseClient $supabase, int $idProductoBcg, array $fields): void
    {
        $fields['updated_at'] = gmdate('Y-m-d H:i:s');
        $res = $supabase->request(
            'PATCH',
            '/rest/v1/bcg_producto',
            [
                'id_producto_bcg' => 'eq.' . $idProductoBcg,
            ],
            $this->headers($supabase),
            $fields
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo actualizar el producto.'));
        }
    }

    public function delete(SupabaseClient $supabase, int $idProductoBcg): void
    {
        $res = $supabase->request(
            'DELETE',
            '/rest/v1/bcg_producto',
            [
                'id_producto_bcg' => 'eq.' . $idProductoBcg,
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo eliminar el producto.'));
        }
    }

    public function upsertComputedBatch(SupabaseClient $supabase, array $rows): void
    {
        $rows = array_values(array_filter($rows, fn ($r) => is_array($r) && (int) ($r['id_producto_bcg'] ?? 0) > 0));
        if (empty($rows)) {
            return;
        }
        $headers = $this->headers($supabase);
        $headers['Prefer'] = 'resolution=merge-duplicates';
        $res = $supabase->request(
            'POST',
            '/rest/v1/bcg_producto',
            [
                'on_conflict' => 'id_producto_bcg',
            ],
            $headers,
            $rows
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudieron guardar cálculos BCG.'));
        }
    }

    private function errorFromResponse(array $res, string $default): string
    {
        $status = (int) ($res['status'] ?? 0);
        $data = $res['data'] ?? [];
        $msg = '';
        if (is_array($data)) {
            $msg = (string) ($data['message'] ?? $data['msg'] ?? $data['error'] ?? '');
        }
        $msg = trim($msg);
        $base = $msg !== '' ? $msg : $default;

        if ($status === 401 || $status === 403) {
            return 'Permisos insuficientes para escribir/leer BCG en Supabase (RLS). Crea policies o configura SUPABASE_SERVICE_ROLE_KEY. ' . $base;
        }

        return 'Supabase (' . $status . '): ' . $base;
    }

    private function headers(SupabaseClient $supabase): array
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

final class BcgMarketRepository
{
    public function listByProductoIds(SupabaseClient $supabase, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, fn ($v) => $v > 0);
        if (empty($ids)) {
            return [];
        }
        $res = $supabase->request(
            'GET',
            '/rest/v1/bcg_mercado_periodo',
            [
                'select' => 'id_periodo,id_producto_bcg,anio,demanda_mercado',
                'id_producto_bcg' => 'in.(' . implode(',', $ids) . ')',
                'order' => 'anio.asc',
                'limit' => 1000,
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudieron cargar periodos de mercado.'));
        }
        $out = [];
        foreach ((array) ($res['data'] ?? []) as $row) {
            if (is_array($row)) {
                $out[] = BcgMarketPeriodMapper::fromRow($row);
            }
        }
        return $out;
    }

    public function upsertPeriodo(SupabaseClient $supabase, int $idProductoBcg, int $anio, float $demandaMercado): void
    {
        $headers = $this->headers($supabase);
        $headers['Prefer'] = 'resolution=merge-duplicates';
        $res = $supabase->request(
            'POST',
            '/rest/v1/bcg_mercado_periodo',
            [
                'on_conflict' => 'id_producto_bcg,anio',
            ],
            $headers,
            [
                'id_producto_bcg' => $idProductoBcg,
                'anio' => $anio,
                'demanda_mercado' => $demandaMercado,
            ]
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo guardar el periodo.'));
        }
    }

    public function deletePeriodo(SupabaseClient $supabase, int $idPeriodo): void
    {
        $res = $supabase->request(
            'DELETE',
            '/rest/v1/bcg_mercado_periodo',
            [
                'id_periodo' => 'eq.' . $idPeriodo,
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo eliminar el periodo.'));
        }
    }

    private function errorFromResponse(array $res, string $default): string
    {
        $status = (int) ($res['status'] ?? 0);
        $data = $res['data'] ?? [];
        $msg = '';
        if (is_array($data)) {
            $msg = (string) ($data['message'] ?? $data['msg'] ?? $data['error'] ?? '');
        }
        $msg = trim($msg);
        $base = $msg !== '' ? $msg : $default;

        if ($status === 401 || $status === 403) {
            return 'Permisos insuficientes para escribir/leer BCG en Supabase (RLS). Crea policies o configura SUPABASE_SERVICE_ROLE_KEY. ' . $base;
        }

        return 'Supabase (' . $status . '): ' . $base;
    }

    private function headers(SupabaseClient $supabase): array
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

final class BcgSectorDemandRepository
{
    public function listByProductoIds(SupabaseClient $supabase, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, fn ($v) => $v > 0);
        if (empty($ids)) {
            return [];
        }
        $res = $supabase->request(
            'GET',
            '/rest/v1/bcg_demanda_sector_periodo',
            [
                'select' => 'id_periodo_sector,id_producto_bcg,anio,demanda_sector',
                'id_producto_bcg' => 'in.(' . implode(',', $ids) . ')',
                'order' => 'anio.asc',
                'limit' => 2000,
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudieron cargar periodos de demanda sector.'));
        }
        $out = [];
        foreach ((array) ($res['data'] ?? []) as $row) {
            if (is_array($row)) {
                $out[] = BcgSectorDemandPeriodMapper::fromRow($row);
            }
        }
        return $out;
    }

    public function upsertPeriodo(SupabaseClient $supabase, int $idProductoBcg, int $anio, float $demandaSector): void
    {
        $headers = $this->headers($supabase);
        $headers['Prefer'] = 'resolution=merge-duplicates';
        $res = $supabase->request(
            'POST',
            '/rest/v1/bcg_demanda_sector_periodo',
            [
                'on_conflict' => 'id_producto_bcg,anio',
            ],
            $headers,
            [
                'id_producto_bcg' => $idProductoBcg,
                'anio' => $anio,
                'demanda_sector' => $demandaSector,
            ]
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo guardar el periodo de demanda sector.'));
        }
    }

    public function deletePeriodo(SupabaseClient $supabase, int $idPeriodoSector): void
    {
        $res = $supabase->request(
            'DELETE',
            '/rest/v1/bcg_demanda_sector_periodo',
            [
                'id_periodo_sector' => 'eq.' . $idPeriodoSector,
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo eliminar el periodo de demanda sector.'));
        }
    }

    private function errorFromResponse(array $res, string $default): string
    {
        $status = (int) ($res['status'] ?? 0);
        $data = $res['data'] ?? [];
        $msg = '';
        if (is_array($data)) {
            $msg = (string) ($data['message'] ?? $data['msg'] ?? $data['error'] ?? '');
        }
        $msg = trim($msg);
        $base = $msg !== '' ? $msg : $default;

        if ($status === 401 || $status === 403) {
            return 'Permisos insuficientes para escribir/leer BCG en Supabase (RLS). Crea policies o configura SUPABASE_SERVICE_ROLE_KEY. ' . $base;
        }

        return 'Supabase (' . $status . '): ' . $base;
    }

    private function headers(SupabaseClient $supabase): array
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

final class BcgCompetitorRepository
{
    public function listByProductoIds(SupabaseClient $supabase, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, fn ($v) => $v > 0);
        if (empty($ids)) {
            return [];
        }
        $res = $supabase->request(
            'GET',
            '/rest/v1/bcg_competidor',
            [
                'select' => 'id_competidor,id_producto_bcg,nombre,ventas',
                'id_producto_bcg' => 'in.(' . implode(',', $ids) . ')',
                'order' => 'id_competidor.asc',
                'limit' => 2000,
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudieron cargar competidores.'));
        }
        $out = [];
        foreach ((array) ($res['data'] ?? []) as $row) {
            if (is_array($row)) {
                $out[] = BcgCompetitorMapper::fromRow($row);
            }
        }
        return $out;
    }

    public function create(SupabaseClient $supabase, int $idProductoBcg, string $nombre, float $ventas): void
    {
        $headers = $this->headers($supabase);
        $headers['Prefer'] = 'return=representation';
        $res = $supabase->request(
            'POST',
            '/rest/v1/bcg_competidor',
            [],
            $headers,
            [
                'id_producto_bcg' => $idProductoBcg,
                'nombre' => $nombre,
                'ventas' => $ventas,
            ]
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo crear el competidor.'));
        }
    }

    public function update(SupabaseClient $supabase, int $idCompetidor, array $fields): void
    {
        $res = $supabase->request(
            'PATCH',
            '/rest/v1/bcg_competidor',
            [
                'id_competidor' => 'eq.' . $idCompetidor,
            ],
            $this->headers($supabase),
            $fields
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo actualizar el competidor.'));
        }
    }

    public function delete(SupabaseClient $supabase, int $idCompetidor): void
    {
        $res = $supabase->request(
            'DELETE',
            '/rest/v1/bcg_competidor',
            [
                'id_competidor' => 'eq.' . $idCompetidor,
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo eliminar el competidor.'));
        }
    }

    private function errorFromResponse(array $res, string $default): string
    {
        $status = (int) ($res['status'] ?? 0);
        $data = $res['data'] ?? [];
        $msg = '';
        if (is_array($data)) {
            $msg = (string) ($data['message'] ?? $data['msg'] ?? $data['error'] ?? '');
        }
        $msg = trim($msg);
        $base = $msg !== '' ? $msg : $default;

        if ($status === 401 || $status === 403) {
            return 'Permisos insuficientes para escribir/leer BCG en Supabase (RLS). Crea policies o configura SUPABASE_SERVICE_ROLE_KEY. ' . $base;
        }

        return 'Supabase (' . $status . '): ' . $base;
    }

    private function headers(SupabaseClient $supabase): array
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

final class BcgResultRepository
{
    public function insertResult(SupabaseClient $supabase, int $idProyecto, float $totalVentas): void
    {
        $headers = $this->headers($supabase);
        $headers['Prefer'] = 'return=representation';
        $res = $supabase->request(
            'POST',
            '/rest/v1/bcg_resultado',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'total_ventas' => $totalVentas,
                'fecha_calculo' => gmdate('Y-m-d H:i:s'),
            ]
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo guardar el resultado.'));
        }
    }

    public function findLatestByProyecto(SupabaseClient $supabase, int $idProyecto): ?BcgResultEntity
    {
        $res = $supabase->request(
            'GET',
            '/rest/v1/bcg_resultado',
            [
                'select' => 'id_resultado,id_proyecto,total_ventas,fecha_calculo',
                'id_proyecto' => 'eq.' . $idProyecto,
                'order' => 'fecha_calculo.desc,id_resultado.desc',
                'limit' => 1,
            ],
            $this->headers($supabase)
        );
        if ($res['status'] >= 400) {
            throw new RuntimeException($this->errorFromResponse($res, 'No se pudo cargar el resultado.'));
        }
        $row = (is_array($res['data']) && is_array($res['data'][0] ?? null)) ? $res['data'][0] : null;
        if (!is_array($row)) {
            return null;
        }
        return BcgResultMapper::fromRow($row);
    }

    private function errorFromResponse(array $res, string $default): string
    {
        $status = (int) ($res['status'] ?? 0);
        $data = $res['data'] ?? [];
        $msg = '';
        if (is_array($data)) {
            $msg = (string) ($data['message'] ?? $data['msg'] ?? $data['error'] ?? '');
        }
        $msg = trim($msg);
        $base = $msg !== '' ? $msg : $default;

        if ($status === 401 || $status === 403) {
            return 'Permisos insuficientes para escribir/leer BCG en Supabase (RLS). Crea policies o configura SUPABASE_SERVICE_ROLE_KEY. ' . $base;
        }

        return 'Supabase (' . $status . '): ' . $base;
    }

    private function headers(SupabaseClient $supabase): array
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
