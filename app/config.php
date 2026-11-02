<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Bangkok');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'studbolt_stocks');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $passwordsToTry = [DB_PASS];
    if (DB_PASS === 'root') $passwordsToTry[] = '';
    elseif (DB_PASS === '') $passwordsToTry[] = 'root';

    $databasesToTry = [DB_NAME];
    if (DB_NAME === 'studbolt_stock') $databasesToTry[] = 'studbolt_stocks';
    elseif (DB_NAME === 'studbolt_stocks') $databasesToTry[] = 'studbolt_stock';

    $lastException = null;
    foreach ($passwordsToTry as $p) {
        foreach ($databasesToTry as $db) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . $db . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, $p, $options);
                return $pdo;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }
    }

    throw $lastException ?: new PDOException('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }

    $stmt = db()->prepare('SELECT active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user']['id']]);
    if ((int) $stmt->fetchColumn() !== 1) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

function require_manager(): void
{
    require_login();
    if ($_SESSION['user']['role'] !== 'manager') {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เข้าถึง');
    }
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}