<?php

require __DIR__ . '/../app/Core/Session.php';
require __DIR__ . '/../app/Services/SupabaseClient.php';
require __DIR__ . '/../app/Controllers/AuthController.php';

$controller = new AuthController();
$authUser = $controller->requireAuth();

$dashboardError = '';
$dashboardPayload = [
    'updated_at' => gmdate('c'),
    'metrics' => [
        'total_rutas' => 0,
        'rutas_activas' => 0,
        'rutas_inactivas' => 0,
        'objetivos_estrategicos' => 0,
        'objetivos_especificos' => 0,
        'valores' => 0,
        'usuarios_registrados' => null,
        'usuarios_activos' => null,
    ],
    'projects' => [],
    'chart' => [
        'labels' => [],
        'series' => [],
    ],
];

function dashboardRestHeaders(SupabaseClient $supabase): array
{
    $serverKey = $supabase->getServiceRoleKey();
    $apiKey = $serverKey ?: $supabase->getAnonKey();
    $authBearer = $serverKey ?: $supabase->getAnonKey();

    return [
        'apikey' => $apiKey,
        'Authorization' => 'Bearer ' . $authBearer,
    ];
}

function dashboardInList(array $ids): string
{
    $ids = array_values(array_filter(array_map(
        fn ($v) => (int) $v,
        $ids
    ), fn ($v) => $v > 0));

    if (empty($ids)) {
        return 'in.()';
    }

    return 'in.(' . implode(',', $ids) . ')';
}

function dashboardIssueProjectToken(int $idProyecto): string
{
    if ($idProyecto <= 0) {
        return '';
    }

    Session::start();
    $tokens = Session::get('project_tokens', []);
    if (!is_array($tokens)) {
        $tokens = [];
    }

    foreach ($tokens as $t => $id) {
        if ((int) $id === (int) $idProyecto && is_string($t) && $t !== '') {
            return $t;
        }
    }

    $token = bin2hex(random_bytes(16));
    $tokens[$token] = (int) $idProyecto;
    Session::set('project_tokens', $tokens);
    return $token;
}

