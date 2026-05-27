<?php

final class BcgController
{
    private BcgProductService $productService;
    private BcgCompetitorService $competitorService;
    private BcgMarketService $marketService;
    private BcgSectorDemandService $sectorDemandService;
    private BcgCalculationService $calcService;

    public function __construct()
    {
        $this->productService = new BcgProductService();
        $this->competitorService = new BcgCompetitorService();
        $this->marketService = new BcgMarketService();
        $this->sectorDemandService = new BcgSectorDemandService();
        $this->calcService = new BcgCalculationService();
    }

    public function getStateJson(): void
    {
        $auth = (new AuthController())->requireAuth();
        $token = trim((string) ($_GET['t'] ?? ''));
        $idProyecto = $this->resolveProjectId($token);

        header('Content-Type: application/json; charset=utf-8');

        if ($idProyecto <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Proyecto inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            $supabase = new SupabaseClient();
            $idPersona = (int) ($auth['id_persona'] ?? 0);
            if (!$this->hasAccess($supabase, $idProyecto, $idPersona)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'No tienes acceso a este proyecto.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $dto = $this->calcService->recalculateProject($supabase, $idProyecto);
            echo json_encode(['ok' => true, 'payload' => $dto->toArray()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            $debug = (string) getenv('APP_DEBUG');
            $msg = ($debug === '1' || strtolower($debug) === 'true') ? ('No se pudo cargar BCG: ' . $e->getMessage()) : 'No se pudo cargar BCG.';
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public function createProductJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $nombre = (string) ($_POST['nombre'] ?? '');
            $ventas = (float) ($_POST['ventas_empresa'] ?? ($_POST['ventasEmpresa'] ?? 0));
            return $this->productService->create($supabase, $idProyecto, $nombre, $ventas);
        });
    }

    public function updateProductJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idProducto = (int) ($_POST['id_producto_bcg'] ?? 0);
            $fields = [];
            if (isset($_POST['nombre'])) $fields['nombre'] = (string) $_POST['nombre'];
            if (isset($_POST['ventas_empresa'])) $fields['ventas_empresa'] = (float) $_POST['ventas_empresa'];
            if (isset($_POST['ventasEmpresa'])) $fields['ventas_empresa'] = (float) $_POST['ventasEmpresa'];
            return $this->productService->update($supabase, $idProyecto, $idProducto, $fields);
        });
    }

    public function deleteProductJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idProducto = (int) ($_POST['id_producto_bcg'] ?? 0);
            return $this->productService->delete($supabase, $idProyecto, $idProducto);
        });
    }

    public function createCompetitorJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idProducto = (int) ($_POST['id_producto_bcg'] ?? 0);
            $nombre = (string) ($_POST['nombre'] ?? '');
            $ventas = (float) ($_POST['ventas'] ?? 0);
            return $this->competitorService->create($supabase, $idProyecto, $idProducto, $nombre, $ventas);
        });
    }

    public function updateCompetitorJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idCompetidor = (int) ($_POST['id_competidor'] ?? 0);
            $fields = [];
            if (isset($_POST['nombre'])) $fields['nombre'] = (string) $_POST['nombre'];
            if (isset($_POST['ventas'])) $fields['ventas'] = (float) $_POST['ventas'];
            return $this->competitorService->update($supabase, $idProyecto, $idCompetidor, $fields);
        });
    }

    public function deleteCompetitorJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idCompetidor = (int) ($_POST['id_competidor'] ?? 0);
            return $this->competitorService->delete($supabase, $idProyecto, $idCompetidor);
        });
    }

    public function upsertMarketPeriodJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idProducto = (int) ($_POST['id_producto_bcg'] ?? 0);
            $anio = (int) ($_POST['anio'] ?? 0);
            $demanda = (float) ($_POST['demanda_mercado'] ?? 0);
            return $this->marketService->upsertPeriodo($supabase, $idProyecto, $idProducto, $anio, $demanda);
        });
    }

    public function deleteMarketPeriodJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idPeriodo = (int) ($_POST['id_periodo'] ?? 0);
            return $this->marketService->deletePeriodo($supabase, $idProyecto, $idPeriodo);
        });
    }

    public function deleteMarketYearBatchJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $anio = (int) ($_POST['anio'] ?? 0);
            if ($anio < 1900 || $anio > 2100) {
                throw new InvalidArgumentException('Año inválido.');
            }

            $productRepo = new BcgProductRepository();
            $marketRepo = new BcgMarketRepository();
            $sectorRepo = new BcgSectorDemandRepository();

            $products = $productRepo->listByProyecto($supabase, $idProyecto);
            $ids = [];
            foreach ($products as $p) {
                if ($p instanceof BcgProductEntity && $p->idProductoBcg > 0) {
                    $ids[] = $p->idProductoBcg;
                }
            }

            if (!empty($ids)) {
                $periods = $marketRepo->listByProductoIds($supabase, $ids);
                foreach ($periods as $mp) {
                    if (!$mp instanceof BcgMarketPeriodEntity) {
                        continue;
                    }
                    if ((int) $mp->anio !== $anio) {
                        continue;
                    }
                    if ((int) $mp->idPeriodo <= 0) {
                        continue;
                    }
                    $marketRepo->deletePeriodo($supabase, (int) $mp->idPeriodo);
                }

                $sectorPeriods = $sectorRepo->listByProductoIds($supabase, $ids);
                foreach ($sectorPeriods as $sp) {
                    if (!$sp instanceof BcgSectorDemandPeriodEntity) {
                        continue;
                    }
                    if ((int) $sp->anio !== $anio) {
                        continue;
                    }
                    if ((int) $sp->idPeriodoSector <= 0) {
                        continue;
                    }
                    $sectorRepo->deletePeriodo($supabase, (int) $sp->idPeriodoSector);
                }
            }

            return $this->calcService->recalculateProject($supabase, $idProyecto);
        });
    }

    public function upsertSectorDemandPeriodJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idProducto = (int) ($_POST['id_producto_bcg'] ?? 0);
            $anio = (int) ($_POST['anio'] ?? 0);
            $demanda = (float) ($_POST['demanda_sector'] ?? 0);
            return $this->sectorDemandService->upsertPeriodo($supabase, $idProyecto, $idProducto, $anio, $demanda);
        });
    }

    public function deleteSectorDemandPeriodJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $idPeriodo = (int) ($_POST['id_periodo_sector'] ?? 0);
            return $this->sectorDemandService->deletePeriodo($supabase, $idProyecto, $idPeriodo);
        });
    }

    public function recalculateJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            return $this->calcService->recalculateProject($supabase, $idProyecto);
        });
    }

    public function saveProductsBatchJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $raw = (string) ($_POST['payload'] ?? '');
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Payload inválido.');
            }
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $idProducto = (int) ($row['id_producto_bcg'] ?? 0);
                if ($idProducto <= 0) {
                    continue;
                }
                $fields = [];
                if (array_key_exists('nombre', $row)) $fields['nombre'] = (string) $row['nombre'];
                if (array_key_exists('ventas_empresa', $row)) $fields['ventas_empresa'] = (float) $row['ventas_empresa'];
                $this->productService->applyUpdate($supabase, $idProyecto, $idProducto, $fields);
            }
            return $this->calcService->recalculateProject($supabase, $idProyecto);
        });
    }

    public function saveMarketRatesBatchJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $raw = (string) ($_POST['payload'] ?? '');
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Payload inválido.');
            }
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $idProducto = (int) ($row['id_producto_bcg'] ?? 0);
                $anio = (int) ($row['anio'] ?? 0);
                $tasa = (float) ($row['demanda_mercado'] ?? 0);
                if ($idProducto <= 0 || $anio <= 0) {
                    continue;
                }
                $this->marketService->applyUpsertPeriodo($supabase, $idProducto, $anio, $tasa);
            }
            return $this->calcService->recalculateProject($supabase, $idProyecto);
        });
    }

    public function saveSectorDemandBatchJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $raw = (string) ($_POST['payload'] ?? '');
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Payload inválido.');
            }
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $idProducto = (int) ($row['id_producto_bcg'] ?? 0);
                $anio = (int) ($row['anio'] ?? 0);
                $demanda = (float) ($row['demanda_sector'] ?? 0);
                if ($idProducto <= 0 || $anio <= 0) {
                    continue;
                }
                $this->sectorDemandService->applyUpsertPeriodo($supabase, $idProducto, $anio, $demanda);
            }
            return $this->calcService->recalculateProject($supabase, $idProyecto);
        });
    }

    public function saveCompetitorsBatchJson(): void
    {
        $this->handleJsonAction(function (SupabaseClient $supabase, array $auth, int $idProyecto): BcgProjectStateDto {
            $raw = (string) ($_POST['payload'] ?? '');
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Payload inválido.');
            }
            $updates = $decoded['updates'] ?? [];
            $creates = $decoded['creates'] ?? [];
            if (!is_array($updates) || !is_array($creates)) {
                throw new InvalidArgumentException('Payload inválido.');
            }

            foreach ($updates as $row) {
                if (!is_array($row)) continue;
                $idCompetidor = (int) ($row['id_competidor'] ?? 0);
                if ($idCompetidor <= 0) continue;
                $fields = [];
                if (array_key_exists('nombre', $row)) $fields['nombre'] = (string) $row['nombre'];
                if (array_key_exists('ventas', $row)) $fields['ventas'] = (float) $row['ventas'];
                $this->competitorService->applyUpdate($supabase, $idCompetidor, $fields);
            }

            foreach ($creates as $row) {
                if (!is_array($row)) continue;
                $idProducto = (int) ($row['id_producto_bcg'] ?? 0);
                $nombre = (string) ($row['nombre'] ?? '');
                $ventas = (float) ($row['ventas'] ?? 0);
                if ($idProducto <= 0) continue;
                $this->competitorService->applyCreate($supabase, $idProducto, $nombre, $ventas);
            }

            return $this->calcService->recalculateProject($supabase, $idProyecto);
        });
    }

    private function handleJsonAction(callable $fn): void
    {
        $auth = (new AuthController())->requireAuth();
        $token = trim((string) ($_POST['t'] ?? $_GET['t'] ?? ''));
        $idProyecto = $this->resolveProjectId($token);

        header('Content-Type: application/json; charset=utf-8');

        if ($idProyecto <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Proyecto inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            $supabase = new SupabaseClient();
            $idPersona = (int) ($auth['id_persona'] ?? 0);
            if (!$this->hasAccess($supabase, $idProyecto, $idPersona)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'No tienes acceso a este proyecto.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $dto = $fn($supabase, $auth, $idProyecto);
            echo json_encode(['ok' => true, 'payload' => $dto->toArray()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            $debug = (string) getenv('APP_DEBUG');
            $msg = ($debug === '1' || strtolower($debug) === 'true') ? ('No se pudo procesar la solicitud: ' . $e->getMessage()) : 'No se pudo procesar la solicitud.';
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    private function projectIdFromToken(string $token): int
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return 0;
        }
        Session::start();
        $tokens = Session::get('project_tokens', []);
        if (!is_array($tokens)) {
            return 0;
        }
        $id = $tokens[$token] ?? 0;
        return (int) $id;
    }

    private function resolveProjectId(string $token): int
    {
        $byToken = $this->projectIdFromToken($token);
        if ($byToken > 0) {
            return $byToken;
        }

        $fallback = (int) ($_POST['id_proyecto'] ?? $_GET['id_proyecto'] ?? 0);
        if ($fallback > 0) {
            return $fallback;
        }

        return 0;
    }

    private function hasAccess(SupabaseClient $supabase, int $idProyecto, int $idPersona): bool
    {
        $proyecto = Proyecto::findById($supabase, $idProyecto);
        if ($proyecto === null) {
            return false;
        }
        $creadorId = (int) ($proyecto['creador_id'] ?? 0);
        if ($creadorId === $idPersona) {
            return true;
        }
        return ProyectoMiembro::exists($supabase, $idProyecto, $idPersona);
    }
}
