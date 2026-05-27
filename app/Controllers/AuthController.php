<?php

final class AuthController
{
    public function showLogin(): void
    {
        Session::start();
        $error = Session::getFlash('error');
        require dirname(__DIR__) . '/Views/auth/login.php';
    }

    public function login(): void
    {
        Session::start();

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            Session::flash('error', 'Correo o contraseña inválidos.');
            $this->redirect('/login.php');
        }

        try {
            $supabase = new SupabaseClient();
        } catch (Throwable $e) {
            Session::flash('error', 'No se pudo inicializar Supabase. Revisa el archivo .env.');
            $this->redirect('/login.php');
        }

        $result = $supabase->signInWithPassword($email, $password);
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            $this->redirect('/login.php');
        }

        $data = $result['data'];
        $user = is_array($data) ? ($data['user'] ?? null) : null;
        $userEmail = is_array($user) ? (($user['email'] ?? null) ?: $email) : $email;

        $persona = Persona::findByEmail($supabase, (string) $userEmail);
        if ($persona === null || empty($persona['id_persona'])) {
            Session::flash('error', 'No existe una persona asociada a este correo en la tabla persona.');
            $this->redirect('/login.php');
        }

        Session::regenerateId();
        Session::set('auth', [
            'persona' => $persona,
            'supabase' => [
                'access_token' => $data['access_token'] ?? null,
                'refresh_token' => $data['refresh_token'] ?? null,
                'user_id' => is_array($user) ? ($user['id'] ?? null) : null,
                'email' => $userEmail,
            ],
        ]);

        $this->redirect('/dashboard.php');
    }

    public function requireAuth(): array
    {
        Session::start();
        $auth = Session::get('auth');
        $persona = is_array($auth) ? ($auth['persona'] ?? null) : null;

        if (!is_array($persona) || empty($persona['id_persona'])) {
            Session::flash('error', 'Debes iniciar sesión para acceder al dashboard.');
            $this->redirect('/login.php');
        }

        return $persona;
    }

    private function redirect(string $path): void
    {
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '\\/');
        $location = $basePath === '' ? $path : ($basePath . $path);
        header('Location: ' . $location);
        exit;
    }
}

