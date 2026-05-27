<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Informe PETI - Ruta Inteligente TI</title>
  <link href="/dist/output.css" rel="stylesheet" />
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; }
      main { padding: 0 !important; }
      section { break-inside: avoid; box-shadow: none !important; }
    }
  </style>
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-900">
<?php
  $proyecto = is_array($proyecto ?? null) ? $proyecto : [];
  $projectToken = (string) ($projectToken ?? '');
  $petiAnalysis = is_array($petiAnalysis ?? null) ? $petiAnalysis : [];
  $data = is_array($petiAnalysis['data'] ?? null) ? $petiAnalysis['data'] : [];
  $nombre = (string) ($proyecto['nombre'] ?? 'Proyecto PETI');
  $percent = (int) ($petiAnalysis['percent'] ?? 0);
  $status = is_array($petiAnalysis['status'] ?? null) ? $petiAnalysis['status'] : ['label' => 'Sin diagnostico'];
  $barColor = $percent >= 85 ? '#059669' : ($percent >= 60 ? '#65a30d' : ($percent >= 35 ? '#d97706' : '#dc2626'));
  $mision = trim((string) ($data['mision']['descripcion'] ?? ''));
  $vision = trim((string) ($data['vision']['descripcion'] ?? ''));
  $valores = is_array($data['valores'] ?? null) ? $data['valores'] : [];
  $objetivosEstrategicos = is_array($data['objetivos_estrategicos'] ?? null) ? $data['objetivos_estrategicos'] : [];
  $objetivosEspecificos = is_array($data['objetivos_especificos'] ?? null) ? $data['objetivos_especificos'] : [];
  $fodaItems = array_merge((array) ($data['foda_cadena'] ?? []), (array) ($data['foda_bcg'] ?? []));
  $cadena = is_array($data['cadena'] ?? null) ? $data['cadena'] : [];
  $bcg = is_array($data['bcg'] ?? null) ? $data['bcg'] : [];
  $bcgProductos = is_array($bcg['product_rows'] ?? null) ? $bcg['product_rows'] : [];
?>

<header class="no-print border-b border-neutral-200 bg-white">
  <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-6 py-4">
    <div>
      <div class="text-sm font-semibold text-neutral-500">Informe listo para PDF</div>
      <h1 class="text-xl font-semibold tracking-tight">Informe PETI</h1>
    </div>
    <div class="flex flex-wrap gap-2">
      <button type="button" onclick="window.print()" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
        Imprimir / Guardar PDF
      </button>
      <a href="detalle-proyecto.php?t=<?php echo urlencode($projectToken); ?>" class="rounded-xl border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-800 hover:bg-neutral-50">
        Volver al proyecto
      </a>
    </div>
  </div>
</header>

