<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/php/core/Database.php';
require_once __DIR__ . '/php/controllers/AuthController.php';

use Controllers\AuthController;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'];

// Normaliza: remove barra final (menos a raiz) e trata /index.php como /
if ($uri !== '/' ) {
  $uri = rtrim($uri, '/');
}
if ($uri === '/index.php') {
  $uri = '/';
}

$auth = new AuthController();

if ($uri === '/login' && $method === 'GET') {
  $auth->showLogin();
} elseif ($uri === '/login' && $method === 'POST') {
  $auth->login();
} elseif ($uri === '/logout') {
  $auth->logout();
} elseif ($uri === '/' || $uri === '/dashboard') {
  if (empty($_SESSION['user'])) {
    header('Location: /login');
    exit;
  }
  $user = $_SESSION['user'];
  ?>
  <!DOCTYPE html>
  <html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <title>Dashboard - Gestão Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-5">
      <h1 class="mb-4">Bem-vindo(a), <?= htmlspecialchars($user['nome'] ?? $user['login']) ?>!</h1>
      <p>Este é o painel inicial do sistema ERP+CRM.</p>
      <a href="/logout" class="btn btn-danger">Sair</a>
    </div>
  </body>
  </html>
  <?php
} else {
  http_response_code(404);
  echo "Página não encontrada";
}
