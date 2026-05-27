<?php

final class BcgCalculationService
{
    private BcgProductRepository $productRepo;
    private BcgMarketRepository $marketRepo;
    private BcgSectorDemandRepository $sectorRepo;
    private BcgCompetitorRepository $competitorRepo;
    private BcgResultRepository $resultRepo;
    private BcgMatrixService $matrixService;

    public function __construct()
    {
        $this->productRepo = new BcgProductRepository();
        $this->marketRepo = new BcgMarketRepository();
        $this->sectorRepo = new BcgSectorDemandRepository();
        $this->competitorRepo = new BcgCompetitorRepository();
        $this->resultRepo = new BcgResultRepository();
        $this->matrixService = new BcgMatrixService();
    }

    public function recalculateProject(SupabaseClient $supabase, int $idProyecto): BcgProjectStateDto
    {
        $products = $this->productRepo->listByProyecto($supabase, $idProyecto);
        $ids = [];
        foreach ($products as $p) {
            if ($p instanceof BcgProductEntity && $p->idProductoBcg > 0) {
                $ids[] = $p->idProductoBcg;
            }
        }

        $periods = $this->marketRepo->listByProductoIds($supabase, $ids);
        $sectorPeriods = $this->sectorRepo->listByProductoIds($supabase, $ids);
        $competitors = $this->competitorRepo->listByProductoIds($supabase, $ids);

        $periodsByProduct = [];
        foreach ($periods as $mp) {
            if (!$mp instanceof BcgMarketPeriodEntity) {
                continue;
            }
            $periodsByProduct[$mp->idProductoBcg][] = $mp;
        }

        $sectorByProduct = [];
        foreach ($sectorPeriods as $sp) {
            if (!$sp instanceof BcgSectorDemandPeriodEntity) {
                continue;
            }
            $sectorByProduct[$sp->idProductoBcg][] = $sp;
        }

        $competitorsByProduct = [];
        foreach ($competitors as $c) {
            if (!$c instanceof BcgCompetitorEntity) {
                continue;
            }
            $competitorsByProduct[$c->idProductoBcg][] = $c;
        }

        $totalVentas = 0.0;
        foreach ($products as $p) {
            if (!$p instanceof BcgProductEntity) {
                continue;
            }
            $p->marketPeriods = $periodsByProduct[$p->idProductoBcg] ?? [];
            $p->competitors = $competitorsByProduct[$p->idProductoBcg] ?? [];
            $p->sectorDemandPeriods = $sectorByProduct[$p->idProductoBcg] ?? [];
            $totalVentas += max(0.0, (float) $p->ventasEmpresa);
        }

        foreach ($products as $p) {
            if (!$p instanceof BcgProductEntity) {
                continue;
            }

            $p->porcentajeVentas = $totalVentas <= 0 ? 0.0 : ((float) $p->ventasEmpresa / $totalVentas);
            $p->tcm = $this->calculateTcm($p->marketPeriods);
            $p->prm = $this->calculatePrm((float) $p->ventasEmpresa, $p->competitors);
            $p->clasificacion = $this->classify($p->tcm, $p->prm);
        }

        foreach ($products as $p) {
            if (!$p instanceof BcgProductEntity) {
                continue;
            }
            if ($p->idProductoBcg <= 0) {
                continue;
            }
            $this->productRepo->update(
                $supabase,
                $p->idProductoBcg,
                [
                    'porcentaje_ventas' => $p->porcentajeVentas,
                    'tcm' => $p->tcm,
                    'prm' => $p->prm,
                    'clasificacion' => $p->clasificacion,
                ]
            );
        }
        $this->resultRepo->insertResult($supabase, $idProyecto, $totalVentas);

        $stateProducts = [];
        foreach ($products as $p) {
            if (!$p instanceof BcgProductEntity) {
                continue;
            }
            $mayor = $this->maxCompetitorSales($p->competitors);
            $arr = BcgProductMapper::toStateArray($p);
            $arr['mayor_competidor'] = $mayor;
            $stateProducts[] = $arr;
        }

        $matrix = $this->matrixService->buildMatrix($products);
        $matrixArr = [];
        foreach ($matrix as $pt) {
            if ($pt instanceof BcgMatrixPointDto) {
                $matrixArr[] = $pt->toArray();
            }
        }

        return new BcgProjectStateDto($totalVentas, gmdate('c'), $stateProducts, $matrixArr);
    }

