<?php
declare(strict_types=1);

/**
 * StudBolt Stock Management System - Web Installer & Seed Setup
 * Sets up database schema, initial admin accounts, and imports all 151 items from Excel
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/app/awarded.php';

$step = $_GET['step'] ?? 'form';
$messages = [];
$errors = [];
$stats = [
    'products_inserted' => 0,
    'with_stock' => 0,
    'total_stock_units' => 0,
    'users_created' => 0,
];

// PHP Requirements Check
$phpVersionOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$extPdo = extension_loaded('pdo_mysql');
$extSimpleXml = extension_loaded('simplexml');
$extZip = extension_loaded('zip');
$allReqsOk = $phpVersionOk && $extPdo && $extSimpleXml && $extZip;

$defaultHost = 'localhost';
$defaultDbName = 'studbolt_stocks';
$defaultUser = 'root';
$defaultPass = 'root';
$defaultManagerPass = 'ChangeMe123!';
$defaultStaffPass = 'Staff123!';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    $dbHost = trim($_POST['db_host'] ?? $defaultHost);
    $dbName = trim($_POST['db_name'] ?? $defaultDbName);
    $dbUser = trim($_POST['db_user'] ?? $defaultUser);
    $dbPass = (string) ($_POST['db_pass'] ?? $defaultPass);
    $managerUser = trim($_POST['manager_username'] ?? 'manager');
    $managerPass = (string) ($_POST['manager_password'] ?? $defaultManagerPass);
    $staffUser = trim($_POST['staff_username'] ?? 'staff');
    $staffPass = (string) ($_POST['staff_password'] ?? $defaultStaffPass);
    $seedExcel = isset($_POST['seed_excel']);

    try {
        // 1. Connect to MySQL server (without selecting db first to ensure it can be created)
        $pdoServer = null;
        $passwordsToTry = [$dbPass];
        if ($dbPass === 'root') $passwordsToTry[] = '';
        elseif ($dbPass === '') $passwordsToTry[] = 'root';

        $serverConnected = false;
        $activePass = $dbPass;
        foreach ($passwordsToTry as $p) {
            try {
                $dsn = "mysql:host={$dbHost};charset=utf8mb4";
                $pdoServer = new PDO($dsn, $dbUser, $p, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $activePass = $p;
                $serverConnected = true;
                break;
            } catch (PDOException $e) {
                // try next
            }
        }

        if (!$serverConnected || !$pdoServer) {
            throw new RuntimeException("ไม่สามารถเชื่อมต่อ MySQL Server ที่ {$dbHost} (ตรวจสอบ User / Password)");
        }

        // 2. Create Database
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $messages[] = "สร้าง / ตรวจสอบฐานข้อมูล `{$dbName}` สำเร็จ";

        // 3. Connect to target database
        $dsnDb = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsnDb, $dbUser, $activePass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // 4. Create Tables
        $schemaSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  role ENUM('manager','stock_staff','employee') NOT NULL DEFAULT 'employee',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(80) UNIQUE NOT NULL,
  size_mm VARCHAR(30) NOT NULL,
  diameter_mm DECIMAL(8,2) NULL,
  length_mm DECIMAL(8,2) NOT NULL,
  grade VARCHAR(80) NOT NULL,
  standard_name VARCHAR(80) DEFAULT '',
  description VARCHAR(500) DEFAULT '',
  qty INT NOT NULL DEFAULT 0,
  min_qty INT NOT NULL DEFAULT 0,
  location_code VARCHAR(80) DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sku(sku),
  INDEX idx_size(size_mm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NULL,
  user_username VARCHAR(80) NOT NULL,
  user_full_name VARCHAR(160) NOT NULL,
  txn_type ENUM('IN','OUT','ADJUST') NOT NULL,
  qty INT NOT NULL,
  balance_after INT NOT NULL,
  job_ref VARCHAR(120) DEFAULT '',
  note VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transactions_product FOREIGN KEY(product_id) REFERENCES products(id),
  CONSTRAINT fk_transactions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_txn_date(created_at),
  INDEX idx_txn_product(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_requests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  requester_user_id INT NULL,
  requester_username VARCHAR(80) NOT NULL,
  requester_full_name VARCHAR(160) NOT NULL,
  request_type ENUM('IN','OUT','ADJUST') NOT NULL,
  qty INT NOT NULL,
  job_ref VARCHAR(120) NOT NULL DEFAULT '',
  note VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  reviewer_user_id INT NULL,
  reviewer_username VARCHAR(80) NULL,
  reviewer_full_name VARCHAR(160) NULL,
  review_note VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_stock_requests_product FOREIGN KEY(product_id) REFERENCES products(id),
  CONSTRAINT fk_stock_requests_requester FOREIGN KEY(requester_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_requests_reviewer FOREIGN KEY(reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_stock_requests_status_created(status, created_at),
  INDEX idx_stock_requests_product(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  username VARCHAR(80) NOT NULL,
  full_name VARCHAR(160) NULL,
  event_type VARCHAR(30) NOT NULL,
  details VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_activity_date(created_at),
  INDEX idx_activity_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        try {
            $pdo->exec($schemaSql);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1813') !== false || stripos($e->getMessage(), 'Tablespace') !== false) {
                // If tablespace error happened on studbolt_stock, automatically switch to studbolt_stocks
                if ($dbName === 'studbolt_stock') {
                    $dbName = 'studbolt_stocks';
                    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    $dsnDb = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsnDb, $dbUser, $activePass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                    $pdo->exec($schemaSql);
                    $messages[] = "สลับไปใช้ฐานข้อมูล `studbolt_stocks` อัตโนมัติ เพื่อเลี่ยง Tablespace ตกค้างใน `studbolt_stock`";
                } else {
                    throw new RuntimeException("พบ Tablespace ตกค้างในฐานข้อมูล `{$dbName}` แนะนำให้เปลี่ยนชื่อ Database Name เป็น `studbolt_stocks`");
                }
            } else {
                throw $e;
            }
        }
        $pdo->exec("ALTER TABLE products MODIFY description VARCHAR(500) DEFAULT ''");
        $messages[] = "สร้าง / ตรวจสอบตาราง 5 ตารางหลักเรียบร้อยแล้ว";

        // 5. Create / Update Default Admin & Staff Accounts
        $userStmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, role, active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name), role = VALUES(role), active = 1'
        );

        $managerHash = password_hash($managerPass, PASSWORD_DEFAULT);
        $userStmt->execute([$managerUser, $managerHash, 'ผู้จัดการคลังสินค้า', 'manager']);
        $stats['users_created']++;

        $staffHash = password_hash($staffPass, PASSWORD_DEFAULT);
        $userStmt->execute([$staffUser, $staffHash, 'พนักงานคลังสินค้า', 'stock_staff']);
        $stats['users_created']++;

        $messages[] = "สร้างบัญชีผู้ใช้เริ่มต้น: {$managerUser} (ผู้จัดการ) และ {$staffUser} (พนักงานคลัง)";

        // 6. Seed All Items from awarded_items.csv
        if ($seedExcel) {
            $csvFile = __DIR__ . '/app/data/awarded_items.csv';
            if (!file_exists($csvFile)) {
                throw new RuntimeException("ไม่พบไฟล์ข้อมูล {$csvFile}");
            }

            $fileHandle = fopen($csvFile, 'rb');
            $header = fgetcsv($fileHandle, null, ',', '"', '');
            $rawRows = [];
            $line = 0;
            while (($cells = fgetcsv($fileHandle, null, ',', '"', '')) !== false) {
                $line++;
                $rawRows[] = ['line' => $line, 'cells' => $cells];
            }
            fclose($fileHandle);

            $indexes = awardedHeaderIndexes($header);

            $insertProduct = $pdo->prepare(
                'INSERT INTO products (sku, size_mm, diameter_mm, length_mm, grade, standard_name, description, qty, min_qty, location_code, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE size_mm = VALUES(size_mm), diameter_mm = VALUES(diameter_mm), length_mm = VALUES(length_mm),
                                         grade = VALUES(grade), standard_name = VALUES(standard_name), description = VALUES(description),
                                         qty = VALUES(qty), min_qty = VALUES(min_qty), location_code = VALUES(location_code)'
            );

            $insertTxn = $pdo->prepare(
                "INSERT INTO transactions (product_id, user_id, user_username, user_full_name, txn_type, qty, balance_after, note)
                 VALUES (?, 1, ?, 'ผู้จัดการคลังสินค้า', 'IN', ?, ?, 'ยอดยกมาจาก PO ที่ได้รับตาม Excel')"
            );

            $pdo->beginTransaction();

            foreach ($rawRows as $r) {
                $cells = awardedCells($r);
                if (empty($cells[0])) continue;

                $partNumber = awardedCellAt($cells, $indexes, 'partnumber');
                $jde = awardedCellAt($cells, $indexes, 'jde');
                $sku = awardedCatalogSku($partNumber, $jde);
                if ($sku === '') continue;

                $size = awardedCellAt($cells, $indexes, 'size');
                [$diameterMm, $lengthMm] = awardedParseSize($size);
                $grade = awardedCellAt($cells, $indexes, 'studmaterial');
                if ($grade === '') $grade = awardedCellAt($cells, $indexes, 'coating');
                if ($grade === '') $grade = 'UNSPECIFIED';

                $thread = awardedCellAt($cells, $indexes, 'thread');
                $lotName = awardedCellAt($cells, $indexes, 'lotname');
                $name = awardedCellAt($cells, $indexes, 'awardedlineitemname');
                $desc = $jde !== '' && strcasecmp($sku, $jde) !== 0 ? 'JDE ' . $jde . ' · ' . $name : $name;

                // Column 17: Total Quantity received from PO placed
                $receivedQtyStr = trim($cells[17] ?? '');
                $qty = 0;
                if ($receivedQtyStr !== '' && is_numeric($receivedQtyStr) && (int)$receivedQtyStr > 0) {
                    $qty = (int)$receivedQtyStr;
                    $stats['with_stock']++;
                    $stats['total_stock_units'] += $qty;
                }

                $minQty = ($qty > 0) ? max(10, (int)round($qty * 0.1)) : 0;

                $insertProduct->execute([
                    awardedClip($sku, 80),
                    awardedClip($size !== '' ? $size : $sku, 30),
                    $diameterMm,
                    $lengthMm ?? 100.0,
                    awardedClip($grade, 80),
                    awardedClip($thread, 80),
                    awardedClip($desc, 255),
                    $qty,
                    $minQty,
                    awardedClip($lotName, 80),
                ]);

                $stats['products_inserted']++;

                if ($qty > 0) {
                    $pid = (int) $pdo->lastInsertId();
                    if ($pid === 0) {
                        $findId = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
                        $findId->execute([$sku]);
                        $pid = (int) $findId->fetchColumn();
                    }
                    if ($pid > 0) {
                        $insertTxn->execute([$pid, $managerUser, $qty, $qty]);
                    }
                }
            }

            // Log activity
            $logStmt = $pdo->prepare(
                "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
                 VALUES (1, ?, 'ผู้จัดการคลังสินค้า', 'IMPORT', ?)"
            );
            $logStmt->execute([
                $managerUser,
                "ติดตั้งระบบและนำเข้าสินค้าเริ่มต้น {$stats['products_inserted']} SKU (มียกยอดสต๊อก {$stats['with_stock']} รายการ รวม " . number_format($stats['total_stock_units']) . " ชิ้น)"
            ]);

            $pdo->commit();
            $messages[] = "นำเข้าสินค้าจาก Master Excel สำเร็จ {$stats['products_inserted']} SKU (มีสต๊อกเริ่มต้น {$stats['with_stock']} SKU รวม " . number_format($stats['total_stock_units']) . " ชิ้น)";
        }

        // 7. Update app/config.php if settings differ
        $configFile = __DIR__ . '/app/config.php';
        $configContent = <<<PHP
<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Bangkok');

define('DB_HOST', getenv('DB_HOST') ?: '{$dbHost}');
define('DB_NAME', getenv('DB_NAME') ?: '{$dbName}');
define('DB_USER', getenv('DB_USER') ?: '{$dbUser}');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '{$activePass}');

function db(): PDO
{
    static \$pdo = null;
    if (\$pdo instanceof PDO) {
        return \$pdo;
    }

    \$options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    \$passwordsToTry = [DB_PASS];
    if (DB_PASS === 'root') \$passwordsToTry[] = '';
    elseif (DB_PASS === '') \$passwordsToTry[] = 'root';

    \$databasesToTry = [DB_NAME];
    if (DB_NAME === 'studbolt_stock') \$databasesToTry[] = 'studbolt_stocks';
    elseif (DB_NAME === 'studbolt_stocks') \$databasesToTry[] = 'studbolt_stock';

    \$lastException = null;
    foreach (\$passwordsToTry as \$p) {
        foreach (\$databasesToTry as \$db) {
            try {
                \$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . \$db . ';charset=utf8mb4';
                \$pdo = new PDO(\$dsn, DB_USER, \$p, \$options);
                return \$pdo;
            } catch (PDOException \$e) {
                \$lastException = \$e;
            }
        }
    }

    throw \$lastException ?: new PDOException('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
}

function require_login(): void
{
    if (empty(\$_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }

    \$stmt = db()->prepare('SELECT active FROM users WHERE id = ? LIMIT 1');
    \$stmt->execute([\$_SESSION['user']['id']]);
    if ((int) \$stmt->fetchColumn() !== 1) {
        \$_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

function require_manager(): void
{
    require_login();
    if (\$_SESSION['user']['role'] !== 'manager') {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เข้าถึง');
    }
}

function h(\$value): string
{
    return htmlspecialchars((string) \$value, ENT_QUOTES, 'UTF-8');
}
PHP;

        file_put_contents($configFile, $configContent);
        $messages[] = "อัปเดตการตั้งค่าใน app/config.php เรียบร้อยแล้ว";

        $step = 'success';
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="th" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StudBolt Stock | ตัวติดตั้งระบบ (Installer & Setup)</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="public/assets/style.css">
    <style>
        body { background: #f3f6fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; font-family: 'Prompt', system-ui, sans-serif; }
        .setup-card { max-width: 720px; width: 100%; }
    </style>
</head>
<body>
    <div class="setup-card card bg-base-100 shadow-2xl border border-base-200">
        <div class="card-body p-6 md:p-10">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-3xl font-bold">⚙</div>
                <div>
                    <h1 class="text-2xl font-bold text-base-content">StudBolt Stock Management</h1>
                    <p class="text-sm text-base-content/70">ตัวติดตั้งฐานข้อมูลและนำเข้าข้อมูลสต๊อกอัตโนมัติ (Ready-to-Use Setup)</p>
                </div>
            </div>

            <!-- Requirement Checks -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-6">
                <div class="p-3 rounded-lg border text-center <?= $phpVersionOk ? 'bg-success/10 border-success/30 text-success' : 'bg-error/10 border-error/30 text-error' ?>">
                    <div class="text-xs uppercase font-semibold">PHP Version</div>
                    <div class="text-sm font-bold"><?= PHP_VERSION ?> <?= $phpVersionOk ? '✓' : '✗' ?></div>
                </div>
                <div class="p-3 rounded-lg border text-center <?= $extPdo ? 'bg-success/10 border-success/30 text-success' : 'bg-error/10 border-error/30 text-error' ?>">
                    <div class="text-xs uppercase font-semibold">PDO MySQL</div>
                    <div class="text-sm font-bold"><?= $extPdo ? 'พร้อมใช้งาน ✓' : 'ไม่มี ✗' ?></div>
                </div>
                <div class="p-3 rounded-lg border text-center <?= $extSimpleXml ? 'bg-success/10 border-success/30 text-success' : 'bg-error/10 border-error/30 text-error' ?>">
                    <div class="text-xs uppercase font-semibold">SimpleXML</div>
                    <div class="text-sm font-bold"><?= $extSimpleXml ? 'พร้อมใช้งาน ✓' : 'ไม่มี ✗' ?></div>
                </div>
                <div class="p-3 rounded-lg border text-center <?= $extZip ? 'bg-success/10 border-success/30 text-success' : 'bg-error/10 border-error/30 text-error' ?>">
                    <div class="text-xs uppercase font-semibold">ZipArchive</div>
                    <div class="text-sm font-bold"><?= $extZip ? 'พร้อมใช้งาน ✓' : 'ไม่มี ✗' ?></div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error mb-6 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div>
                        <div class="font-bold">เกิดข้อผิดพลาดในการติดตั้ง</div>
                        <ul class="list-disc list-inside text-sm">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($step === 'success'): ?>
                <div class="alert alert-success mb-6 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div>
                        <h3 class="font-bold text-lg">ติดตั้งและนำเข้าข้อมูลสำเร็จ!</h3>
                        <p class="text-sm">ฐานข้อมูลพร้อมใช้งาน บัญชีผู้ดูแลและสินค้าจาก Master Excel ถูกสร้างเรียบร้อยแล้ว</p>
                    </div>
                </div>

                <div class="bg-base-200/60 p-5 rounded-xl border border-base-300 mb-6 space-y-2">
                    <div class="font-semibold text-base mb-2">สรุปรายการที่ติดตั้ง:</div>
                    <div class="flex justify-between text-sm py-1 border-b border-base-300">
                        <span>จำนวน SKU สินค้าที่นำเข้า:</span>
                        <strong class="text-primary font-bold"><?= number_format($stats['products_inserted']) ?> SKU</strong>
                    </div>
                    <div class="flex justify-between text-sm py-1 border-b border-base-300">
                        <span>รายการที่มียอดยกมาจาก PO (ชิ้น):</span>
                        <strong class="text-success font-bold"><?= number_format($stats['with_stock']) ?> รายการ (<?= number_format($stats['total_stock_units']) ?> ชิ้น)</strong>
                    </div>
                    <div class="flex justify-between text-sm py-1 border-b border-base-300">
                        <span>บัญชีผู้จัดการ (Manager):</span>
                        <span>Username: <code class="badge badge-outline">manager</code> | รหัสผ่าน: <code class="badge badge-outline"><?= htmlspecialchars($_POST['manager_password'] ?? $defaultManagerPass) ?></code></span>
                    </div>
                    <div class="flex justify-between text-sm py-1">
                        <span>บัญชีพนักงานคลัง (Staff):</span>
                        <span>Username: <code class="badge badge-outline">staff</code> | รหัสผ่าน: <code class="badge badge-outline"><?= htmlspecialchars($_POST['staff_password'] ?? $defaultStaffPass) ?></code></span>
                    </div>
                </div>

                <div class="flex gap-4">
                    <a href="index.php" class="btn btn-primary flex-1 text-base">เข้าสู่ระบบ (Go to Login) →</a>
                    <a href="setup.php" class="btn btn-outline">ติดตั้งใหม่</a>
                </div>
            <?php else: ?>
                <form method="post" class="space-y-6">
                    <!-- DB Config -->
                    <div>
                        <h2 class="text-lg font-bold border-b border-base-200 pb-2 mb-3">1. กำหนดการเชื่อมต่อฐานข้อมูล (Database Connection)</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="label"><span class="label-text font-semibold">MySQL Host</span></label>
                                <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? $defaultHost) ?>" class="input input-bordered w-full" required>
                            </div>
                            <div>
                                <label class="label"><span class="label-text font-semibold">Database Name</span></label>
                                <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? $defaultDbName) ?>" class="input input-bordered w-full" required>
                            </div>
                            <div>
                                <label class="label"><span class="label-text font-semibold">Username</span></label>
                                <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? $defaultUser) ?>" class="input input-bordered w-full" required>
                            </div>
                            <div>
                                <label class="label"><span class="label-text font-semibold">Password</span></label>
                                <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? $defaultPass) ?>" class="input input-bordered w-full" placeholder="MAMP: root / XAMPP: เว้นว่าง">
                            </div>
                        </div>
                    </div>

                    <!-- User Accounts -->
                    <div>
                        <h2 class="text-lg font-bold border-b border-base-200 pb-2 mb-3">2. บัญชีผู้ใช้งานเริ่มต้น (Default Users)</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="label"><span class="label-text font-semibold">ผู้จัดการ (Manager)</span></label>
                                <input type="text" name="manager_username" value="manager" class="input input-bordered w-full mb-2" readonly>
                                <input type="text" name="manager_password" value="<?= $defaultManagerPass ?>" class="input input-bordered w-full" placeholder="รหัสผ่านผู้จัดการ" required>
                            </div>
                            <div>
                                <label class="label"><span class="label-text font-semibold">พนักงานคลัง (Stock Staff)</span></label>
                                <input type="text" name="staff_username" value="staff" class="input input-bordered w-full mb-2" readonly>
                                <input type="text" name="staff_password" value="<?= $defaultStaffPass ?>" class="input input-bordered w-full" placeholder="รหัสผ่านพนักงานคลัง" required>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Seed Option -->
                    <div class="p-4 bg-primary/5 rounded-xl border border-primary/20">
                        <label class="cursor-pointer flex items-start gap-3">
                            <input type="checkbox" name="seed_excel" value="1" checked class="checkbox checkbox-primary mt-1">
                            <div>
                                <span class="font-bold text-base block text-primary">นำเข้าสินค้าทั้งหมด 151 รายการจาก Master Excel อัตโนมัติ</span>
                                <span class="text-sm text-base-content/70">
                                    ระบบจะแปลงขนาดนิ้วเป็น mm, วิเคราะห์เกรด/เกลียว, ตั้งจุดเตือน และลงยอดสต๊อกเริ่มต้นสำหรับ 48 รายการที่ได้รับ PO แล้ว
                                </span>
                            </div>
                        </label>
                    </div>

                    <button type="submit" name="run_setup" value="1" class="btn btn-primary w-full text-base py-3 h-auto shadow-lg hover:shadow-primary/25">
                        ⚙ เริ่มติดตั้งและสร้างฐานข้อมูลพร้อมใช้งาน (Run Setup)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
