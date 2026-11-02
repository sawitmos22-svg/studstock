<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Bangkok');

class SupabaseSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $statement = db()->prepare('SELECT data FROM app_sessions WHERE id = ? AND last_activity >= ?');
        $statement->execute([$id, time() - (int) ini_get('session.gc_maxlifetime')]);
        $data = $statement->fetchColumn();
        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $statement = db()->prepare(
            'INSERT INTO app_sessions (id, data, last_activity) VALUES (?, ?, ?)
             ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, last_activity = EXCLUDED.last_activity'
        );
        return $statement->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $statement = db()->prepare('DELETE FROM app_sessions WHERE id = ?');
        return $statement->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = db()->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
        $statement->execute([time() - $max_lifetime]);
        return $statement->rowCount();
    }
}

if (getenv('POSTGRES_URL_NON_POOLING') || getenv('POSTGRES_URL')) {
    session_set_save_handler(new SupabaseSessionHandler(), true);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_start();

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

    $databaseUrl = getenv('POSTGRES_URL_NON_POOLING') ?: getenv('POSTGRES_URL');
    if ($databaseUrl) {
        $parts = parse_url($databaseUrl);
        if (!$parts || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('รูปแบบ POSTGRES_URL ไม่ถูกต้อง');
        }

        parse_str($parts['query'] ?? '', $query);
        $host = $parts['host'];
        $port = (int) ($parts['port'] ?? 5432);
        $database = rawurldecode(ltrim($parts['path'], '/'));
        $username = rawurldecode($parts['user'] ?? '');
        $password = rawurldecode($parts['pass'] ?? '');
        $sslMode = preg_replace('/[^a-z]/i', '', $query['sslmode'] ?? 'require') ?: 'require';
        $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslMode}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Supabase transaction poolers do not support named server-side prepared statements.
            PDO::ATTR_EMULATE_PREPARES => true,
        ];

        $pdo = new PDO($dsn, $username, $password, $options);
        $pdo->exec("SET TIME ZONE 'Asia/Bangkok'");
        return $pdo;
    }

    // Local MAMP development may still use the original MySQL database.
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
    foreach ($passwordsToTry as $password) {
        foreach ($databasesToTry as $database) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . $database . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, $password, $options);
                return $pdo;
            } catch (PDOException $exception) {
                $lastException = $exception;
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