    private function calculateTcm(array $periods): float
    {
        $rows = [];
        foreach ($periods as $p) {
            if ($p instanceof BcgMarketPeriodEntity) {
                $rows[] = $p;
            }
        }
        usort($rows, fn ($a, $b) => ($a->anio <=> $b->anio) ?: ($a->idPeriodo <=> $b->idPeriodo));
        if (count($rows) < 2) {
            return 0.0;
        }

        $sum = 0.0;
        $count = 0;
        foreach ($rows as $r) {
            $sum += (float) $r->demandaMercado;
            $count++;
        }

        if ($count <= 0) {
            return 0.0;
        }

        $tcm = $sum / $count;
        if ($tcm > 0.2) {
            $tcm = 0.2;
        }
        return $tcm;
    }

    private function maxCompetitorSales(array $competitors): float
    {
        $max = 0.0;
        foreach ($competitors as $c) {
            if (!$c instanceof BcgCompetitorEntity) {
                continue;
            }
            $max = max($max, max(0.0, (float) $c->ventas));
        }
        return $max;
    }

    private function calculatePrm(float $ventasEmpresa, array $competitors): float
    {
        $mayor = $this->maxCompetitorSales($competitors);
        if ($mayor == 0.0) {
            return 0.0;
        }
        $prm = ($ventasEmpresa <= 0 ? 0.0 : ($ventasEmpresa / $mayor));
        if ($prm > 2.0) {
            $prm = 2.0;
        }
        return $prm;
    }

    private function classify(float $tcm, float $prm): string
    {
        if ($prm >= 1.0 && $tcm >= 0.1) {
            return 'ESTRELLA';
        }
        if ($prm >= 1.0 && $tcm < 0.1) {
            return 'VACA';
        }
        if ($prm < 1.0 && $tcm >= 0.1) {
            return 'INTERROGANTE';
        }
        return 'PERRO';
    }
}

final class BcgMatrixService
{
    public function buildMatrix(array $products): array
    {
        $out = [];
        foreach ($products as $p) {
            if (!$p instanceof BcgProductEntity) {
                continue;
            }
            $bubbleSize = (float) $p->porcentajeVentas * 100.0;
            $out[] = new BcgMatrixPointDto(
                $p->nombre,
                (float) $p->prm,
                (float) $p->tcm,
                $p->clasificacion,
                $bubbleSize,
                $this->colorFor($p->clasificacion)
            );
        }
        return $out;
    }

    private function colorFor(string $classification): string
    {
        $c = strtoupper(trim($classification));
        if ($c === 'ESTRELLA') return '#16a34a';
        if ($c === 'VACA') return '#2563eb';
        if ($c === 'INTERROGANTE') return '#f59e0b';
        return '#dc2626';
    }
}

final class BcgProductService
{
    private BcgProductRepository $repo;
    private BcgCalculationService $calc;

    public function __construct()
    {
        $this->repo = new BcgProductRepository();
        $this->calc = new BcgCalculationService();
    }

    public function create(SupabaseClient $supabase, int $idProyecto, string $nombre, float $ventasEmpresa): BcgProjectStateDto
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        if ($ventasEmpresa < 0) {
            throw new InvalidArgumentException('Las ventas deben ser >= 0.');
        }

        $existing = $this->repo->listByProyecto($supabase, $idProyecto);
        $normalized = $this->normalizeName($nombre);
        foreach ($existing as $p) {
            if ($p instanceof BcgProductEntity && $this->normalizeName($p->nombre) === $normalized) {
                throw new InvalidArgumentException('Ya existe un producto con ese nombre en este proyecto.');
            }
        }

