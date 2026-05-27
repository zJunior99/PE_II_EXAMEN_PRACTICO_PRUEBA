<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Proyectos - Ruta Inteligente TI</title>
    <link href="dist/output.css" rel="stylesheet" />
</head>

<body class="min-h-screen bg-neutral-50 text-neutral-900">
<?php
  $proyectos = is_array($proyectos ?? null) ? $proyectos : [];
?>
<div class="min-h-screen grid grid-cols-1 md:grid-cols-[16rem_1fr]">
    <?php
      $sidebarActive = 'proyectos';
      $sidebarSeedProjects = $proyectos;
      include __DIR__ . '/../layouts/sidebar.php';
    ?>

    <div class="min-h-screen flex flex-col">

        <!-- HEADER -->
        <header class="bg-white border-b border-neutral-200">
            <div class="px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Proyectos</h1>

                <a href="nuevo-proyecto.php"
                   class="bg-brand-600 text-white px-4 py-2 rounded-xl text-sm hover:bg-brand-700">
                    + Nuevo proyecto
                </a>
            </div>
        </header>

        <main class="flex-1 px-6 py-8">
            <?php if (!empty($error)) : ?>
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-800">
                    <?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)) : ?>
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-4 text-sm text-emerald-900">
                    <?php echo htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- BUSCADOR Y FILTROS -->
            <div class="mb-8 flex flex-col md:flex-row gap-3 md:items-center md:justify-between">

                <div class="w-full md:max-w-md">
                    <input
                        type="text"
                        placeholder="Buscar proyectos..."
                        class="w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-brand-700 focus:ring-2 focus:ring-brand-600/15 outline-none"
                    />
                </div>

                <div class="flex gap-2">
                    <select class="rounded-xl border border-neutral-300 px-3 py-2 text-sm">
                        <option>Todos</option>
                        <option>Activo</option>
                        <option>Borrador</option>
                        <option>Compartido</option>
                    </select>

                    <select class="rounded-xl border border-neutral-300 px-3 py-2 text-sm">
                        <option>Ordenar por fecha</option>
                        <option>Ordenar por nombre</option>
                    </select>
                </div>
            </div>

            <!-- LISTA -->
            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

                <div class="divide-y divide-neutral-200">
                    <?php if (empty($proyectos)) : ?>
                        <div class="px-6 py-8 text-sm text-neutral-600">
                            Aún no tienes proyectos registrados. Crea uno con “+ Nuevo proyecto”.
                        </div>
                    <?php else : ?>
                        <?php foreach ($proyectos as $proyecto) : ?>
                            <div
                                class="px-6 py-4 flex justify-between items-center hover:bg-neutral-50 transition"
                                data-project-row="1"
                                data-project-name="<?php echo htmlspecialchars((string) ($proyecto['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-project-id="<?php echo (int) ($proyecto['id_proyecto'] ?? 0); ?>"
                            >
                                <div>
                                    <p class="font-medium">
                                        <?php echo htmlspecialchars((string) ($proyecto['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                    <p class="text-xs text-neutral-500">Proyecto estratégico</p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <a
                                        href="detalle-proyecto.php?t=<?php echo urlencode((string) ($proyecto['token'] ?? '')); ?>"
                                        class="text-sm text-brand-700 font-medium hover:underline"
                                    >
                                        Ver detalle
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <?php
              $page = max(1, (int) ($page ?? 1));
              $totalPages = max(1, (int) ($totalPages ?? 1));
              $totalProyectos = max(0, (int) ($totalProyectos ?? 0));
              $baseParams = $_GET ?? [];
              unset($baseParams['page']);
              $buildUrl = function (int $p) use ($baseParams): string {
                $params = $baseParams;
                $params['page'] = $p;
                $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
                return 'proyectos.php' . ($qs ? ('?' . $qs) : '');
              };
              $start = max(1, $page - 2);
              $end = min($totalPages, $page + 2);
            ?>
            <?php if ($totalPages > 1) : ?>
              <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-neutral-600">
                  Página <?php echo (int) $page; ?> de <?php echo (int) $totalPages; ?> · <?php echo (int) $totalProyectos; ?> proyectos
                </div>

                <nav class="inline-flex items-center gap-1" aria-label="Paginación">
                  <a
                    href="<?php echo htmlspecialchars($buildUrl(max(1, $page - 1)), ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo $page <= 1 ? 'pointer-events-none opacity-50' : ''; ?> inline-flex h-10 items-center justify-center rounded-xl border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-800 hover:bg-neutral-50"
                  >
                    Anterior
                  </a>

                  <?php for ($p = $start; $p <= $end; $p++) : ?>
                    <a
                      href="<?php echo htmlspecialchars($buildUrl($p), ENT_QUOTES, 'UTF-8'); ?>"
                      class="<?php echo $p === $page ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-neutral-800 border-neutral-200 hover:bg-neutral-50'; ?> inline-flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-semibold"
                      aria-current="<?php echo $p === $page ? 'page' : 'false'; ?>"
                    >
                      <?php echo (int) $p; ?>
                    </a>
                  <?php endfor; ?>

                  <a
                    href="<?php echo htmlspecialchars($buildUrl(min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo $page >= $totalPages ? 'pointer-events-none opacity-50' : ''; ?> inline-flex h-10 items-center justify-center rounded-xl border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-800 hover:bg-neutral-50"
                  >
                    Siguiente
                  </a>
                </nav>
              </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
    document.querySelectorAll('a[href^="detalle-proyecto.php?t="]').forEach((a) => {
        a.addEventListener("click", () => {
            const row = a.closest("[data-project-row]");
            const id = row ? Number(row.getAttribute("data-project-id") || 0) : 0;
            const name = row ? (row.getAttribute("data-project-name") || "") : "";
            if (id && name && window.RISidebar && typeof window.RISidebar.pushRecentProject === "function") {
                window.RISidebar.pushRecentProject(id, name);
            }
        });
    });
</script>
</body>
</html>
