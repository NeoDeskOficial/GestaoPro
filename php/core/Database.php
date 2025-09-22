<?php
declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            // Carrega a config (ajuste o caminho relativo conforme onde você incluir este arquivo)
            require_once __DIR__ . '/../../includes/config/app.php';

            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    // Em produção, troque por log e mensagem genérica
                    die('Falha na conexão com o banco: ' . $e->getMessage());
                }
                die('Falha na conexão com o banco.');
            }
        }

        return self::$pdo;
    }
}