        $this->repo->create($supabase, $idProyecto, $nombre, $ventasEmpresa);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }

    public function update(SupabaseClient $supabase, int $idProyecto, int $idProductoBcg, array $fields): BcgProjectStateDto
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        $clean = [];
        if (array_key_exists('nombre', $fields)) {
            $n = trim((string) $fields['nombre']);
            if ($n === '') {
                throw new InvalidArgumentException('El nombre es obligatorio.');
            }
            $existing = $this->repo->listByProyecto($supabase, $idProyecto);
            $normalized = $this->normalizeName($n);
            foreach ($existing as $p) {
                if (!$p instanceof BcgProductEntity) {
                    continue;
                }
                if ($p->idProductoBcg === $idProductoBcg) {
                    continue;
                }
                if ($this->normalizeName($p->nombre) === $normalized) {
                    throw new InvalidArgumentException('Ya existe un producto con ese nombre en este proyecto.');
                }
            }
            $clean['nombre'] = $n;
        }
        if (array_key_exists('ventas_empresa', $fields)) {
            $v = (float) $fields['ventas_empresa'];
            if ($v < 0) {
                throw new InvalidArgumentException('Las ventas deben ser >= 0.');
            }
            $clean['ventas_empresa'] = $v;
        }
        if (empty($clean)) {
            return $this->calc->recalculateProject($supabase, $idProyecto);
        }
        $this->repo->update($supabase, $idProductoBcg, $clean);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }

    public function applyUpdate(SupabaseClient $supabase, int $idProyecto, int $idProductoBcg, array $fields): void
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        $clean = [];
        if (array_key_exists('nombre', $fields)) {
            $n = trim((string) $fields['nombre']);
            if ($n === '') {
                throw new InvalidArgumentException('El nombre es obligatorio.');
            }
            $existing = $this->repo->listByProyecto($supabase, $idProyecto);
            $normalized = $this->normalizeName($n);
            foreach ($existing as $p) {
                if (!$p instanceof BcgProductEntity) {
                    continue;
                }
                if ($p->idProductoBcg === $idProductoBcg) {
                    continue;
                }
                if ($this->normalizeName($p->nombre) === $normalized) {
                    throw new InvalidArgumentException('Ya existe un producto con ese nombre en este proyecto.');
                }
            }
            $clean['nombre'] = $n;
        }
        if (array_key_exists('ventas_empresa', $fields)) {
            $v = (float) $fields['ventas_empresa'];
            if ($v < 0) {
                throw new InvalidArgumentException('Las ventas deben ser >= 0.');
            }
            $clean['ventas_empresa'] = $v;
        }
        if (!empty($clean)) {
            $this->repo->update($supabase, $idProductoBcg, $clean);
        }
    }

    public function delete(SupabaseClient $supabase, int $idProyecto, int $idProductoBcg): BcgProjectStateDto
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        $this->repo->delete($supabase, $idProductoBcg);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($name, 'UTF-8');
        }
        return strtolower($name);
    }
}

final class BcgCompetitorService
{
    private BcgCompetitorRepository $repo;
    private BcgCalculationService $calc;

    public function __construct()
    {
        $this->repo = new BcgCompetitorRepository();
        $this->calc = new BcgCalculationService();
    }

    public function create(SupabaseClient $supabase, int $idProyecto, int $idProductoBcg, string $nombre, float $ventas): BcgProjectStateDto
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del competidor es obligatorio.');
        }
        if ($ventas < 0) {
            throw new InvalidArgumentException('Las ventas deben ser >= 0.');
        }
        $this->repo->create($supabase, $idProductoBcg, $nombre, $ventas);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }

    public function applyCreate(SupabaseClient $supabase, int $idProductoBcg, string $nombre, float $ventas): void
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del competidor es obligatorio.');
        }
        if ($ventas < 0) {
            throw new InvalidArgumentException('Las ventas deben ser >= 0.');
        }
        $this->repo->create($supabase, $idProductoBcg, $nombre, $ventas);
    }

    public function update(SupabaseClient $supabase, int $idProyecto, int $idCompetidor, array $fields): BcgProjectStateDto
    {
        if ($idCompetidor <= 0) {
            throw new InvalidArgumentException('Competidor inválido.');
        }
        $clean = [];
        if (array_key_exists('nombre', $fields)) {
            $n = trim((string) $fields['nombre']);
            if ($n === '') {
                throw new InvalidArgumentException('El nombre del competidor es obligatorio.');
            }
            $clean['nombre'] = $n;
        }
        if (array_key_exists('ventas', $fields)) {
            $v = (float) $fields['ventas'];
            if ($v < 0) {
                throw new InvalidArgumentException('Las ventas deben ser >= 0.');
            }
            $clean['ventas'] = $v;
        }
        if (!empty($clean)) {
            $this->repo->update($supabase, $idCompetidor, $clean);
        }
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }

    public function applyUpdate(SupabaseClient $supabase, int $idCompetidor, array $fields): void
    {
        if ($idCompetidor <= 0) {
            throw new InvalidArgumentException('Competidor inválido.');
        }
        $clean = [];
        if (array_key_exists('nombre', $fields)) {
            $n = trim((string) $fields['nombre']);
            if ($n === '') {
                throw new InvalidArgumentException('El nombre del competidor es obligatorio.');
            }
            $clean['nombre'] = $n;
        }
        if (array_key_exists('ventas', $fields)) {
            $v = (float) $fields['ventas'];
            if ($v < 0) {
                throw new InvalidArgumentException('Las ventas deben ser >= 0.');
            }
            $clean['ventas'] = $v;
        }
        if (!empty($clean)) {
            $this->repo->update($supabase, $idCompetidor, $clean);
        }
    }

    public function delete(SupabaseClient $supabase, int $idProyecto, int $idCompetidor): BcgProjectStateDto
    {
        if ($idCompetidor <= 0) {
            throw new InvalidArgumentException('Competidor inválido.');
        }
        $this->repo->delete($supabase, $idCompetidor);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }
}

