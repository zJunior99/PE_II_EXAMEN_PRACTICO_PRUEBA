<?php

final class ConfiguracionController
{
    public function show(): void
    {
        $authController = new AuthController();
        $authUser = $authController->requireAuth();

        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        require dirname(__DIR__) . '/Views/configuracion/index.php';
    }

    public function updateNombre(): void
    {
        $authController = new AuthController();
        $persona = $authController->requireAuth();

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect('/configuracion.php');
        }

        try {
            $supabase = new SupabaseClient();
        } catch (Throwable $e) {
            Session::flash('error', 'No se pudo inicializar Supabase. Revisa el archivo .env.');
            $this->redirect('/configuracion.php');
        }

        $idPersona = (int) ($persona['id_persona'] ?? 0);
        if ($idPersona <= 0) {
            Session::flash('error', 'Sesión inválida.');
            $this->redirect('/login.php');
        }

        $result = Persona::updateNombreById($supabase, $idPersona, $nombre);
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            $this->redirect('/configuracion.php');
        }

        $auth = Session::get('auth');
        if (is_array($auth) && isset($auth['persona']) && is_array($auth['persona'])) {
            $auth['persona']['nombre'] = $nombre;
            Session::set('auth', $auth);
        }

        Session::flash('success', 'Nombre actualizado correctamente.');
        $this->redirect('/configuracion.php');
    }

    public function updatePassword(): void
    {
        $authController = new AuthController();
        $authController->requireAuth();

        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($password === '' || strlen($password) < 6) {
            Session::flash('error', 'La contraseña debe tener al menos 6 caracteres.');
            $this->redirect('/configuracion.php');
        }

        if ($password !== $passwordConfirmation) {
            Session::flash('error', 'Las contraseñas no coinciden.');
            $this->redirect('/configuracion.php');
        }

        $auth = Session::get('auth');
        $accessToken = is_array($auth) && isset($auth['supabase']) && is_array($auth['supabase'])
            ? (string) ($auth['supabase']['access_token'] ?? '')
            : '';

        if ($accessToken === '') {
            Session::flash('error', 'No hay sesión válida para actualizar la contraseña.');
            $this->redirect('/login.php');
        }

        try {
            $supabase = new SupabaseClient();
        } catch (Throwable $e) {
            Session::flash('error', 'No se pudo inicializar Supabase. Revisa el archivo .env.');
            $this->redirect('/configuracion.php');
        }

        $result = $supabase->updatePassword($accessToken, $password);
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            $this->redirect('/configuracion.php');
        }

        Session::flash('success', 'Contraseña actualizada correctamente.');
        $this->redirect('/configuracion.php');
    }

    private function redirect(string $path): void
    {
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '\\/');
        $location = $basePath === '' ? $path : ($basePath . $path);
        header('Location: ' . $location);
        exit;
    }
}

