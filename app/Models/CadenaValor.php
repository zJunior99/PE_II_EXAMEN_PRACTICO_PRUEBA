<?php

final class CadenaValor
{
    public static function ensureSeeded(SupabaseClient $supabase): void
    {
        $check = $supabase->request(
            'GET',
            '/rest/v1/cadena_valor_pregunta',
            [
                'select' => 'id_pregunta',
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($check['status'] >= 400) {
            return;
        }

        if (is_array($check['data']) && !empty($check['data'])) {
            return;
        }

        $questions = self::defaultQuestions();
        $rows = [];
        foreach ($questions as $number => $text) {
            $rows[] = [
                'numero' => (int) $number,
                'texto' => (string) $text,
            ];
        }

        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'return=representation';

        $supabase->request(
            'POST',
            '/rest/v1/cadena_valor_pregunta',
            [],
            $headers,
            $rows
        );
    }

    public static function listPreguntas(SupabaseClient $supabase): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/cadena_valor_pregunta',
            [
                'select' => 'id_pregunta,numero,texto',
                'order' => 'numero.asc',
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            return [];
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public static function listRespuestasByProyecto(SupabaseClient $supabase, int $idProyecto): array
    {
        $response = $supabase->request(
            'GET',
            '/rest/v1/cadena_valor_respuesta',
            [
                'select' => 'id_pregunta,valor',
                'id_proyecto' => 'eq.' . $idProyecto,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400 || !is_array($response['data'])) {
            return [];
        }

        $map = [];
        foreach ($response['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $qid = (int) ($row['id_pregunta'] ?? 0);
            $value = (int) ($row['valor'] ?? -1);
            if ($qid <= 0 || $value < 0 || $value > 4) {
                continue;
            }
            $map[$qid] = $value;
        }

        return $map;
    }

    public static function existsPregunta(SupabaseClient $supabase, int $idPregunta): bool
    {
        if ($idPregunta <= 0) {
            return false;
        }

        $response = $supabase->request(
            'GET',
            '/rest/v1/cadena_valor_pregunta',
            [
                'select' => 'id_pregunta',
                'id_pregunta' => 'eq.' . $idPregunta,
                'limit' => 1,
            ],
            self::restHeaders($supabase)
        );

        if ($response['status'] >= 400) {
            return false;
        }

        return is_array($response['data']) && !empty($response['data']);
    }

    public static function upsertRespuesta(SupabaseClient $supabase, int $idProyecto, int $idPregunta, int $valor): bool
    {
        if ($idProyecto <= 0 || $idPregunta <= 0 || $valor < 0 || $valor > 4) {
            return false;
        }

        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'resolution=merge-duplicates,return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/cadena_valor_respuesta',
            [
                'on_conflict' => 'id_proyecto,id_pregunta',
            ],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'id_pregunta' => $idPregunta,
                'valor' => $valor,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]
        );

        return $response['status'] < 400;
    }

    public static function upsertRespuestasBatch(SupabaseClient $supabase, int $idProyecto, array $answers): bool
    {
        if ($idProyecto <= 0 || empty($answers)) {
            return false;
        }

        $rows = [];
        foreach ($answers as $idPregunta => $valor) {
            $idPregunta = (int) $idPregunta;
            $valor = (int) $valor;
            if ($idPregunta <= 0 || $valor < 0 || $valor > 4) {
                return false;
            }
            $rows[] = [
                'id_proyecto' => $idProyecto,
                'id_pregunta' => $idPregunta,
                'valor' => $valor,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];
        }

        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'resolution=merge-duplicates,return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/cadena_valor_respuesta',
            [
                'on_conflict' => 'id_proyecto,id_pregunta',
            ],
            $headers,
            $rows
        );

        return $response['status'] < 400;
    }

    public static function upsertResultado(SupabaseClient $supabase, int $idProyecto, int $suma, float $potencial): bool
    {
        if ($idProyecto <= 0) {
            return false;
        }

        $headers = self::restHeaders($supabase);
        $headers['Prefer'] = 'resolution=merge-duplicates,return=representation';

        $response = $supabase->request(
            'POST',
            '/rest/v1/cadena_valor_resultado',
            [],
            $headers,
            [
                'id_proyecto' => $idProyecto,
                'suma' => $suma,
                'potencial' => $potencial,
            ]
        );

        return $response['status'] < 400;
    }

    public static function compute(array $preguntas, array $respuestas): array
    {
        $total = 0;
        $valid = 0;
        $count = 0;

        foreach ($preguntas as $p) {
            if (!is_array($p)) {
                continue;
            }
            $qid = (int) ($p['id_pregunta'] ?? 0);
            if ($qid <= 0) {
                continue;
            }
            $count += 1;
            if (!array_key_exists($qid, $respuestas)) {
                continue;
            }
            $v = (int) $respuestas[$qid];
            if ($v < 0 || $v > 4) {
                continue;
            }
            $valid += 1;
            $total += $v;
        }

        $missing = max(0, $count - $valid);
        $potencial = $missing > 0 ? null : (1 - ($total / 100));

        return [
            'sum' => $total,
            'valid' => $valid,
            'count' => $count,
            'missing' => $missing,
            'potential' => $potencial,
        ];
    }

    public static function defaultQuestions(): array
    {
        return [
            1 => 'La empresa tiene una política sistematizada de cero defectos en la producción de productos/servicios.',
            2 => 'La empresa emplea los medios productivos tecnológicamente más avanzados de su sector.',
            3 => 'La empresa dispone de un sistema de información y control de gestión eficiente y eficaz.',
            4 => 'Los medios técnicos y tecnológicos de la empresa están preparados para competir en un futuro a corto, medio y largo plazo.',
            5 => 'La empresa es un referente en su sector en I+D+i.',
            6 => 'La excelencia de los procedimientos de la empresa (ISO, etc.) son una principal fuente de ventaja competitiva.',
            7 => 'La empresa dispone de página web, y esta se emplea no sólo como escaparate virtual de productos/servicios, sino también para establecer relaciones con clientes y proveedores.',
            8 => 'Los productos/servicios que desarrolla nuestra empresa llevan incorporada una tecnología difícil de imitar.',
            9 => 'La empresa es referente en su sector en la optimización, en términos de coste, de su cadena de producción, siendo ésta una de sus principales ventajas competitivas.',
            10 => 'La informatización de la empresa es una fuente de ventaja competitiva clara respecto a sus competidores.',
            11 => 'Los canales de distribución de la empresa son una importante fuente de ventajas competitivas.',
            12 => 'Los productos/servicios de la empresa son altamente y diferencialmente valorados por el cliente respecto a nuestros competidores.',
            13 => 'La empresa dispone y ejecuta un sistemático plan de marketing y ventas.',
            14 => 'La empresa tiene optimizada su gestión financiera.',
            15 => 'La empresa busca continuamente mejorar la relación con sus clientes cortando los plazos de ejecución, personalizando la oferta o mejorando las condiciones de entrega, siempre partiendo de un plan previo.',
            16 => 'La empresa es referente en su sector en el lanzamiento de innovadores productos y servicios de éxito demostrado en el mercado.',
            17 => 'Los Recursos Humanos son especialmente responsables del éxito de la empresa, considerándolos incluso como el principal activo estratégico.',
            18 => 'Se tiene una plantilla altamente motivada, que conoce con claridad las metas, objetivos y estrategias de la organización.',
            19 => 'La empresa siempre trabaja conforme a una estrategia y objetivos claros.',
            20 => 'La gestión del circulante está optimizada.',
            21 => 'Se tiene definido claramente el posicionamiento estratégico de todos los productos de la empresa.',
            22 => 'Se dispone de una política de marca basada en la reputación que la empresa genera, en la gestión de relación con el cliente y en el posicionamiento estratégico previamente definido.',
            23 => 'La cartera de clientes de nuestra empresa está altamente fidelizada, ya que tenemos como principal propósito deleitarlos día a día.',
            24 => 'Nuestra política y equipo de ventas y marketing es una importante ventaja competitiva de nuestra empresa respecto al sector.',
            25 => 'El servicio al cliente que prestamos es una de nuestras principales ventajas competitivas respecto a nuestros competidores.',
        ];
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