try {
    $supabase = new SupabaseClient();
    $headers = dashboardRestHeaders($supabase);

    $idPersona = (int) ($authUser['id_persona'] ?? 0);
    $projectsById = [];

    $miembroRes = $supabase->request(
        'GET',
        '/rest/v1/proyecto_miembro',
        [
            'select' => 'id_proyecto,proyecto(id_proyecto,nombre,creador_id)',
            'id_persona' => 'eq.' . $idPersona,
            'order' => 'id.desc',
        ],
        $headers
    );
    if ($miembroRes['status'] < 400 && is_array($miembroRes['data'])) {
        foreach ($miembroRes['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $p = $row['proyecto'] ?? null;
            if (!is_array($p)) {
                continue;
            }
            $pid = (int) ($p['id_proyecto'] ?? 0);
            if ($pid > 0) {
                $projectsById[$pid] = $p;
            }
        }
    }

    $creadorRes = $supabase->request(
        'GET',
        '/rest/v1/proyecto',
        [
            'select' => 'id_proyecto,nombre,creador_id',
            'creador_id' => 'eq.' . $idPersona,
            'order' => 'id_proyecto.desc',
        ],
        $headers
    );
    if ($creadorRes['status'] >= 400) {
        throw new RuntimeException((string) ($creadorRes['data']['message'] ?? $creadorRes['data']['msg'] ?? 'No se pudo cargar el dashboard.'));
    }
    if (is_array($creadorRes['data'])) {
        foreach ($creadorRes['data'] as $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = (int) ($p['id_proyecto'] ?? 0);
            if ($pid > 0) {
                $projectsById[$pid] = $p;
            }
        }
    }

    $projects = array_values($projectsById);
    $projectIds = [];
    foreach ($projects as $p) {
        if (!is_array($p)) {
            continue;
        }
        $projectIds[] = (int) ($p['id_proyecto'] ?? 0);
    }

    $missionsByProject = [];
    $visionsByProject = [];
    $valoresCountByProject = [];
    $objEstCountByProject = [];
    $objEspCountByProject = [];

    if (!empty($projectIds)) {
        $inProjects = dashboardInList($projectIds);

        $misionRes = $supabase->request(
            'GET',
            '/rest/v1/mision',
            [
                'select' => 'id_proyecto',
                'id_proyecto' => $inProjects,
            ],
            $headers
        );
        if ($misionRes['status'] < 400 && is_array($misionRes['data'])) {
            foreach ($misionRes['data'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = (int) ($row['id_proyecto'] ?? 0);
                if ($pid > 0) {
                    $missionsByProject[$pid] = true;
                }
            }
        }

        $visionRes = $supabase->request(
            'GET',
            '/rest/v1/vision',
            [
                'select' => 'id_proyecto',
                'id_proyecto' => $inProjects,
            ],
            $headers
        );
        if ($visionRes['status'] < 400 && is_array($visionRes['data'])) {
            foreach ($visionRes['data'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = (int) ($row['id_proyecto'] ?? 0);
                if ($pid > 0) {
                    $visionsByProject[$pid] = true;
                }
            }
        }

        $valorRes = $supabase->request(
            'GET',
            '/rest/v1/valor',
            [
                'select' => 'id_proyecto,id_valor',
                'id_proyecto' => $inProjects,
            ],
            $headers
        );
        if ($valorRes['status'] < 400 && is_array($valorRes['data'])) {
            foreach ($valorRes['data'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = (int) ($row['id_proyecto'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $valoresCountByProject[$pid] = ($valoresCountByProject[$pid] ?? 0) + 1;
            }
        }

        $objEstRes = $supabase->request(
            'GET',
            '/rest/v1/objetivo_estrategico',
            [
                'select' => 'id_objetivo_est,id_proyecto',
                'id_proyecto' => $inProjects,
            ],
            $headers
        );

        $objEstIds = [];
        $objEstToProject = [];
        if ($objEstRes['status'] < 400 && is_array($objEstRes['data'])) {
            foreach ($objEstRes['data'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = (int) ($row['id_proyecto'] ?? 0);
                $oeid = (int) ($row['id_objetivo_est'] ?? 0);
                if ($pid <= 0 || $oeid <= 0) {
                    continue;
                }
                $objEstIds[] = $oeid;
                $objEstToProject[$oeid] = $pid;
                $objEstCountByProject[$pid] = ($objEstCountByProject[$pid] ?? 0) + 1;
            }
        }

        if (!empty($objEstIds)) {
            $objEspRes = $supabase->request(
                'GET',
                '/rest/v1/objetivo_especifico',
                [
                    'select' => 'id_objetivo_esp,id_objetivo_est',
                    'id_objetivo_est' => dashboardInList($objEstIds),
                ],
                $headers
            );
            if ($objEspRes['status'] < 400 && is_array($objEspRes['data'])) {
                foreach ($objEspRes['data'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $oeid = (int) ($row['id_objetivo_est'] ?? 0);
                    $pid = (int) ($objEstToProject[$oeid] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    $objEspCountByProject[$pid] = ($objEspCountByProject[$pid] ?? 0) + 1;
                }
            }
        }
    }

    $totalRutas = 0;
    $rutasActivas = 0;
    $rutasInactivas = 0;
    $totalValores = 0;
    $totalObjEst = 0;
    $totalObjEsp = 0;

    $projectRows = [];
    foreach ($projects as $p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = (int) ($p['id_proyecto'] ?? 0);
        if ($pid <= 0) {
            continue;
        }

        $totalRutas++;

        $hasMision = !empty($missionsByProject[$pid]);
        $hasVision = !empty($visionsByProject[$pid]);
        $valoresCount = (int) ($valoresCountByProject[$pid] ?? 0);
        $objEstCount = (int) ($objEstCountByProject[$pid] ?? 0);
        $objEspCount = (int) ($objEspCountByProject[$pid] ?? 0);

        $totalValores += $valoresCount;
        $totalObjEst += $objEstCount;
        $totalObjEsp += $objEspCount;

        $isActive = $hasMision && $hasVision && $valoresCount > 0 && $objEstCount > 0;
        if ($isActive) {
            $rutasActivas++;
        }

        $statusLabel = $isActive ? 'Activo' : (($hasMision || $hasVision || $valoresCount > 0 || $objEstCount > 0) ? 'En progreso' : 'Borrador');
        $projectRows[] = [
            'id' => $pid,
            'token' => dashboardIssueProjectToken($pid),
            'nombre' => (string) ($p['nombre'] ?? ''),
            'status' => $statusLabel,
            'has_mision' => $hasMision,
            'has_vision' => $hasVision,
            'valores' => $valoresCount,
            'obj_est' => $objEstCount,
            'obj_esp' => $objEspCount,
        ];
    }

    $rutasInactivas = max(0, $totalRutas - $rutasActivas);

    $usuariosRegistrados = null;
    $usuariosActivos = null;
    if ($supabase->getServiceRoleKey()) {
        $personaRes = $supabase->request(
            'GET',
            '/rest/v1/persona',
            [
                'select' => 'id_persona',
                'order' => 'id_persona.desc',
                'limit' => 1000,
            ],
            $headers
        );
        if ($personaRes['status'] < 400 && is_array($personaRes['data'])) {
            $usuariosRegistrados = count($personaRes['data']);
        }

        $proyAllRes = $supabase->request(
            'GET',
            '/rest/v1/proyecto',
            [
                'select' => 'creador_id',
                'order' => 'id_proyecto.desc',
                'limit' => 1000,
            ],
            $headers
        );
        if ($proyAllRes['status'] < 400 && is_array($proyAllRes['data'])) {
            $set = [];
            foreach ($proyAllRes['data'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cid = (int) ($row['creador_id'] ?? 0);
                if ($cid > 0) {
                    $set[$cid] = true;
                }
            }
            $usuariosActivos = count($set);
        }
    }

    if ($usuariosActivos === null) {
        $usuariosActivos = 1;
    } else {
        $usuariosActivos = max(1, (int) $usuariosActivos);
    }

    $top = $projectRows;
    usort($top, fn ($a, $b) => ((int) ($b['obj_esp'] ?? 0) <=> (int) ($a['obj_esp'] ?? 0)) ?: ((int) ($b['obj_est'] ?? 0) <=> (int) ($a['obj_est'] ?? 0)));
    $top = array_slice($top, 0, 6);
    $chartLabels = [];
    $chartSeries = [];
    foreach ($top as $row) {
        $chartLabels[] = (string) ($row['nombre'] ?? '');
        $chartSeries[] = [
            'obj_est' => (int) ($row['obj_est'] ?? 0),
            'obj_esp' => (int) ($row['obj_esp'] ?? 0),
        ];
    }

    $dashboardPayload = [
        'updated_at' => gmdate('c'),
        'metrics' => [
            'total_rutas' => $totalRutas,
            'rutas_activas' => $rutasActivas,
            'rutas_inactivas' => $rutasInactivas,
            'objetivos_estrategicos' => $totalObjEst,
            'objetivos_especificos' => $totalObjEsp,
            'valores' => $totalValores,
            'usuarios_registrados' => $usuariosRegistrados,
            'usuarios_activos' => $usuariosActivos,
        ],
        'projects' => $projectRows,
        'chart' => [
            'labels' => $chartLabels,
            'series' => $chartSeries,
        ],
    ];
} catch (Throwable $e) {
    $dashboardError = $e->getMessage();
}

if (isset($_GET['format']) && (string) $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        [
            'ok' => $dashboardError === '',
            'error' => $dashboardError === '' ? null : $dashboardError,
            'payload' => $dashboardPayload,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

require __DIR__ . '/../app/Views/dashboard/index.php';