final class BcgMarketService
{
    private BcgMarketRepository $repo;
    private BcgCalculationService $calc;

    public function __construct()
    {
        $this->repo = new BcgMarketRepository();
        $this->calc = new BcgCalculationService();
    }

    public function upsertPeriodo(SupabaseClient $supabase, int $idProyecto, int $idProductoBcg, int $anio, float $demandaMercado): BcgProjectStateDto
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        if ($anio < 1900 || $anio > 2100) {
            throw new InvalidArgumentException('Año inválido.');
        }
        if ($demandaMercado < 0) {
            throw new InvalidArgumentException('La demanda de mercado debe ser >= 0.');
        }
        $this->repo->upsertPeriodo($supabase, $idProductoBcg, $anio, $demandaMercado);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }

    public function applyUpsertPeriodo(SupabaseClient $supabase, int $idProductoBcg, int $anio, float $demandaMercado): void
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        if ($anio < 1900 || $anio > 2100) {
            throw new InvalidArgumentException('Año inválido.');
        }
        if ($demandaMercado < 0) {
            throw new InvalidArgumentException('La demanda de mercado debe ser >= 0.');
        }
        $this->repo->upsertPeriodo($supabase, $idProductoBcg, $anio, $demandaMercado);
    }

    public function deletePeriodo(SupabaseClient $supabase, int $idProyecto, int $idPeriodo): BcgProjectStateDto
    {
        if ($idPeriodo <= 0) {
            throw new InvalidArgumentException('Periodo inválido.');
        }
        $this->repo->deletePeriodo($supabase, $idPeriodo);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }
}

final class BcgSectorDemandService
{
    private BcgSectorDemandRepository $repo;
    private BcgCalculationService $calc;

    public function __construct()
    {
        $this->repo = new BcgSectorDemandRepository();
        $this->calc = new BcgCalculationService();
    }

    public function upsertPeriodo(SupabaseClient $supabase, int $idProyecto, int $idProductoBcg, int $anio, float $demandaSector): BcgProjectStateDto
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        if ($anio < 1900 || $anio > 2100) {
            throw new InvalidArgumentException('Año inválido.');
        }
        if ($demandaSector < 0) {
            throw new InvalidArgumentException('La demanda del sector debe ser >= 0.');
        }
        $this->repo->upsertPeriodo($supabase, $idProductoBcg, $anio, $demandaSector);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }

    public function applyUpsertPeriodo(SupabaseClient $supabase, int $idProductoBcg, int $anio, float $demandaSector): void
    {
        if ($idProductoBcg <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        if ($anio < 1900 || $anio > 2100) {
            throw new InvalidArgumentException('Año inválido.');
        }
        if ($demandaSector < 0) {
            throw new InvalidArgumentException('La demanda del sector debe ser >= 0.');
        }
        $this->repo->upsertPeriodo($supabase, $idProductoBcg, $anio, $demandaSector);
    }

    public function deletePeriodo(SupabaseClient $supabase, int $idProyecto, int $idPeriodoSector): BcgProjectStateDto
    {
        if ($idPeriodoSector <= 0) {
            throw new InvalidArgumentException('Periodo inválido.');
        }
        $this->repo->deletePeriodo($supabase, $idPeriodoSector);
        return $this->calc->recalculateProject($supabase, $idProyecto);
    }
}
