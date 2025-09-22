<?php
/**
 * Arquivo de configuração da aplicação.
 * Dica HostGator:
 * - DB_HOST: normalmente 'localhost'
 * - DB_NAME: cpaneluser_nomeDoBanco
 * - DB_USER: cpaneluser_usuario
 * - DB_PASS: senha do usuário MySQL criado no cPanel
 */

define('APP_NAME', 'Gestão Pro ERP+CRM');
date_default_timezone_set('America/Sao_Paulo');

// === MySQL ===
define('DB_HOST', 'localhost');         // Em HostGator, normalmente é 'localhost'
define('DB_NAME', 'neodes04_GestaoPro');     // Ex.: cpuser_erpcrm
define('DB_USER', 'neodes04_AdmGestaoPro');     // Ex.: cpuser_dbuser
define('DB_PASS', 'nd@SuperAdmGestaoPro');   // Defina no cPanel
define('DB_CHARSET', 'utf8mb4');

// === Modo debug (exibir mensagens de erro detalhadas em DEV) ===
define('APP_DEBUG', true);
