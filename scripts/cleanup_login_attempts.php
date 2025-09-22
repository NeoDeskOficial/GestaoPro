<?php
declare(strict_types=1);

/**
 * cleanup_login_attempts.php — PRODUÇÃO
 * - Limpa registros antigos de login_attempts
 * - Log discreto em scripts/cleanup.log
 * - Rotação simples do log quando passa de 1 MB
 */

date_default_timezone_set('America/Sao_Paulo');

$logFile     = __DIR__ . '/cleanup.log';
$daysToKeep  = 2;                 // retenção (dias)
$maxLogBytes = 1024 * 1024;       // 1 MB
$projectRoot = dirname(__DIR__);  // /home/neodes04/gestaopro.neodeskinformatica.com.br
$databasePhp = $projectRoot . '/php/core/Database.php';

// Rotação simples do log
if (file_exists($logFile) && filesize($logFile) > $maxLogBytes) {
    file_put_contents($logFile, '', LOCK_EX); // zera o arquivo
}

// Função de log (discreto, só arquivo)
$log = function (string $msg) use ($logFile): void {
    $line = date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
};

try {
    if (!file_exists($databasePhp)) {
        $log("ERRO: Database.php não encontrado: {$databasePhp}");
        exit(1);
    }

    require_once $databasePhp;

    $pdo = \Core\Database::conn();

    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL ? DAY)");
    $stmt->execute([$daysToKeep]);

    $log("OK: removidas {$stmt->rowCount()} linhas (retenção: {$daysToKeep} dias).");
    exit(0);

} catch (\Throwable $e) {
    $log("ERRO (exception): " . $e->getMessage());
    exit(1);
}
