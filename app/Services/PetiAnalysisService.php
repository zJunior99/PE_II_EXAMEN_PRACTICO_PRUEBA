<?php

final class PetiAnalysisService
{
    public function build(SupabaseClient $supabase, int $idProyecto, array $proyecto = []): array
    {
        $mision = $this->safe(fn () => Mision::findByProyecto($supabase, $idProyecto), null);
        $vision = $this->safe(fn () => Vision::findByProyecto($supabase, $idProyecto), null);
        $valores = $this->safe(fn () => Valor::listByProyecto($supabase, $idProyecto), []);
        $objetivosEstrategicos = $this->safe(fn () => ObjetivoEstrategico::listByProyecto($supabase, $idProyecto), []);
        $objetivosEspecificos = $this->safe(fn () => ObjetivoEspecifico::listByProyecto($supabase, $idProyecto), []);

        $cadenaPreguntas = $this->safe(function () use ($supabase) {
            CadenaValor::ensureSeeded($supabase);
            return CadenaValor::listPreguntas($supabase);
        }, []);
        $cadenaRespuestas = $this->safe(fn () => CadenaValor::listRespuestasByProyecto($supabase, $idProyecto), []);
        $cadenaCalc = CadenaValor::compute($cadenaPreguntas, $cadenaRespuestas);

        $fodaCadena = $this->safe(fn () => Foda::listByProyectoFuente($supabase, $idProyecto, 'CADENA_VALOR_INTERNA'), []);
        $fodaBcg = $this->safe(fn () => Foda::listByProyectoFuente($supabase, $idProyecto, 'AUTODIAGNOSTICO_BCG'), []);
        $bcg = $this->bcgSummary($supabase, $idProyecto);

        $checks = [
            [
                'key' => 'mision',
                'label' => 'Mision definida',
                'complete' => $this->hasText($mision['descripcion'] ?? ''),
                'detail' => $this->hasText($mision['descripcion'] ?? '') ? 'Documento base registrado.' : 'Pendiente de registrar.',
            ],
            [
                'key' => 'vision',
                'label' => 'Vision definida',
                'complete' => $this->hasText($vision['descripcion'] ?? ''),
                'detail' => $this->hasText($vision['descripcion'] ?? '') ? 'Proyeccion estrategica registrada.' : 'Pendiente de registrar.',
            ],
            [
                'key' => 'valores',
                'label' => 'Valores institucionales',
                'complete' => count($valores) > 0,
                'detail' => count($valores) . ' valor(es) registrado(s).',
            ],
            [
                'key' => 'objetivos_estrategicos',
                'label' => 'Objetivos estrategicos',
                'complete' => count($objetivosEstrategicos) > 0,
                'detail' => count($objetivosEstrategicos) . ' objetivo(s) estrategico(s).',
            ],
            [
                'key' => 'objetivos_especificos',
                'label' => 'Objetivos especificos',
                'complete' => count($objetivosEspecificos) > 0,
                'detail' => count($objetivosEspecificos) . ' objetivo(s) especifico(s).',
            ],
            [
                'key' => 'cadena_valor',
                'label' => 'Cadena de valor',
                'complete' => ((int) ($cadenaCalc['count'] ?? 0) > 0) && ((int) ($cadenaCalc['missing'] ?? 0) === 0),
                'detail' => (int) ($cadenaCalc['valid'] ?? 0) . '/' . (int) ($cadenaCalc['count'] ?? 0) . ' respuestas completadas.',
            ],
            [
                'key' => 'foda',
                'label' => 'FODA estrategico',
                'complete' => (count($fodaCadena) + count($fodaBcg)) >= 4,
                'detail' => (count($fodaCadena) + count($fodaBcg)) . ' elemento(s) FODA registrados.',
            ],
            [
                'key' => 'bcg',
                'label' => 'Matriz BCG',
                'complete' => ((int) ($bcg['products'] ?? 0) > 0) && ((int) ($bcg['results'] ?? 0) > 0),
                'detail' => (int) ($bcg['products'] ?? 0) . ' producto(s), ' . (int) ($bcg['results'] ?? 0) . ' resultado(s).',
            ],
        ];

        $completed = count(array_filter($checks, fn ($item) => !empty($item['complete'])));
        $total = count($checks);
        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
        $status = $this->statusFor($percent);

        $data = [
            'proyecto' => $proyecto,
            'mision' => is_array($mision) ? $mision : null,
            'vision' => is_array($vision) ? $vision : null,
            'valores' => $valores,
            'objetivos_estrategicos' => $objetivosEstrategicos,
            'objetivos_especificos' => $objetivosEspecificos,
            'cadena' => $cadenaCalc,
            'foda_cadena' => $fodaCadena,
            'foda_bcg' => $fodaBcg,
            'bcg' => $bcg,
        ];

        return [
            'percent' => $percent,
            'completed' => $completed,
            'total' => $total,
            'status' => $status,
            'checks' => $checks,
            'summary' => $this->executiveSummary($proyecto, $percent, $status, $data),
            'data' => $data,
            'generated_at' => date('d/m/Y H:i'),
        ];
    }