<main class="mx-auto max-w-4xl space-y-6 px-6 py-8">
  <section class="rounded-2xl border border-neutral-200 bg-white p-8 shadow-sm">
    <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
      <div>
        <div class="text-sm font-semibold text-neutral-500" style="text-transform: uppercase; letter-spacing: .04em;">Examen Practica Unidad II - PETI</div>
        <h2 class="mt-3 text-3xl font-semibold tracking-tight"><?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="mt-2 text-sm text-neutral-600">Fecha de generacion: <?php echo htmlspecialchars((string) ($petiAnalysis['generated_at'] ?? date('d/m/Y H:i')), ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
      <div class="w-56 rounded-2xl border border-neutral-200 bg-neutral-50 p-5">
        <div class="text-sm font-semibold text-neutral-600">Madurez PETI</div>
        <div class="mt-2 text-3xl font-semibold"><?php echo $percent; ?>%</div>
        <div class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold text-white" style="background-color: <?php echo htmlspecialchars($barColor, ENT_QUOTES, 'UTF-8'); ?>;">
          <?php echo htmlspecialchars((string) ($status['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>
    </div>
    <div class="mt-6 h-2 overflow-hidden rounded-full bg-neutral-100">
      <div class="h-full rounded-full" style="width: <?php echo max(0, min(100, $percent)); ?>%; background-color: <?php echo htmlspecialchars($barColor, ENT_QUOTES, 'UTF-8'); ?>;"></div>
    </div>
  </section>

  <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Resumen ejecutivo</h3>
    <div class="mt-4 text-sm leading-relaxed text-neutral-700" style="white-space: pre-line;"><?php echo htmlspecialchars((string) ($petiAnalysis['summary'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
  </section>

  <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Mision, vision y valores</h3>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
        <div class="text-sm font-semibold">Mision</div>
        <p class="mt-3 text-sm leading-relaxed text-neutral-700"><?php echo $mision !== '' ? nl2br(htmlspecialchars($mision, ENT_QUOTES, 'UTF-8')) : 'Pendiente de registrar.'; ?></p>
      </div>
      <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
        <div class="text-sm font-semibold">Vision</div>
        <p class="mt-3 text-sm leading-relaxed text-neutral-700"><?php echo $vision !== '' ? nl2br(htmlspecialchars($vision, ENT_QUOTES, 'UTF-8')) : 'Pendiente de registrar.'; ?></p>
      </div>
    </div>
    <div class="mt-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
      <div class="text-sm font-semibold">Valores</div>
      <?php if (empty($valores)) : ?>
        <p class="mt-3 text-sm text-neutral-600">Pendiente de registrar.</p>
      <?php else : ?>
        <div class="mt-3 flex flex-wrap gap-2">
          <?php foreach ($valores as $valor) : ?>
            <span class="rounded-full border border-neutral-200 bg-white px-3 py-1 text-sm text-neutral-700"><?php echo htmlspecialchars((string) ($valor['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Objetivos estrategicos y especificos</h3>
    <?php if (empty($objetivosEstrategicos)) : ?>
      <p class="mt-3 text-sm text-neutral-600">Pendiente de registrar objetivos estrategicos.</p>
    <?php else : ?>
      <div class="mt-4 overflow-x-auto rounded-xl border border-neutral-200">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-neutral-50 text-xs font-semibold text-neutral-600">
            <tr>
              <th class="w-14 px-4 py-3">#</th>
              <th class="px-4 py-3">Objetivo estrategico</th>
              <th class="px-4 py-3">Objetivos especificos</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200">
            <?php foreach ($objetivosEstrategicos as $index => $objetivo) : ?>
              <?php
                $idObjetivo = (int) ($objetivo['id_objetivo_est'] ?? 0);
                $children = array_values(array_filter($objetivosEspecificos, fn ($item) => (int) ($item['id_objetivo_est'] ?? 0) === $idObjetivo));
              ?>
              <tr>
                <td class="px-4 py-3 text-neutral-500"><?php echo (int) $index + 1; ?></td>
                <td class="px-4 py-3 align-top text-neutral-800"><?php echo htmlspecialchars((string) ($objetivo['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="px-4 py-3 align-top text-neutral-700">
                  <?php if (empty($children)) : ?>
                    <span class="text-neutral-500">Sin objetivos especificos registrados.</span>
                  <?php else : ?>
                    <div class="space-y-1">
                      <?php foreach ($children as $child) : ?>
                        <div>- <?php echo htmlspecialchars((string) ($child['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Resultado de cadena de valor</h3>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
        <div class="text-sm font-semibold">Puntaje obtenido</div>
        <div class="mt-2 text-2xl font-semibold"><?php echo (int) ($cadena['sum'] ?? 0); ?></div>
      </div>
      <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
        <div class="text-sm font-semibold">Respuestas</div>
        <div class="mt-2 text-2xl font-semibold"><?php echo (int) ($cadena['valid'] ?? 0); ?>/<?php echo (int) ($cadena['count'] ?? 0); ?></div>
      </div>
      <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
        <div class="text-sm font-semibold">Potencial</div>
        <?php $potential = $cadena['potential'] ?? null; ?>
        <div class="mt-2 text-2xl font-semibold"><?php echo is_numeric($potential) ? number_format(((float) $potential) * 100, 2) . '%' : 'Pendiente'; ?></div>
      </div>
    </div>
    <?php if ((int) ($cadena['missing'] ?? 0) > 0) : ?>
      <p class="mt-4 text-sm text-neutral-600">Faltan <?php echo (int) ($cadena['missing'] ?? 0); ?> pregunta(s) por responder para completar el diagnostico.</p>
    <?php endif; ?>
  </section>

  <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Matriz BCG con clasificacion por producto</h3>
    <?php if (empty($bcgProductos)) : ?>
      <p class="mt-3 text-sm text-neutral-600">Pendiente de registrar productos BCG.</p>
    <?php else : ?>
      <div class="mt-4 overflow-x-auto rounded-xl border border-neutral-200">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-neutral-50 text-xs font-semibold text-neutral-600">
            <tr>
              <th class="px-4 py-3">Producto</th>
              <th class="px-4 py-3">Ventas</th>
              <th class="px-4 py-3">% ventas</th>
              <th class="px-4 py-3">TCM</th>
              <th class="px-4 py-3">PRM</th>
              <th class="px-4 py-3">Clasificacion</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200">
            <?php foreach ($bcgProductos as $producto) : ?>
              <tr>
                <td class="px-4 py-3 text-neutral-800"><?php echo htmlspecialchars((string) ($producto['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="px-4 py-3 text-neutral-700"><?php echo is_numeric($producto['ventas_empresa'] ?? null) ? number_format((float) $producto['ventas_empresa'], 2) : '-'; ?></td>
                <td class="px-4 py-3 text-neutral-700"><?php echo is_numeric($producto['porcentaje_ventas'] ?? null) ? number_format((float) $producto['porcentaje_ventas'], 2) . '%' : '-'; ?></td>
                <td class="px-4 py-3 text-neutral-700"><?php echo is_numeric($producto['tcm'] ?? null) ? number_format((float) $producto['tcm'], 2) : '-'; ?></td>
                <td class="px-4 py-3 text-neutral-700"><?php echo is_numeric($producto['prm'] ?? null) ? number_format((float) $producto['prm'], 2) : '-'; ?></td>
                <td class="px-4 py-3 font-semibold text-neutral-800"><?php echo htmlspecialchars((string) (($producto['clasificacion'] ?? '') ?: 'Sin calcular'), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Checklist de avance</h3>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
      <?php foreach ((array) ($petiAnalysis['checks'] ?? []) as $check) : ?>
        <?php $done = !empty($check['complete']); ?>
        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
          <div class="flex items-start gap-3">
            <div class="mt-0.5 h-4 w-4 shrink-0 rounded-full" style="background-color: <?php echo $done ? '#059669' : '#d97706'; ?>;"></div>
            <div>
              <div class="text-sm font-semibold"><?php echo htmlspecialchars((string) ($check['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="mt-1 text-xs text-neutral-600"><?php echo htmlspecialchars((string) ($check['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="mt-4 text-sm text-neutral-600">FODA registrado: <?php echo count($fodaItems); ?> elemento(s) desde cadena de valor y BCG.</p>
  </section>
</main>
</body>
</html>
