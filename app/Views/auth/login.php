<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Ruta Inteligente TI - Acceso</title>
    <link href="dist/output.css" rel="stylesheet" />
  </head>
  <body class="min-h-screen bg-brand-900 text-neutral-900">
    <main class="min-h-screen grid grid-cols-1 md:grid-cols-2">
      <section class="relative bg-black h-44 md:h-auto overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-brand-600/35 via-black to-black"></div>
        <div class="absolute inset-0 bg-[linear-gradient(135deg,_rgba(6,182,212,0.18),_rgba(37,99,235,0.08),_rgba(0,0,0,0))]"></div>
        <div class="relative h-full w-full p-8 text-white hidden md:flex items-end">
          <div>
            <p class="text-sm text-white/70">Ruta Inteligente TI</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight">Planifica. Ejecuta. Mide.</h2>
            <p class="mt-2 max-w-sm text-sm text-white/70">
              Accede al panel para gestionar proyectos y ver un resumen del avance.
            </p>
          </div>
        </div>
      </section>

      <section class="bg-white flex items-center justify-center px-6 py-10">
        <div class="w-full max-w-md">
          <div class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-brand-900">Ruta Inteligente TI</h1>
            <p class="mt-1 text-sm text-neutral-600">Accede con tu cuenta.</p>
          </div>

          <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
            <div class="mt-6">
              <?php if (!empty($error)) : ?>
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                  <?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
              <?php endif; ?>

              <form class="space-y-4" action="login.php" method="post">
                <div>
                  <label for="login-email" class="block text-sm font-medium text-neutral-800">Correo</label>
                  <input
                    id="login-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    class="mt-1 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none ring-0 transition focus:border-brand-700 focus:ring-2 focus:ring-brand-600/15"
                    placeholder="tu@correo.com"
                  />
                </div>

                <div>
                  <label for="login-password" class="block text-sm font-medium text-neutral-800">Contraseña</label>
                  <input
                    id="login-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="mt-1 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-brand-700 focus:ring-2 focus:ring-brand-600/15"
                    placeholder="••••••••"
                  />
                </div>

                <button
                  type="submit"
                  class="inline-flex w-full items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-600/25"
                >
                  Entrar
                </button>
              </form>
            </div>
          </div>

          <p class="mt-6 text-xs text-neutral-500">
            Al continuar, aceptas nuestras políticas de privacidad y condiciones de uso.
          </p>
        </div>
      </section>
    </main>

  </body>
</html>
