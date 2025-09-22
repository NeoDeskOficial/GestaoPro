<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Login - Gestão Pro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/login.css" rel="stylesheet">
</head>
<body>

<div class="login-bg">
  <div class="d-flex align-items-center justify-content-center vh-100">
    <div class="card shadow-lg p-4 rounded-4 login-card">
      <div class="text-center mb-3">
        <img src="/assets/img/logo.png" alt="Logo" class="img-fluid" style="max-height: 80px;">
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="/login">
        <div class="mb-3">
          <label class="form-label">Usuário</label>
          <input type="text" name="login" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Senha</label>
          <input type="password" name="senha" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
      </form>

      <div class="text-center mt-3">
        <small class="text-muted">&copy; <?= date('Y') ?> Gestão Pro - Todos os direitos reservados</small>
      </div>
    </div>
  </div>
</div>

</body>
</html>
