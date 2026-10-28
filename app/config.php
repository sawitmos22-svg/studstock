<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Bangkok');

const DB_HOST = 'localhost';
const DB_NAME = 'studbolt_stocks';
const DB_USER = 'root';
const DB_PASS = 'root';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
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
