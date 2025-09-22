<?php
declare(strict_types=1);

namespace Controllers;

use PDO;
use Core\Database;

class AuthController
{
    /* =========================================================
     * Helpers de Rate Limit (tentativas de login)
     * ========================================================= */

    private function clientIp(): string
    {
        // Se um dia usar proxy confiável/Cloudflare, trate cabeçalhos aqui.
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function ipToBinary(string $ip): string
    {
        $bin = @inet_pton($ip);
        // inet_pton retorna string binária; fallback para 0.0.0.0
        return $bin !== false ? $bin : (string) inet_pton('0.0.0.0');
    }

    private function tooManyAttempts(PDO $pdo, string $login, string $ipBin, int $limit = 5, int $windowMinutes = 15): bool
    {
        $sql = "SELECT
                    SUM(CASE WHEN login = ? THEN 1 ELSE 0 END) AS by_login,
                    SUM(CASE WHEN ip = ? THEN 1 ELSE 0 END)     AS by_ip
                FROM login_attempts
                WHERE created_at >= (NOW() - INTERVAL ? MINUTE)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$login, $ipBin, $windowMinutes]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['by_login' => 0, 'by_ip' => 0];

        return ((int)$row['by_login'] >= $limit) || ((int)$row['by_ip'] >= $limit);
    }

    private function remainingTime(PDO $pdo, string $login, string $ipBin, int $windowMinutes = 15): int
    {
        $sql = "SELECT
                    TIMESTAMPDIFF(SECOND, NOW(), MAX(created_at) + INTERVAL ? MINUTE) AS remain
                FROM login_attempts
                WHERE login = ? OR ip = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$windowMinutes, $login, $ipBin]);
        $remain = (int)($stmt->fetchColumn() ?: 0);
        return max(0, $remain);
    }

    private function recordAttempt(PDO $pdo, string $login, string $ipBin): void
    {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (login, ip) VALUES (?, ?)");
        $stmt->bindParam(1, $login, PDO::PARAM_STR);
        $stmt->bindParam(2, $ipBin, PDO::PARAM_LOB); // ip armazenado como VARBINARY(16)
        $stmt->execute();
    }

    private function clearAttempts(PDO $pdo, string $login, string $ipBin): void
    {
        // Limpa tentativas após sucesso para este login/IP
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE login = ? OR ip = ?");
        $stmt->bindParam(1, $login, PDO::PARAM_STR);
        $stmt->bindParam(2, $ipBin, PDO::PARAM_LOB);
        $stmt->execute();
    }

    /* =========================================================
     * Views
     * ========================================================= */

    private function viewLogin(array $data = []): void
    {
        // Torna $error etc. visível na view
        extract($data);
        // Ajuste o caminho se sua estrutura for diferente
        require __DIR__ . '/../views/auth/login.php';
    }

    /* =========================================================
     * Ações
     * ========================================================= */

    public function showLogin(): void
    {
        if (!empty($_SESSION['user'])) {
            header('Location: /');
            exit;
        }
        $this->viewLogin();
    }

    public function login(): void
    {
        // 1) Campos do formulário
        $login = trim($_POST['login'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($login === '' || $senha === '') {
            $this->viewLogin(['error' => 'Informe login e senha.']);
            return;
        }

        // 2) DB + Rate limit (5 tentativas / 15 min por login e por IP)
        $pdo   = Database::conn();
        $ip    = $this->clientIp();
        $ipBin = $this->ipToBinary($ip);

        if ($this->tooManyAttempts($pdo, $login, $ipBin, 5, 15)) {
            $seconds = $this->remainingTime($pdo, $login, $ipBin, 15);
            $min = max(1, (int)ceil($seconds / 60));
            $this->viewLogin(['error' => "Muitas tentativas. Tente novamente em ~{$min} min."]);
            return;
        }

        // 3) Busca usuário + dados essenciais do funcionário (se vinculado)
        $sql = "SELECT
                    u.id, u.login, u.senha, u.funcionario_id, u.status AS user_status,
                    f.nome, f.sobrenome, f.email, f.acesso_sistema, f.status AS func_status
                FROM users u
                LEFT JOIN funcionarios f ON f.id = u.funcionario_id
                WHERE u.login = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Mensagem genérica p/ não vazar info
        $invalid = 'Usuário ou senha inválidos.';

        if (!$user) {
            // registra tentativa falha
            $this->recordAttempt($pdo, $login, $ipBin);
            $this->viewLogin(['error' => $invalid]);
            return;
        }

        // 4) Status de user e funcionário
        if ((int)$user['user_status'] !== 1) {
            $this->recordAttempt($pdo, $login, $ipBin);
            $this->viewLogin(['error' => 'Usuário inativo.']);
            return;
        }

        if ($user['funcionario_id']) {
            if ((int)$user['func_status'] !== 1) {
                $this->recordAttempt($pdo, $login, $ipBin);
                $this->viewLogin(['error' => 'Funcionário inativo.']);
                return;
            }
            if ((int)$user['acesso_sistema'] !== 1) {
                $this->recordAttempt($pdo, $login, $ipBin);
                $this->viewLogin(['error' => 'Acesso ao sistema não autorizado.']);
                return;
            }
        }

        // 5) Verifica senha (hash em users.senha)
        if (!password_verify($senha, $user['senha'])) {
            // registra tentativa falha
            $this->recordAttempt($pdo, $login, $ipBin);
            $this->viewLogin(['error' => $invalid]);
            return;
        }

        // 6) Sucesso → limpa tentativas e abre sessão
        $this->clearAttempts($pdo, $login, $ipBin);

        $_SESSION['user'] = [
            'id'             => (int)$user['id'],
            'login'          => $user['login'],
            'funcionario_id' => $user['funcionario_id'] ? (int)$user['funcionario_id'] : null,
            'nome'           => $user['nome'] ?? null,
            'sobrenome'      => $user['sobrenome'] ?? null,
            'email'          => $user['email'] ?? null,
        ];

        // Opcional: log de acesso
        // error_log('LOGIN OK: '.$_SESSION['user']['login'].' IP: '.$this->clientIp());

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        // Opcional: log de saída
        // error_log('LOGOUT: '.($_SESSION['user']['login'] ?? 'desconhecido'));

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: /login');
        exit;
    }
}
