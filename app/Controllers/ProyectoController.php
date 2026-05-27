<?php

final class ProyectoController
{
    public function index(): void
    {
        // Protege el módulo: solo usuarios autenticados pueden gestionar proyectos.
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $error = Session::getFlash('error');
        $success = Session::getFlash('success');

        $format = strtolower((string) ($_GET['format'] ?? ''));
        $wantsJson = $format === 'json' || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $recentOnly = ((string) ($_GET['recent'] ?? '')) === '1';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;
        $totalProyectos = 0;
        $totalPages = 1;

        try {
            $supabase = new SupabaseClient();
            $idPersona = (int) ($authUser['id_persona'] ?? 0);
            $ids = [];
            $miembroRows = ProyectoMiembro::listProyectoIdsByPersona($supabase, $idPersona);
            foreach ($miembroRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = (int) ($row['id_proyecto'] ?? 0);
                if ($pid > 0) {
                    $ids[$pid] = true;
                }
            }

            $creadorRows = Proyecto::listByCreador($supabase, $idPersona);
            foreach ($creadorRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = (int) ($row['id_proyecto'] ?? 0);
                if ($pid > 0) {
                    $ids[$pid] = true;
                }
            }

            $idList = array_keys($ids);
            $totalProyectos = count($idList);
            $totalPages = max(1, (int) ceil($totalProyectos / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            if ($wantsJson && $recentOnly) {
                $rows = Proyecto::listByIdsPaged($supabase, $idList, 3, 0, 'id_proyecto.desc');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(
                    [
                        'ok' => true,
                        'projects' => array_map(
                            fn ($p) => [
                                'id_proyecto' => (int) ($p['id_proyecto'] ?? 0),
                                'nombre' => (string) ($p['nombre'] ?? ''),
                            ],
                            array_values(array_filter($rows, fn ($p) => is_array($p) && (int) ($p['id_proyecto'] ?? 0) > 0))
                        ),
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                exit;
            }

            $proyectos = Proyecto::listByIdsPaged($supabase, $idList, $perPage, $offset, 'id_proyecto.desc');
            $proyectos = $this->attachProjectTokens($proyectos);
        } catch (Throwable $e) {
            $proyectos = [];
            $error = $error ?: $this->friendlySupabaseError($e, 'No se pudo cargar la lista de proyectos.');
            if ($wantsJson && $recentOnly) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'No se pudieron cargar los proyectos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }

        require dirname(__DIR__) . '/Views/proyectos/index.php';
    }

    public function createForm(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $error = Session::getFlash('error');
        $success = Session::getFlash('success');

        require dirname(__DIR__) . '/Views/proyectos/nuevo-proyecto.php';
    }

    public function store(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        // Validación mínima antes de insertar en la tabla proyecto.
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') < 3) {
            Session::flash('error', 'El nombre del proyecto es obligatorio (mínimo 3 caracteres).');
            $this->redirect('/nuevo-proyecto.php');
        }

        try {
            $supabase = new SupabaseClient();
            $idProyecto = Proyecto::create($supabase, (int) $authUser['id_persona'], $nombre);
            try {
                ProyectoMiembro::createCreador($supabase, (int) $idProyecto, (int) $authUser['id_persona']);
            } catch (Throwable $e) {
                if ($this->isDebug()) {
                    error_log('[proyecto_store] No se pudo registrar creador en proyecto_miembro. id_proyecto=' . (int) $idProyecto . ' id_persona=' . (int) $authUser['id_persona'] . ' error=' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            Session::flash('error', $this->friendlySupabaseError($e, 'No se pudo crear el proyecto.'));
            $this->redirect('/nuevo-proyecto.php');
        }

        Session::flash('success', 'Proyecto creado correctamente.');
        $token = $this->issueProjectToken($idProyecto);
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token));
    }

    public function show(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $error = Session::getFlash('error');
        $success = Session::getFlash('success');

        $token = trim((string) ($_GET['t'] ?? ''));
        $partial = trim((string) ($_GET['partial'] ?? ''));
        $section = trim((string) ($_GET['section'] ?? 'overview'));
        $allowedSections = ['overview', 'mision', 'vision', 'valores', 'objetivos', 'cadena', 'bgg'];
        $requestedSection = $partial !== '' ? $partial : ($section !== '' ? $section : 'overview');
        if (!in_array($requestedSection, $allowedSections, true)) {
            $requestedSection = 'overview';
        }
        $renderOnlySection = $partial !== '' ? $requestedSection : '';
        $initialPanel = $requestedSection;
        $idProyecto = 0;

        if ($renderOnlySection !== '' && $token === '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Proyecto inválido.';
            exit;
        }

        if ($token !== '') {
            $idProyecto = $this->projectIdFromToken($token);
            if ($idProyecto <= 0) {
                if ($renderOnlySection !== '') {
                    http_response_code(401);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Enlace inválido o expirado.';
                    exit;
                }
                Session::flash('error', 'Enlace inválido o expirado. Regresa a la lista de proyectos.');
                $this->redirect('/proyectos.php');
            }
        } else {
            $idProyecto = (int) ($_GET['id'] ?? 0);
            if ($idProyecto <= 0) {
                Session::flash('error', 'Proyecto inválido.');
                $this->redirect('/proyectos.php');
            }
        }

        try {
            $supabase = new SupabaseClient();
            $idPersona = (int) ($authUser['id_persona'] ?? 0);
            $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, $idPersona);
            if ($proyecto === null) {
                if ($renderOnlySection !== '') {
                    http_response_code(403);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'No tienes acceso a este proyecto.';
                    exit;
                }
                Session::flash('error', 'No tienes acceso a este proyecto.');
                $this->redirect('/proyectos.php');
            }

            $isCreador = $this->isCreadorProyecto($proyecto, $idPersona);

            $dataSection = $renderOnlySection !== '' ? $renderOnlySection : 'overview';
            $mision = null;
            $vision = null;
            $valores = [];
            $misionTexto = '';
            $visionTexto = '';

            if ($dataSection === 'overview' || $dataSection === 'mision') {
                $mision = Mision::findByProyecto($supabase, $idProyecto);
                $misionTexto = is_array($mision) ? (string) ($mision['descripcion'] ?? '') : '';
            }
            if ($dataSection === 'overview' || $dataSection === 'vision') {
                $vision = Vision::findByProyecto($supabase, $idProyecto);
                $visionTexto = is_array($vision) ? (string) ($vision['descripcion'] ?? '') : '';
            }
            if ($dataSection === 'overview' || $dataSection === 'valores') {
                $valores = Valor::listByProyecto($supabase, $idProyecto);
            }

            $miembros = [];
            try {
                if ($dataSection === 'overview' && !empty($isCreador)) {
                    $miembrosRows = ProyectoMiembro::listByProyectoWithPersona($supabase, $idProyecto);
                    if (empty($miembrosRows)) {
                        $miembrosRows = ProyectoMiembro::listByProyecto($supabase, $idProyecto);
                    }
                    $ids = [];
                    foreach ($miembrosRows as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $pid = (int) ($row['id_persona'] ?? 0);
                        if ($pid > 0) {
                            $ids[$pid] = true;
                        }
                    }
                    $creadorId = (int) ($proyecto['creador_id'] ?? 0);
                    if ($creadorId > 0) {
                        $ids[$creadorId] = true;
                    }

                    $personas = [];
                    foreach ($miembrosRows as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $p = $row['persona'] ?? null;
                        if (!is_array($p)) {
                            continue;
                        }
                        $pid = (int) ($row['id_persona'] ?? 0);
                        if ($pid > 0) {
                            $personas[] = ['id_persona' => $pid, 'nombre' => $p['nombre'] ?? null, 'email' => $p['email'] ?? null];
                        }
                    }
                    if (empty($personas)) {
                        $personas = Persona::listByIds($supabase, array_keys($ids));
                    }
                    $byId = [];
                    foreach ($personas as $p) {
                        if (!is_array($p)) {
                            continue;
                        }
                        $pid = (int) ($p['id_persona'] ?? 0);
                        if ($pid > 0) {
                            $byId[$pid] = $p;
                        }
                    }

                    if ($creadorId > 0 && isset($byId[$creadorId])) {
                        $creator = $byId[$creadorId];
                        $miembros[] = [
                            'id_persona' => $creadorId,
                            'nombre' => (string) ($creator['nombre'] ?? ''),
                            'email' => (string) ($creator['email'] ?? ''),
                            'rol' => 'CREADOR',
                        ];
                    }

                    foreach ($miembrosRows as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $pid = (int) ($row['id_persona'] ?? 0);
                        if ($pid <= 0) {
                            continue;
                        }
                        if ($pid === $creadorId) {
                            continue;
                        }
                        $persona = $byId[$pid] ?? null;
                        $miembros[] = [
                            'id_persona' => $pid,
                            'nombre' => (string) (is_array($persona) ? ($persona['nombre'] ?? '') : ''),
                            'email' => (string) (is_array($persona) ? ($persona['email'] ?? '') : ''),
                            'rol' => (string) (($row['rol'] ?? '') ?: 'INVITADO'),
                        ];
                    }
                }
            } catch (Throwable $e) {
                $miembros = [];
            }
        } catch (Throwable $e) {
            Session::flash('error', $this->friendlySupabaseError($e, 'No se pudo cargar el proyecto.'));
            $this->redirect('/proyectos.php');
        }

        $objetivosEstrategicos = [];
        $objetivosEspecificosByEstrategico = [];
        $objetivosError = '';
        $cadenaPreguntas = [];
        $cadenaRespuestas = [];
        $cadenaCalc = [
            'sum' => 0,
            'valid' => 0,
            'count' => 0,
            'missing' => 0,
            'potential' => null,
        ];
        $fodaFortalezas = [];
        $fodaDebilidades = [];
        $bcgFortalezas = [];
        $bcgDebilidades = [];
        $petiAnalysis = null;

        if ($renderOnlySection === 'objetivos') {
            try {
                $objetivosEstrategicos = ObjetivoEstrategico::listByProyecto($supabase, $idProyecto);
                $objetivosEstrategicos = $this->attachObjetivoEstrategicoTokens($objetivosEstrategicos);

                $objetivosEspecificos = ObjetivoEspecifico::listByProyecto($supabase, $idProyecto);
                $objetivosEspecificosByEstrategico = $this->groupObjetivosEspecificosByEstrategicoWithTokens($objetivosEspecificos);

                $objetivosEstrategicos = $this->attachEspecificosCountToObjetivosEstrategicos($objetivosEstrategicos, $objetivosEspecificosByEstrategico);
            } catch (Throwable $e) {
                $objetivosError = $this->isDebug() ? ('No se pudieron cargar los objetivos. Detalle: ' . $e->getMessage()) : 'No se pudieron cargar los objetivos.';
            }
        }

        if ($renderOnlySection === 'cadena') {
            try {
                CadenaValor::ensureSeeded($supabase);
                $cadenaPreguntas = CadenaValor::listPreguntas($supabase);
                $cadenaRespuestas = CadenaValor::listRespuestasByProyecto($supabase, $idProyecto);
                $cadenaCalc = CadenaValor::compute($cadenaPreguntas, $cadenaRespuestas);
            } catch (Throwable $e) {
            }

            try {
                $rows = Foda::listByProyectoFuente($supabase, $idProyecto, 'CADENA_VALOR_INTERNA');
                foreach ($rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $tipo = (string) ($r['tipo'] ?? '');
                    $desc = trim((string) ($r['descripcion'] ?? ''));
                    if ($desc === '') {
                        continue;
                    }
                    if ($tipo === 'FORTALEZA') {
                        $fodaFortalezas[] = $desc;
                    } elseif ($tipo === 'DEBILIDAD') {
                        $fodaDebilidades[] = $desc;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        if ($renderOnlySection === 'bgg') {
            try {
                $rows = Foda::listByProyectoFuente($supabase, $idProyecto, 'AUTODIAGNOSTICO_BCG');
                foreach ($rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $tipo = (string) ($r['tipo'] ?? '');
                    $desc = trim((string) ($r['descripcion'] ?? ''));
                    if ($desc === '') {
                        continue;
                    }
                    if ($tipo === 'FORTALEZA') {
                        $bcgFortalezas[] = $desc;
                    } elseif ($tipo === 'DEBILIDAD') {
                        $bcgDebilidades[] = $desc;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        if ($renderOnlySection === '' || $renderOnlySection === 'overview') {
            try {
                $petiAnalysis = (new PetiAnalysisService())->build($supabase, $idProyecto, $proyecto);
            } catch (Throwable $e) {
                $petiAnalysis = null;
            }
        }

        if ($token === '') {
            $token = $this->issueProjectToken($idProyecto);
            $query = [];
            if (isset($_GET['edit'])) {
                $query['edit'] = (string) $_GET['edit'];
            }
            if (isset($_GET['section'])) {
                $query['section'] = (string) $_GET['section'];
            }
            if (isset($_GET['oe_edit'])) {
                $query['oe_edit'] = (string) $_GET['oe_edit'];
            }
            if (isset($_GET['oesp_edit'])) {
                $query['oesp_edit'] = (string) $_GET['oesp_edit'];
            }
            $qs = http_build_query(array_filter($query, fn ($v) => $v !== ''), '', '&', PHP_QUERY_RFC3986);
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . ($qs ? ('&' . $qs) : ''));
        }

        $projectToken = $token;
        $edit = (string) ($_GET['edit'] ?? '');
        $oeEditToken = trim((string) ($_GET['oe_edit'] ?? ''));
        $oespEditToken = trim((string) ($_GET['oesp_edit'] ?? ''));

        if ($renderOnlySection !== '') {
            header('Content-Type: text/html; charset=utf-8');
            $panel = $renderOnlySection;
            require dirname(__DIR__) . '/Views/proyectos/detalle-panel.php';
            exit;
        }

        require dirname(__DIR__) . '/Views/proyectos/detalle-proyecto.php';
    }

    public function report(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_GET['t'] ?? ''));
        $idProyecto = $token !== '' ? $this->projectIdFromToken($token) : (int) ($_GET['id'] ?? 0);
        if ($idProyecto <= 0) {
            Session::flash('error', 'Proyecto invalido.');
            $this->redirect('/proyectos.php');
        }

        try {
            $supabase = new SupabaseClient();
            $idPersona = (int) ($authUser['id_persona'] ?? 0);
            $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, $idPersona);
            if ($proyecto === null) {
                Session::flash('error', 'No tienes acceso a este proyecto.');
                $this->redirect('/proyectos.php');
            }

            if ($token === '') {
                $token = $this->issueProjectToken($idProyecto);
            }

            $projectToken = $token;
            $petiAnalysis = (new PetiAnalysisService())->build($supabase, $idProyecto, $proyecto);
        } catch (Throwable $e) {
            Session::flash('error', $this->friendlySupabaseError($e, 'No se pudo generar el reporte PETI.'));
            $this->redirect('/proyectos.php');
        }

        require dirname(__DIR__) . '/Views/proyectos/reporte-proyecto.php';
    }

    public function saveFodaCadena(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $payloadRaw = (string) ($_POST['payload'] ?? '');

        header('Content-Type: application/json; charset=utf-8');

        if ($idProyecto <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Proyecto inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $fortalezas = $decoded['fortalezas'] ?? [];
        $debilidades = $decoded['debilidades'] ?? [];
        if (!is_array($fortalezas) || !is_array($debilidades)) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $max = 50;
        $items = [];
        $now = gmdate('Y-m-d H:i:s');

        $i = 0;
        foreach ($fortalezas as $txt) {
            $txt = trim((string) $txt);
            if ($txt === '') {
                continue;
            }
            $i++;
            if ($i > $max) {
                break;
            }
            $items[] = [
                'tipo' => 'FORTALEZA',
                'posicion' => $i,
                'descripcion' => $txt,
                'updated_at' => $now,
            ];
        }

        $j = 0;
        foreach ($debilidades as $txt) {
            $txt = trim((string) $txt);
            if ($txt === '') {
                continue;
            }
            $j++;
            if ($j > $max) {
                break;
            }
            $items[] = [
                'tipo' => 'DEBILIDAD',
                'posicion' => $j,
                'descripcion' => $txt,
                'updated_at' => $now,
            ];
        }

        try {
            $supabase = new SupabaseClient();
            $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
            if ($proyecto === null) {
                echo json_encode(['ok' => false, 'error' => 'No tienes acceso a este proyecto.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $ok = Foda::replaceByProyectoFuente($supabase, $idProyecto, 'CADENA_VALOR_INTERNA', $items);
            if (!$ok) {
                echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el FODA.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            echo json_encode(['ok' => true, 'updated_at' => gmdate('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el FODA.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public function saveFodaBcg(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $payloadRaw = (string) ($_POST['payload'] ?? '');

        header('Content-Type: application/json; charset=utf-8');

        if ($idProyecto <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Proyecto inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $fortalezas = $decoded['fortalezas'] ?? [];
        $debilidades = $decoded['debilidades'] ?? [];
        if (!is_array($fortalezas) || !is_array($debilidades)) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $max = 50;
        $items = [];
        $now = gmdate('Y-m-d H:i:s');

        $i = 0;
        foreach ($fortalezas as $txt) {
            $txt = trim((string) $txt);
            if ($txt === '') continue;
            $i++;
            if ($i > $max) break;
            $items[] = ['tipo' => 'FORTALEZA', 'posicion' => $i, 'descripcion' => $txt, 'updated_at' => $now];
        }

        $j = 0;
        foreach ($debilidades as $txt) {
            $txt = trim((string) $txt);
            if ($txt === '') continue;
            $j++;
            if ($j > $max) break;
            $items[] = ['tipo' => 'DEBILIDAD', 'posicion' => $j, 'descripcion' => $txt, 'updated_at' => $now];
        }

        try {
            $supabase = new SupabaseClient();
            $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
            if ($proyecto === null) {
                echo json_encode(['ok' => false, 'error' => 'No tienes acceso a este proyecto.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $ok = Foda::replaceByProyectoFuente($supabase, $idProyecto, 'AUTODIAGNOSTICO_BCG', $items);
            if (!$ok) {
                echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el apartado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            echo json_encode(['ok' => true, 'updated_at' => gmdate('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el apartado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public function updateProjectName(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');

        if ($idProyecto <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Proyecto inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($nombre === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'El nombre no puede quedar vacío.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            $supabase = new SupabaseClient();
            $proyecto = Proyecto::findById($supabase, $idProyecto);
            if ($proyecto === null) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Proyecto no encontrado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $idPersona = (int) ($authUser['id_persona'] ?? 0);
            if (!$this->isCreadorProyecto($proyecto, $idPersona)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Solo el creador puede editar el nombre.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            Proyecto::updateNombre($supabase, $idProyecto, $nombre);
            echo json_encode(['ok' => true, 'nombre' => $nombre], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Throwable $e) {
            http_response_code(400);
            $msg = $this->friendlySupabaseError($e, 'No se pudo actualizar el nombre.');
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public function inviteMiembro(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $email = trim((string) ($_POST['email'] ?? ''));

        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

        if ($idProyecto <= 0 || $email === '') {
            $this->debugInviteLog('invalid_input', ['id_proyecto' => $idProyecto, 'id_persona' => (int) $authUser['id_persona'], 'email' => $email]);
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'code' => 'INVALID_INPUT', 'error' => 'Datos inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            Session::flash('error', 'Datos inválidos.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
        }

        try {
            $supabase = new SupabaseClient();
            $proyecto = Proyecto::findOwnedById($supabase, $idProyecto, (int) $authUser['id_persona']);
            if ($proyecto === null) {
                $this->debugInviteLog('not_creator', ['id_proyecto' => $idProyecto, 'id_persona' => (int) $authUser['id_persona'], 'email' => $email]);
                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'code' => 'NOT_CREATOR', 'error' => 'Solo el creador puede invitar.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                Session::flash('error', 'Solo el creador puede invitar miembros.');
                $this->redirect('/proyectos.php');
            }

            $persona = Persona::findByEmail($supabase, $email);
            if ($persona === null || (int) ($persona['id_persona'] ?? 0) <= 0) {
                $this->debugInviteLog('user_not_registered', ['id_proyecto' => $idProyecto, 'id_persona' => (int) $authUser['id_persona'], 'email' => $email]);
                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'code' => 'USER_NOT_REGISTERED', 'error' => 'El usuario no está registrado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                Session::flash('error', 'USER_NOT_REGISTERED');
                $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
            }

            $idPersonaInvitada = (int) ($persona['id_persona'] ?? 0);
            $creadorId = (int) ($proyecto['creador_id'] ?? 0);
            if ($idPersonaInvitada === $creadorId) {
                $this->debugInviteLog('already_member_creator', ['id_proyecto' => $idProyecto, 'id_persona' => (int) $authUser['id_persona'], 'invited_id' => $idPersonaInvitada, 'email' => $email]);
                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'code' => 'USER_ALREADY_MEMBER', 'error' => 'El usuario ya es miembro.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                Session::flash('error', 'USER_ALREADY_MEMBER');
                $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
            }

            if (ProyectoMiembro::exists($supabase, $idProyecto, $idPersonaInvitada)) {
                $this->debugInviteLog('already_member', ['id_proyecto' => $idProyecto, 'id_persona' => (int) $authUser['id_persona'], 'invited_id' => $idPersonaInvitada, 'email' => $email]);
                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'code' => 'USER_ALREADY_MEMBER', 'error' => 'El usuario ya es miembro.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                Session::flash('error', 'USER_ALREADY_MEMBER');
                $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
            }

            $ok = ProyectoMiembro::createInvitado($supabase, $idProyecto, $idPersonaInvitada);
            if (!$ok) {
                $this->debugInviteLog('conflict', ['id_proyecto' => $idProyecto, 'id_persona' => (int) $authUser['id_persona'], 'invited_id' => $idPersonaInvitada, 'email' => $email]);
                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'code' => 'USER_ALREADY_MEMBER', 'error' => 'El usuario ya es miembro.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                Session::flash('error', 'USER_ALREADY_MEMBER');
                $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
            }

            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            Session::flash('success', 'Invitación enviada.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
        } catch (Throwable $e) {
            $this->debugInviteLog('error', ['id_proyecto' => $idProyecto, 'id_persona' => (int) $authUser['id_persona'], 'email' => $email, 'error' => $e->getMessage()]);
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'code' => 'ERROR', 'error' => 'No se pudo invitar al usuario.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            Session::flash('error', 'No se pudo invitar al usuario.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
        }
    }

    public function eliminarMiembro(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $idPersona = (int) ($_POST['id_persona'] ?? 0);

        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

        if ($idProyecto <= 0 || $idPersona <= 0) {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'code' => 'INVALID_INPUT', 'error' => 'Datos inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            Session::flash('error', 'Datos inválidos.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
        }

        try {
            $supabase = new SupabaseClient();
            $proyecto = Proyecto::findOwnedById($supabase, $idProyecto, (int) $authUser['id_persona']);
            if ($proyecto === null) {
                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'code' => 'NOT_CREATOR', 'error' => 'Solo el creador puede eliminar.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                Session::flash('error', 'Solo el creador puede eliminar miembros.');
                $this->redirect('/proyectos.php');
            }

            $creadorId = (int) ($proyecto['creador_id'] ?? 0);
            if ($idPersona === $creadorId) {
                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'code' => 'CANNOT_REMOVE_CREATOR', 'error' => 'No se puede eliminar al creador.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                Session::flash('error', 'No se puede eliminar al creador.');
                $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
            }

            if (!ProyectoMiembro::exists($supabase, $idProyecto, $idPersona)) {
                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'code' => 'NOT_MEMBER', 'error' => 'El usuario no es miembro del proyecto.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                Session::flash('error', 'El usuario no es miembro del proyecto.');
                $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
            }

            ProyectoMiembro::delete($supabase, $idProyecto, $idPersona);

            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            Session::flash('success', 'Miembro eliminado.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
        } catch (Throwable $e) {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'code' => 'ERROR', 'error' => 'No se pudo eliminar el miembro.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            Session::flash('error', 'No se pudo eliminar el miembro.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=overview&members=1');
        }
    }

    public function saveCadenaValor(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $idPregunta = (int) ($_POST['id_pregunta'] ?? 0);
        $valor = (int) ($_POST['valor'] ?? -1);

        header('Content-Type: application/json; charset=utf-8');

        if ($idProyecto <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Proyecto inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($idPregunta <= 0 || $valor < 0 || $valor > 4) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            $supabase = new SupabaseClient();
            $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
            if ($proyecto === null) {
                echo json_encode(['ok' => false, 'error' => 'No tienes acceso a este proyecto.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            CadenaValor::ensureSeeded($supabase);
            if (!CadenaValor::existsPregunta($supabase, $idPregunta)) {
                echo json_encode(['ok' => false, 'error' => 'Pregunta inválida.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $ok = CadenaValor::upsertRespuesta($supabase, $idProyecto, $idPregunta, $valor);
            if (!$ok) {
                echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la respuesta.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $preguntas = CadenaValor::listPreguntas($supabase);
            $respuestas = CadenaValor::listRespuestasByProyecto($supabase, $idProyecto);
            $calc = CadenaValor::compute($preguntas, $respuestas);

            if ($calc['potential'] !== null) {
                CadenaValor::upsertResultado($supabase, $idProyecto, (int) $calc['sum'], (float) $calc['potential']);
            }

            echo json_encode(
                [
                    'ok' => true,
                    'calc' => $calc,
                    'updated_at' => gmdate('c'),
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public function saveCadenaValorBatch(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $answersRaw = (string) ($_POST['answers'] ?? '');

        header('Content-Type: application/json; charset=utf-8');

        if ($idProyecto <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Proyecto inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $decoded = json_decode($answersRaw, true);
        if (!is_array($decoded)) {
            echo json_encode(['ok' => false, 'error' => 'Respuestas inválidas.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $answers = [];
        foreach ($decoded as $qid => $value) {
            $qid = (int) $qid;
            $value = (int) $value;
            if ($qid <= 0 || $value < 0 || $value > 4) {
                echo json_encode(['ok' => false, 'error' => 'Respuestas inválidas.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $answers[$qid] = $value;
        }

        try {
            $supabase = new SupabaseClient();
            $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
            if ($proyecto === null) {
                echo json_encode(['ok' => false, 'error' => 'No tienes acceso a este proyecto.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            CadenaValor::ensureSeeded($supabase);
            $preguntas = CadenaValor::listPreguntas($supabase);
            if (empty($preguntas)) {
                echo json_encode(['ok' => false, 'error' => 'No se pudieron cargar las preguntas.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $ids = [];
            foreach ($preguntas as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $id = (int) ($p['id_pregunta'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }

            $count = count($ids);
            if ($count <= 0) {
                echo json_encode(['ok' => false, 'error' => 'No se pudieron cargar las preguntas.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            if (count($answers) !== $count) {
                echo json_encode(['ok' => false, 'error' => 'Debes responder todas las preguntas antes de guardar.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $sum = 0;
            foreach ($ids as $qid => $_) {
                if (!array_key_exists($qid, $answers)) {
                    echo json_encode(['ok' => false, 'error' => 'Debes responder todas las preguntas antes de guardar.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                $sum += (int) $answers[$qid];
            }

            $ok = CadenaValor::upsertRespuestasBatch($supabase, $idProyecto, $answers);
            if (!$ok) {
                echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la evaluación.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $potential = 1 - ($sum / 100);
            CadenaValor::upsertResultado($supabase, $idProyecto, (int) $sum, (float) $potential);

            echo json_encode(
                [
                    'ok' => true,
                    'calc' => [
                        'sum' => (int) $sum,
                        'valid' => (int) $count,
                        'count' => (int) $count,
                        'missing' => 0,
                        'potential' => (float) $potential,
                    ],
                    'updated_at' => gmdate('c'),
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar la evaluación.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public function saveMision(): void
    {
        $this->saveSingleTextBlock('mision', 'mision', 'La misión es obligatoria.');
    }

    public function saveVision(): void
    {
        $this->saveSingleTextBlock('vision', 'vision', 'La visión es obligatoria.');
    }

    public function addValor(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        if ($idProyecto <= 0) {
            Session::flash('error', 'Proyecto inválido.');
            $this->redirect('/proyectos.php');
        }

        if ($descripcion === '' || mb_strlen($descripcion, 'UTF-8') < 2) {
            Session::flash('error', 'El valor es obligatorio.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&edit=valores');
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        Valor::create($supabase, $idProyecto, $descripcion);
        Session::flash('success', 'Valor agregado correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=valores');
    }

    public function saveValores(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $valores = $_POST['valores'] ?? [];

        if ($idProyecto <= 0) {
            if ($this->wantsJson()) {
                $this->jsonError('Proyecto inválido.', 400);
            }
            Session::flash('error', 'Proyecto inválido.');
            $this->redirect('/proyectos.php');
        }

        if (!is_array($valores)) {
            if ($this->wantsJson()) {
                $this->jsonError('Valores inválidos.', 400);
            }
            Session::flash('error', 'Valores inválidos.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&edit=valores');
        }

        $clean = [];
        foreach ($valores as $v) {
            $v = trim((string) $v);
            if ($v === '') {
                continue;
            }
            if (mb_strlen($v, 'UTF-8') < 2) {
                if ($this->wantsJson()) {
                    $this->jsonError('Cada valor debe tener al menos 2 caracteres.', 400);
                }
                Session::flash('error', 'Cada valor debe tener al menos 2 caracteres.');
                $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&edit=valores');
            }
            $clean[] = $v;
        }

        try {
            $supabase = new SupabaseClient();
            $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
            if ($proyecto === null) {
                if ($this->wantsJson()) {
                    $this->jsonError('No tienes acceso a este proyecto.', 403);
                }
                Session::flash('error', 'No tienes acceso a este proyecto.');
                $this->redirect('/proyectos.php');
            }

            Valor::replaceAll($supabase, $idProyecto, $clean);
        } catch (Throwable $e) {
            $msg = $this->friendlySupabaseError($e, 'No se pudieron guardar los valores.');
            if ($this->wantsJson()) {
                $this->jsonError($msg, 400);
            }
            Session::flash('error', $msg);
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=valores&edit=valores');
        }

        if ($this->wantsJson()) {
            $this->jsonOk('Valores guardados correctamente.');
        }
        Session::flash('success', 'Valores guardados correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=valores');
    }

    public function updateValor(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $idValor = (int) ($_POST['id_valor'] ?? 0);
        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        if ($idProyecto <= 0 || $idValor <= 0) {
            Session::flash('error', 'Valor inválido.');
            $this->redirect('/proyectos.php');
        }

        if ($descripcion === '' || mb_strlen($descripcion, 'UTF-8') < 2) {
            Session::flash('error', 'El valor es obligatorio.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&edit=valores');
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        $ok = Valor::update($supabase, $idValor, $idProyecto, $descripcion);
        if (!$ok) {
            Session::flash('error', 'No se pudo actualizar el valor.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=valores');
        }

        Session::flash('success', 'Valor actualizado correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=valores');
    }

    public function createObjetivoEstrategico(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        if ($idProyecto <= 0) {
            if ($this->wantsJson()) {
                $this->jsonError('Proyecto inválido.', 400);
            }
            Session::flash('error', 'Proyecto inválido.');
            $this->redirect('/proyectos.php');
        }

        if ($descripcion === '' || mb_strlen($descripcion, 'UTF-8') < 5) {
            if ($this->wantsJson()) {
                $this->jsonError('La descripción del objetivo estratégico es obligatoria (mínimo 5 caracteres).', 400);
            }
            Session::flash('error', 'La descripción del objetivo estratégico es obligatoria (mínimo 5 caracteres).');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este proyecto.', 403);
            }
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        ObjetivoEstrategico::create($supabase, $idProyecto, $descripcion);
        if ($this->wantsJson()) {
            $this->jsonOk('Objetivo estratégico registrado correctamente.');
        }
        Session::flash('success', 'Objetivo estratégico registrado correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
    }

    public function updateObjetivoEstrategico(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $oeToken = trim((string) ($_POST['oe'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        $idObjetivoEst = $this->objetivoEstrategicoIdFromToken($oeToken);

        if ($idProyecto <= 0 || $idObjetivoEst <= 0) {
            if ($this->wantsJson()) {
                $this->jsonError('Objetivo inválido.', 400);
            }
            Session::flash('error', 'Objetivo inválido.');
            $this->redirect('/proyectos.php');
        }

        if ($descripcion === '' || mb_strlen($descripcion, 'UTF-8') < 5) {
            if ($this->wantsJson()) {
                $this->jsonError('La descripción del objetivo estratégico es obligatoria (mínimo 5 caracteres).', 400);
            }
            Session::flash('error', 'La descripción del objetivo estratégico es obligatoria (mínimo 5 caracteres).');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos&oe_edit=' . urlencode($oeToken));
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este proyecto.', 403);
            }
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        if (!ObjetivoEstrategico::existsInProyecto($supabase, $idObjetivoEst, $idProyecto)) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este objetivo.', 403);
            }
            Session::flash('error', 'No tienes acceso a este objetivo.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        try {
            $ok = ObjetivoEstrategico::update($supabase, $idObjetivoEst, $idProyecto, $descripcion);
        } catch (Throwable $e) {
            $ok = false;
        }
        if (!$ok) {
            if ($this->wantsJson()) {
                $this->jsonError('No se pudo actualizar el objetivo estratégico.', 400);
            }
            Session::flash('error', 'No se pudo actualizar el objetivo estratégico.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos&oe_edit=' . urlencode($oeToken));
        }

        if ($this->wantsJson()) {
            $this->jsonOk('Objetivo estratégico actualizado correctamente.');
        }
        Session::flash('success', 'Objetivo estratégico actualizado correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
    }

    public function deleteObjetivoEstrategico(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $oeToken = trim((string) ($_POST['oe'] ?? ''));
        $idObjetivoEst = $this->objetivoEstrategicoIdFromToken($oeToken);

        if ($idProyecto <= 0 || $idObjetivoEst <= 0) {
            if ($this->wantsJson()) {
                $this->jsonError('Objetivo inválido.', 400);
            }
            Session::flash('error', 'Objetivo inválido.');
            $this->redirect('/proyectos.php');
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este proyecto.', 403);
            }
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        if (!ObjetivoEstrategico::existsInProyecto($supabase, $idObjetivoEst, $idProyecto)) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este objetivo.', 403);
            }
            Session::flash('error', 'No tienes acceso a este objetivo.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        try {
            $ok = ObjetivoEstrategico::delete($supabase, $idObjetivoEst, $idProyecto);
        } catch (Throwable $e) {
            $ok = false;
        }
        if (!$ok) {
            if ($this->wantsJson()) {
                $this->jsonError('No se pudo eliminar el objetivo estratégico.', 400);
            }
            Session::flash('error', 'No se pudo eliminar el objetivo estratégico.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        if ($this->wantsJson()) {
            $this->jsonOk('Objetivo estratégico eliminado correctamente.');
        }
        Session::flash('success', 'Objetivo estratégico eliminado correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
    }

    public function createObjetivoEspecifico(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $oeToken = trim((string) ($_POST['oe'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        $idObjetivoEst = $this->objetivoEstrategicoIdFromToken($oeToken);

        if ($idProyecto <= 0 || $idObjetivoEst <= 0) {
            if ($this->wantsJson()) {
                $this->jsonError('Objetivo inválido.', 400);
            }
            Session::flash('error', 'Objetivo inválido.');
            $this->redirect('/proyectos.php');
        }

        if ($descripcion === '' || mb_strlen($descripcion, 'UTF-8') < 5) {
            if ($this->wantsJson()) {
                $this->jsonError('La descripción del objetivo específico es obligatoria (mínimo 5 caracteres).', 400);
            }
            Session::flash('error', 'La descripción del objetivo específico es obligatoria (mínimo 5 caracteres).');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este proyecto.', 403);
            }
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        if (!ObjetivoEstrategico::existsInProyecto($supabase, $idObjetivoEst, $idProyecto)) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este objetivo.', 403);
            }
            Session::flash('error', 'No tienes acceso a este objetivo.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        ObjetivoEspecifico::create($supabase, $idObjetivoEst, $descripcion);
        if ($this->wantsJson()) {
            $this->jsonOk('Objetivo específico registrado correctamente.');
        }
        Session::flash('success', 'Objetivo específico registrado correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
    }

    public function updateObjetivoEspecifico(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $oeToken = trim((string) ($_POST['oe'] ?? ''));
        $oespToken = trim((string) ($_POST['oesp'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        $idObjetivoEst = $this->objetivoEstrategicoIdFromToken($oeToken);
        $idObjetivoEsp = $this->objetivoEspecificoIdFromToken($oespToken);

        if ($idProyecto <= 0 || $idObjetivoEst <= 0 || $idObjetivoEsp <= 0) {
            if ($this->wantsJson()) {
                $this->jsonError('Objetivo inválido.', 400);
            }
            Session::flash('error', 'Objetivo inválido.');
            $this->redirect('/proyectos.php');
        }

        if ($descripcion === '' || mb_strlen($descripcion, 'UTF-8') < 5) {
            if ($this->wantsJson()) {
                $this->jsonError('La descripción del objetivo específico es obligatoria (mínimo 5 caracteres).', 400);
            }
            Session::flash('error', 'La descripción del objetivo específico es obligatoria (mínimo 5 caracteres).');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos&oesp_edit=' . urlencode($oespToken));
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este proyecto.', 403);
            }
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        if (!ObjetivoEstrategico::existsInProyecto($supabase, $idObjetivoEst, $idProyecto)) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este objetivo.', 403);
            }
            Session::flash('error', 'No tienes acceso a este objetivo.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        if (!ObjetivoEspecifico::existsInObjetivoEstrategico($supabase, $idObjetivoEsp, $idObjetivoEst)) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este objetivo específico.', 403);
            }
            Session::flash('error', 'No tienes acceso a este objetivo específico.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        try {
            $ok = ObjetivoEspecifico::update($supabase, $idObjetivoEsp, $idObjetivoEst, $descripcion);
        } catch (Throwable $e) {
            $ok = false;
        }
        if (!$ok) {
            if ($this->wantsJson()) {
                $this->jsonError('No se pudo actualizar el objetivo específico.', 400);
            }
            Session::flash('error', 'No se pudo actualizar el objetivo específico.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos&oesp_edit=' . urlencode($oespToken));
        }

        if ($this->wantsJson()) {
            $this->jsonOk('Objetivo específico actualizado correctamente.');
        }
        Session::flash('success', 'Objetivo específico actualizado correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
    }

    public function deleteObjetivoEspecifico(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $oeToken = trim((string) ($_POST['oe'] ?? ''));
        $oespToken = trim((string) ($_POST['oesp'] ?? ''));

        $idObjetivoEst = $this->objetivoEstrategicoIdFromToken($oeToken);
        $idObjetivoEsp = $this->objetivoEspecificoIdFromToken($oespToken);

        if ($idProyecto <= 0 || $idObjetivoEst <= 0 || $idObjetivoEsp <= 0) {
            if ($this->wantsJson()) {
                $this->jsonError('Objetivo inválido.', 400);
            }
            Session::flash('error', 'Objetivo inválido.');
            $this->redirect('/proyectos.php');
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este proyecto.', 403);
            }
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        if (!ObjetivoEstrategico::existsInProyecto($supabase, $idObjetivoEst, $idProyecto)) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este objetivo.', 403);
            }
            Session::flash('error', 'No tienes acceso a este objetivo.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        if (!ObjetivoEspecifico::existsInObjetivoEstrategico($supabase, $idObjetivoEsp, $idObjetivoEst)) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este objetivo específico.', 403);
            }
            Session::flash('error', 'No tienes acceso a este objetivo específico.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        try {
            $ok = ObjetivoEspecifico::delete($supabase, $idObjetivoEsp, $idObjetivoEst);
        } catch (Throwable $e) {
            $ok = false;
        }
        if (!$ok) {
            if ($this->wantsJson()) {
                $this->jsonError('No se pudo eliminar el objetivo específico.', 400);
            }
            Session::flash('error', 'No se pudo eliminar el objetivo específico.');
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
        }

        if ($this->wantsJson()) {
            $this->jsonOk('Objetivo específico eliminado correctamente.');
        }
        Session::flash('success', 'Objetivo específico eliminado correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=objetivos');
    }

    private function saveSingleTextBlock(string $block, string $editQuery, string $emptyMessage): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $token = trim((string) ($_POST['t'] ?? ''));
        $idProyecto = $this->projectIdFromToken($token);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        if ($idProyecto <= 0) {
            if ($this->wantsJson()) {
                $this->jsonError('Proyecto inválido.', 400);
            }
            Session::flash('error', 'Proyecto inválido.');
            $this->redirect('/proyectos.php');
        }

        if ($descripcion === '') {
            if ($this->wantsJson()) {
                $this->jsonError($emptyMessage, 400);
            }
            Session::flash('error', $emptyMessage);
            $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=' . urlencode($editQuery) . '&edit=' . $editQuery);
        }

        $supabase = new SupabaseClient();
        $proyecto = $this->findAccessibleProyecto($supabase, $idProyecto, (int) $authUser['id_persona']);
        if ($proyecto === null) {
            if ($this->wantsJson()) {
                $this->jsonError('No tienes acceso a este proyecto.', 403);
            }
            Session::flash('error', 'No tienes acceso a este proyecto.');
            $this->redirect('/proyectos.php');
        }

        if ($block === 'mision') {
            Mision::save($supabase, $idProyecto, $descripcion);
        } elseif ($block === 'vision') {
            Vision::save($supabase, $idProyecto, $descripcion);
        }

        if ($this->wantsJson()) {
            $this->jsonOk('Cambios guardados correctamente.');
        }
        Session::flash('success', 'Cambios guardados correctamente.');
        $this->redirect('/detalle-proyecto.php?t=' . urlencode($token) . '&section=' . urlencode($editQuery));
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $xhr === 'xmlhttprequest';
    }

    private function jsonOk(string $message, array $extra = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function jsonError(string $message, int $status): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function findAccessibleProyecto(SupabaseClient $supabase, int $idProyecto, int $idPersona): ?array
    {
        if ($idProyecto <= 0 || $idPersona <= 0) {
            return null;
        }

        $proyecto = Proyecto::findById($supabase, $idProyecto);
        if ($proyecto === null) {
            return null;
        }

        if ($this->isCreadorProyecto($proyecto, $idPersona)) {
            return $proyecto;
        }

        if (ProyectoMiembro::exists($supabase, $idProyecto, $idPersona)) {
            return $proyecto;
        }

        return null;
    }

    private function isCreadorProyecto(array $proyecto, int $idPersona): bool
    {
        return (int) ($proyecto['creador_id'] ?? 0) === (int) $idPersona;
    }

    private function redirect(string $path): void
    {
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '\\/');
        $location = $basePath === '' ? $path : ($basePath . $path);
        header('Location: ' . $location);
        exit;
    }

    private function debugInviteLog(string $event, array $context): void
    {
        if (!$this->isDebug()) {
            return;
        }
        $email = (string) ($context['email'] ?? '');
        if ($email !== '') {
            $context['email'] = $this->maskEmail($email);
        }
        $payload = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log('[invite_member] ' . $event . ' ' . ($payload ?: ''));
    }

    private function maskEmail(string $email): string
    {
        $email = trim($email);
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email === '' ? '' : '***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $head = mb_substr($local, 0, 1, 'UTF-8');
        return $head . '***@' . $domain;
    }

    private function friendlySupabaseError(Throwable $e, string $prefix): string
    {
        $message = $e->getMessage();
        $lower = strtolower($message);

        $hint = ' Revisa SUPABASE_URL, SUPABASE_ANON_KEY y que existan las tablas (proyecto, mision, vision, valor) en Supabase.';

        if (str_contains($lower, 'permission denied') || str_contains($lower, 'row level security') || str_contains($lower, 'rls')) {
            $hint = ' Falta permiso (RLS). Usa SUPABASE_SERVICE_ROLE_KEY en el .env o configura policies.';
        } elseif (str_contains($lower, 'does not exist') && str_contains($lower, 'relation')) {
            $hint = ' No existen las tablas en la base (ejecuta base-datos.sql en el SQL Editor de Supabase).';
        }

        if ($this->isDebug()) {
            return $prefix . $hint . ' Detalle: ' . $message;
        }

        return $prefix . $hint;
    }

    private function isDebug(): bool
    {
        $value = getenv('APP_DEBUG');
        if ($value === false) {
            return false;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function issueProjectToken(int $idProyecto): string
    {
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

    private function attachProjectTokens(array $proyectos): array
    {
        $out = [];
        foreach ($proyectos as $p) {
            if (!is_array($p)) {
                continue;
            }

            $id = (int) ($p['id_proyecto'] ?? 0);
            if ($id > 0) {
                $p['token'] = $this->issueProjectToken($id);
            }

            $out[] = $p;
        }

        return $out;
    }

    private function issueObjetivoEstrategicoToken(int $idObjetivoEst): string
    {
        Session::start();
        $tokens = Session::get('obj_est_tokens', []);
        if (!is_array($tokens)) {
            $tokens = [];
        }

        foreach ($tokens as $t => $id) {
            if ((int) $id === (int) $idObjetivoEst && is_string($t) && $t !== '') {
                return $t;
            }
        }

        $token = bin2hex(random_bytes(16));
        $tokens[$token] = (int) $idObjetivoEst;
        Session::set('obj_est_tokens', $tokens);
        return $token;
    }

    private function objetivoEstrategicoIdFromToken(string $token): int
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return 0;
        }

        Session::start();
        $tokens = Session::get('obj_est_tokens', []);
        if (!is_array($tokens)) {
            return 0;
        }

        return (int) ($tokens[$token] ?? 0);
    }

    private function issueObjetivoEspecificoToken(int $idObjetivoEsp): string
    {
        Session::start();
        $tokens = Session::get('obj_esp_tokens', []);
        if (!is_array($tokens)) {
            $tokens = [];
        }

        foreach ($tokens as $t => $id) {
            if ((int) $id === (int) $idObjetivoEsp && is_string($t) && $t !== '') {
                return $t;
            }
        }

        $token = bin2hex(random_bytes(16));
        $tokens[$token] = (int) $idObjetivoEsp;
        Session::set('obj_esp_tokens', $tokens);
        return $token;
    }

    private function objetivoEspecificoIdFromToken(string $token): int
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return 0;
        }

        Session::start();
        $tokens = Session::get('obj_esp_tokens', []);
        if (!is_array($tokens)) {
            return 0;
        }

        return (int) ($tokens[$token] ?? 0);
    }

    private function attachObjetivoEstrategicoTokens(array $objetivos): array
    {
        $out = [];
        foreach ($objetivos as $o) {
            if (!is_array($o)) {
                continue;
            }

            $id = (int) ($o['id_objetivo_est'] ?? 0);
            if ($id > 0) {
                $o['token'] = $this->issueObjetivoEstrategicoToken($id);
            }

            $out[] = $o;
        }

        return $out;
    }

    private function groupObjetivosEspecificosByEstrategicoWithTokens(array $especificos): array
    {
        $out = [];
        foreach ($especificos as $e) {
            if (!is_array($e)) {
                continue;
            }

            $idEsp = (int) ($e['id_objetivo_esp'] ?? 0);
            $idEst = (int) ($e['id_objetivo_est'] ?? 0);
            if ($idEsp > 0) {
                $e['token'] = $this->issueObjetivoEspecificoToken($idEsp);
            }

            if (!isset($out[$idEst])) {
                $out[$idEst] = [];
            }

            $out[$idEst][] = $e;
        }

        return $out;
    }

    private function attachEspecificosCountToObjetivosEstrategicos(array $estrategicos, array $especificosByEstrategico): array
    {
        $out = [];
        foreach ($estrategicos as $o) {
            if (!is_array($o)) {
                continue;
            }

            $id = (int) ($o['id_objetivo_est'] ?? 0);
            $o['especificos_count'] = isset($especificosByEstrategico[$id]) ? count($especificosByEstrategico[$id]) : 0;
            $out[] = $o;
        }

        return $out;
    }
}
