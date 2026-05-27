<?php

final class RegisterController
{
    public function register(): void
    {
        Session::start();
        Session::flash('error', 'El registro de usuarios está deshabilitado.');
        $this->redirect('/login.php');
    }

    private function redirect(string $path): void
    {
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '\\/');
        $location = $basePath === '' ? $path : ($basePath . $path);
        header('Location: ' . $location);
        exit;
    }
}

