<?php

require __DIR__ . '/../app/Core/Session.php';
require __DIR__ . '/../app/Services/SupabaseClient.php';
require __DIR__ . '/../app/Models/Persona.php';
require __DIR__ . '/../app/Controllers/AuthController.php';
require __DIR__ . '/../app/Controllers/ConfiguracionController.php';

$controller = new ConfiguracionController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update_name') {
        $controller->updateNombre();
    }
    if ($action === 'update_password') {
        $controller->updatePassword();
    }
    header('Location: login.php');
    exit;
}

$controller->show();