    private function executiveSummary(array $proyecto, int $percent, array $status, array $data): string
    {
        $nombre = trim((string) ($proyecto['nombre'] ?? 'Proyecto PETI'));
        $mision = trim((string) ($data['mision']['descripcion'] ?? ''));
        $vision = trim((string) ($data['vision']['descripcion'] ?? ''));
        $valoresCount = count((array) ($data['valores'] ?? []));
        $oeCount = count((array) ($data['objetivos_estrategicos'] ?? []));
        $oespCount = count((array) ($data['objetivos_especificos'] ?? []));
        $fodaCount = count((array) ($data['foda_cadena'] ?? [])) + count((array) ($data['foda_bcg'] ?? []));
        $cadena = (array) ($data['cadena'] ?? []);
        $bcg = (array) ($data['bcg'] ?? []);

        $parts = [];
        $parts[] = 'El proyecto "' . $nombre . '" presenta un avance PETI del ' . $percent . '%, ubicado en el nivel "' . $status['label'] . '". Este resultado permite conocer rapidamente el grado de madurez de la formulacion estrategica y priorizar los modulos pendientes.';

        if ($mision !== '' || $vision !== '') {
            $parts[] = 'La formulacion estrategica cuenta con ' . ($mision !== '' ? 'mision' : 'mision pendiente') . ' y ' . ($vision !== '' ? 'vision' : 'vision pendiente') . '. Ademas, se registran ' . $valoresCount . ' valor(es), ' . $oeCount . ' objetivo(s) estrategico(s) y ' . $oespCount . ' objetivo(s) especifico(s).';
        } else {
            $parts[] = 'La formulacion estrategica inicial aun requiere completar la mision y la vision para consolidar la orientacion del plan.';
        }

        $parts[] = 'En el diagnostico, la cadena de valor registra ' . (int) ($cadena['valid'] ?? 0) . ' de ' . (int) ($cadena['count'] ?? 0) . ' respuesta(s), mientras que el FODA contiene ' . $fodaCount . ' elemento(s) derivados del analisis interno y BCG.';

        if ((int) ($bcg['products'] ?? 0) > 0) {
            $parts[] = 'La matriz BCG contiene ' . (int) $bcg['products'] . ' producto(s) analizado(s), lo que fortalece la evaluacion de posicionamiento y cartera estrategica.';
        } else {
            $parts[] = 'Como siguiente paso, se recomienda completar la matriz BCG para relacionar productos, ventas, competidores y posicionamiento estrategico.';
        }

        return implode("\n\n", $parts);
    }

    private function statusFor(int $percent): array
    {
        if ($percent >= 85) {
            return ['label' => 'Completo', 'tone' => 'emerald', 'description' => 'El PETI esta practicamente listo para presentacion.'];
        }
        if ($percent >= 60) {
            return ['label' => 'Avanzado', 'tone' => 'lime', 'description' => 'El proyecto tiene una base solida y requiere completar detalles.'];
        }
        if ($percent >= 35) {
            return ['label' => 'En progreso', 'tone' => 'amber', 'description' => 'El PETI ya inicio, pero aun tiene modulos relevantes pendientes.'];
        }

        return ['label' => 'Inicial', 'tone' => 'red', 'description' => 'El proyecto requiere completar sus componentes principales.'];
    }

    private function bcgSummary(SupabaseClient $supabase, int $idProyecto): array
    {
        $products = $this->bcgProducts($supabase, $idProyecto);

        return [
            'products' => count($products),
            'results' => $this->countRows($supabase, '/rest/v1/bcg_resultado', ['id_proyecto' => 'eq.' . $idProyecto]),
            'product_rows' => $products,
        ];
    }

    private function bcgProducts(SupabaseClient $supabase, int $idProyecto): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/bcg_producto',
            [
                'select' => 'id_producto_bcg,nombre,ventas_empresa,porcentaje_ventas,tcm,prm,clasificacion',
                'id_proyecto' => 'eq.' . $idProyecto,
                'order' => 'id_producto_bcg.asc',
                'limit' => 1000,
            ],
            $this->headers($supabase)
        );

        if ((int) ($response['status'] ?? 500) >= 400 || !is_array($response['data'] ?? null)) {
            return [];
        }

        return array_values(array_filter($response['data'], fn ($row) => is_array($row)));
    }

    private function countRows(SupabaseClient $supabase, string $path, array $query): int
    {
        $query['select'] = '*';
        $query['limit'] = 1000;
        $response = $supabase->request('GET', $path, $query, $this->headers($supabase));
        if ((int) ($response['status'] ?? 500) >= 400 || !is_array($response['data'] ?? null)) {
            return 0;
        }

        return count($response['data']);
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

    private function hasText(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }

    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}
