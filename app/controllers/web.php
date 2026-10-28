<?php
require dirname(__DIR__) . '/config.php';
define('APP_BOOTSTRAPPED', true);

if (isset($_GET['logout'])) {
    if (!empty($_SESSION['user'])) {
        $logoutUser = $_SESSION['user'];
        $userExists = db()->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
        $userExists->execute([$logoutUser['id']]);
        if ($userExists->fetchColumn()) {
            $log = db()->prepare(
                "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
                 VALUES (?, ?, ?, 'LOGOUT', ?)"
            );
            $log->execute([
                $logoutUser['id'],
                $logoutUser['username'],
                $logoutUser['full_name'],
                'ออกจากระบบ',
            ]);
        }
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $stmt = db()->prepare(
        'SELECT id, username, password_hash, full_name, role FROM users WHERE username = ? AND active = 1 LIMIT 1'
    );
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        $log = db()->prepare(
            "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
             VALUES (?, ?, ?, 'LOGIN', ?)"
        );
        $log->execute([$user['id'], $user['username'], $user['full_name'], 'เข้าสู่ระบบสำเร็จ']);
        header('Location: index.php');
        exit;
    }

    $username = substr(trim($_POST['username'] ?? ''), 0, 80);
    $log = db()->prepare(
        "INSERT INTO activity_logs (username, event_type, details)
         VALUES (?, 'LOGIN_FAILED', ?)"
    );
    $log->execute([$username, 'เข้าสู่ระบบไม่สำเร็จ']);
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

if (empty($_SESSION['user'])) {
    require dirname(__DIR__) . '/views/login.php';
    exit;
}
require_login();

$u = $_SESSION['user'];
$manager = $u['role'] === 'manager';
$stockStaff = $u['role'] === 'stock_staff';
$canViewStock = $manager || $stockStaff;
$page = $_GET['page'] ?? 'dashboard';

if ($page === 'accounts' && !$manager) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึง');
}
if (!in_array($page, ['dashboard', 'accounts'], true)) {
    http_response_code(404);
    exit('ไม่พบหน้าที่ต้องการ');
}
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$pendingStockRequestCount = $manager
    ? (int) db()->query("SELECT COUNT(*) FROM stock_requests WHERE status = 'PENDING'")->fetchColumn()
    : 0;

if (isset($_GET['export']) && !$manager) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์ส่งออกข้อมูล');
}

if (isset($_GET['export']) && $_GET['export'] === 'template' && $manager) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="studbolt_products_template.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['sku', 'size_mm', 'diameter_mm', 'length_mm', 'grade', 'standard_name', 'description', 'qty', 'min_qty', 'location_code']);
    fclose($out);
    exit;
}

$csvSafeText = static function ($value): string {
    $value = (string) $value;
    return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'" . $value : $value;
};

if (isset($_GET['export']) && $_GET['export'] === 'selected' && $manager) {
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('PHP ยังไม่ได้เปิดใช้งาน ZipArchive จึงรวมไฟล์ส่งออกไม่ได้');
    }

    $datasetNames = ['products', 'accounts', 'activity', 'transactions'];
    $requestedDatasets = $_GET['datasets'] ?? [];
    if (!is_array($requestedDatasets)) {
        http_response_code(422);
        exit('รูปแบบชุดข้อมูลที่เลือกไม่ถูกต้อง');
    }
    foreach ($requestedDatasets as $requestedDataset) {
        if (!is_string($requestedDataset)) {
            http_response_code(422);
            exit('รูปแบบชุดข้อมูลที่เลือกไม่ถูกต้อง');
        }
    }
    $selectedDatasets = array_values(array_unique(array_intersect($datasetNames, $requestedDatasets)));
    if (!$selectedDatasets) {
        http_response_code(422);
        exit('กรุณาเลือกข้อมูลที่ต้องการส่งออกอย่างน้อยหนึ่งชุด');
    }

    $eventLabels = [
        'LOGIN' => 'เข้าสู่ระบบ',
        'LOGIN_FAILED' => 'เข้าสู่ระบบไม่สำเร็จ',
        'LOGOUT' => 'ออกจากระบบ',
        'IMPORT' => 'นำเข้าสินค้า',
        'USER_CREATE' => 'สร้างบัญชีผู้ใช้',
        'USER_DELETE' => 'ลบบัญชีผู้ใช้',
        'PASSWORD_CHANGE' => 'เปลี่ยนรหัสผ่าน',
        'LOG_CLEAR' => 'ล้างประวัติ log',
        'STOCK_REQUEST' => 'ส่งคำขอสต๊อก',
        'STOCK_REQUEST_REVIEW' => 'ตรวจสอบคำขอสต๊อก',
    ];
    $makeCsv = static function (array $headers, array $rows, callable $formatRow): string {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('เตรียมข้อมูล CSV ไม่สำเร็จ');
        }
        if (fwrite($stream, "\xEF\xBB\xBF") === false || fputcsv($stream, $headers) === false) {
            fclose($stream);
            throw new RuntimeException('เขียนข้อมูล CSV ไม่สำเร็จ');
        }
        foreach ($rows as $row) {
            if (fputcsv($stream, $formatRow($row)) === false) {
                fclose($stream);
                throw new RuntimeException('เขียนข้อมูล CSV ไม่สำเร็จ');
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if ($csv === false) {
            throw new RuntimeException('อ่านข้อมูล CSV ไม่สำเร็จ');
        }
        return $csv;
    };

    $csvFiles = [];
    foreach ($selectedDatasets as $dataset) {
        if ($dataset === 'products') {
            $rows = db()->query(
                'SELECT sku, size_mm, diameter_mm, length_mm, grade, standard_name, description, qty, min_qty, location_code, active, created_at, updated_at
                 FROM products
                 ORDER BY id'
            )->fetchAll();
            $csvFiles['products.csv'] = $makeCsv(
                ['SKU', 'ขนาด', 'เส้นผ่านศูนย์กลาง mm', 'ความยาว mm', 'เกรด', 'มาตรฐาน', 'รายละเอียด', 'คงเหลือ', 'จำนวนขั้นต่ำ', 'ตำแหน่ง', 'สถานะ', 'สร้างเมื่อ', 'แก้ไขล่าสุด'],
                $rows,
                static fn(array $row): array => [
                    $csvSafeText($row['sku']), $csvSafeText($row['size_mm']), $row['diameter_mm'], $row['length_mm'],
                    $csvSafeText($row['grade']), $csvSafeText($row['standard_name']), $csvSafeText($row['description']),
                    $row['qty'], $row['min_qty'], $csvSafeText($row['location_code']),
                    (int) $row['active'] === 1 ? 'ใช้งาน' : 'ปิดใช้งาน', $row['created_at'], $row['updated_at'],
                ]
            );
        } elseif ($dataset === 'accounts') {
            $rows = db()->query(
                'SELECT username, full_name, role, active, created_at
                 FROM users
                 ORDER BY id'
            )->fetchAll();
            $csvFiles['accounts.csv'] = $makeCsv(
                ['ชื่อ-นามสกุล', 'ชื่อผู้ใช้', 'สิทธิ์', 'สถานะ', 'สร้างเมื่อ'],
                $rows,
                static fn(array $row): array => [
                    $csvSafeText($row['full_name']),
                    $csvSafeText($row['username']),
                    $row['role'] === 'manager' ? 'ผู้จัดการ' : ($row['role'] === 'stock_staff' ? 'พนักงานคลัง' : 'พนักงานเบิกสินค้า'),
                    (int) $row['active'] === 1 ? 'ใช้งาน' : 'ปิดใช้งาน',
                    $row['created_at'],
                ]
            );
        } elseif ($dataset === 'activity') {
            $rows = db()->query(
                'SELECT username, full_name, event_type, details, created_at
                 FROM activity_logs
                 ORDER BY id'
            )->fetchAll();
            $csvFiles['activity_logs.csv'] = $makeCsv(
                ['วันเวลา', 'กิจกรรม', 'ชื่อ-นามสกุล', 'บัญชีผู้ใช้', 'รายละเอียด'],
                $rows,
                static fn(array $row): array => [
                    $row['created_at'],
                    $eventLabels[$row['event_type']] ?? $row['event_type'],
                    $csvSafeText($row['full_name'] ?: 'ไม่พบข้อมูลผู้ใช้'),
                    $csvSafeText($row['username']),
                    $csvSafeText($row['details']),
                ]
            );
        } else {
            $rows = db()->query(
                'SELECT t.id, t.created_at, t.txn_type, p.sku, p.size_mm, p.length_mm, p.grade, t.qty, t.balance_after, t.user_full_name AS full_name, t.user_username AS username, t.job_ref, t.note
                 FROM transactions t
                 JOIN products p ON p.id = t.product_id
                 ORDER BY t.id'
            )->fetchAll();
            $csvFiles['transactions.csv'] = $makeCsv(
                ['เลขที่', 'วันเวลา', 'ประเภท', 'SKU', 'ขนาด', 'ความยาว mm', 'เกรด', 'จำนวน', 'คงเหลือหลังทำรายการ', 'ผู้ทำรายการ', 'ชื่อผู้ใช้', 'เลขที่งาน', 'หมายเหตุ'],
                $rows,
                static fn(array $row): array => [
                    $row['id'], $row['created_at'], $row['txn_type'], $csvSafeText($row['sku']),
                    $csvSafeText($row['size_mm']), $row['length_mm'], $csvSafeText($row['grade']),
                    $row['qty'], $row['balance_after'], $csvSafeText($row['full_name']),
                    $csvSafeText($row['username']), $csvSafeText($row['job_ref']), $csvSafeText($row['note']),
                ]
            );
        }
    }

    $archivePath = tempnam(sys_get_temp_dir(), 'studbolt_export_');
    if ($archivePath === false) {
        throw new RuntimeException('เตรียมไฟล์ส่งออกไม่สำเร็จ');
    }
    $archive = new ZipArchive();
    if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        unlink($archivePath);
        throw new RuntimeException('สร้างไฟล์ ZIP สำหรับส่งออกไม่สำเร็จ');
    }
    foreach ($csvFiles as $filename => $contents) {
        if (!$archive->addFromString($filename, $contents)) {
            $archive->close();
            unlink($archivePath);
            throw new RuntimeException('เพิ่มข้อมูลลงไฟล์ ZIP ไม่สำเร็จ');
        }
    }
    if (!$archive->close()) {
        unlink($archivePath);
        throw new RuntimeException('บันทึกไฟล์ ZIP สำหรับส่งออกไม่สำเร็จ');
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="studbolt_selected_export_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . filesize($archivePath));
    readfile($archivePath);
    unlink($archivePath);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'accounts' && $manager) {
    $rows = db()->query(
        'SELECT username, full_name, role, active, created_at
         FROM users
         ORDER BY active DESC, full_name'
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="studbolt_accounts_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ชื่อ-นามสกุล', 'ชื่อผู้ใช้', 'สิทธิ์', 'สถานะ', 'สร้างเมื่อ']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $csvSafeText($row['full_name']),
            $csvSafeText($row['username']),
            $row['role'] === 'manager' ? 'ผู้จัดการ' : ($row['role'] === 'stock_staff' ? 'พนักงานคลัง' : 'พนักงาน'),
            (int) $row['active'] === 1 ? 'ใช้งาน' : 'ปิดใช้งาน',
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'activity' && $manager) {
    $rows = db()->query(
        'SELECT username, full_name, event_type, details, created_at
         FROM activity_logs
         ORDER BY id DESC'
    )->fetchAll();

    $eventLabels = [
        'LOGIN' => 'เข้าสู่ระบบ',
        'LOGIN_FAILED' => 'เข้าสู่ระบบไม่สำเร็จ',
        'LOGOUT' => 'ออกจากระบบ',
        'IMPORT' => 'นำเข้าสินค้า',
        'USER_CREATE' => 'สร้างบัญชีผู้ใช้',
        'USER_DELETE' => 'ลบบัญชีผู้ใช้',
        'PASSWORD_CHANGE' => 'เปลี่ยนรหัสผ่าน',
        'LOG_CLEAR' => 'ล้างประวัติ log',
        'STOCK_REQUEST' => 'ส่งคำขอสต๊อก',
        'STOCK_REQUEST_REVIEW' => 'ตรวจสอบคำขอสต๊อก',
    ];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="studbolt_login_history_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['วันเวลา', 'กิจกรรม', 'ชื่อ-นามสกุล', 'บัญชีผู้ใช้', 'รายละเอียด']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['created_at'],
            $eventLabels[$row['event_type']] ?? $row['event_type'],
            $csvSafeText($row['full_name'] ?: 'ไม่พบข้อมูลผู้ใช้'),
            $csvSafeText($row['username']),
            $csvSafeText($row['details']),
        ]);
    }
    fclose($out);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'products' && $manager) {
    $rows = db()->query(
        'SELECT sku, size_mm, diameter_mm, length_mm, grade, standard_name, description, qty, min_qty, location_code
         FROM products
         WHERE active = 1
         ORDER BY size_mm + 0, length_mm, grade'
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="studbolt_products_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['SKU', 'ขนาด', 'เส้นผ่านศูนย์กลาง mm', 'ความยาว mm', 'เกรด', 'มาตรฐาน', 'รายละเอียด', 'คงเหลือ', 'จำนวนขั้นต่ำ', 'ตำแหน่ง']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['sku'],
            $r['size_mm'],
            $r['diameter_mm'],
            $r['length_mm'],
            $r['grade'],
            $r['standard_name'],
            $r['description'],
            $r['qty'],
            $r['min_qty'],
            $r['location_code'],
        ]);
    }

    fclose($out);
    exit;
}

if (isset($_GET['export']) && in_array($_GET['export'], ['1', 'transactions'], true) && $manager) {
    $rows = db()->query(
        'SELECT t.id, t.created_at, t.txn_type, p.sku, p.size_mm, p.length_mm, p.grade, t.qty, t.balance_after, t.user_full_name AS full_name, t.user_username AS username, t.job_ref, t.note
         FROM transactions t
         JOIN products p ON p.id = t.product_id
         ORDER BY t.id DESC'
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="studbolt_transactions_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['เลขที่', 'วันเวลา', 'ประเภท', 'SKU', 'ขนาด', 'ความยาว mm', 'เกรด', 'จำนวน', 'คงเหลือหลังทำรายการ', 'ผู้ทำรายการ', 'ชื่อผู้ใช้', 'เลขที่งาน', 'หมายเหตุ']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['created_at'],
            $r['txn_type'],
            $r['sku'],
            $r['size_mm'],
            $r['length_mm'],
            $r['grade'],
            $r['qty'],
            $r['balance_after'],
            $r['full_name'],
            $r['username'],
            $r['job_ref'],
            $r['note'],
        ]);
    }

    fclose($out);
    exit;
}

if ($page === 'accounts') {
    $stockRequests = db()->query(
        "SELECT r.id, r.requester_username, r.requester_full_name, r.request_type, r.qty,
                r.job_ref, r.note, r.created_at, p.sku, p.description, p.size_mm, p.length_mm,
                p.grade, p.qty AS current_qty
         FROM stock_requests r
         JOIN products p ON p.id = r.product_id
         WHERE r.status = 'PENDING'
         ORDER BY r.created_at, r.id"
    )->fetchAll();
    $activityTotal = (int) db()->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
    $activityPages = max(1, (int) ceil($activityTotal / 10));
    $activityPage = filter_var($_GET['activity_page'] ?? 1, FILTER_VALIDATE_INT);
    $activityPage = $activityPage === false ? 1 : max(1, min($activityPages, $activityPage));
    $activityOffset = ($activityPage - 1) * 10;
    $activityLogs = db()->query(
        'SELECT id, username, full_name, event_type, details, created_at
         FROM activity_logs
         ORDER BY id DESC
         LIMIT 10 OFFSET ' . $activityOffset
    )->fetchAll();
    $accounts = db()->query(
        'SELECT id, username, full_name, role, active, created_at
         FROM users
         ORDER BY active DESC, full_name'
    )->fetchAll();

    require dirname(__DIR__) . '/views/layout.php';
    require dirname(__DIR__) . '/views/accounts_page.php';
    require dirname(__DIR__) . '/views/accounts_scripts.php';
    exit;
}

$products = db()->query(
    'SELECT * FROM products WHERE active = 1 ORDER BY size_mm + 0, length_mm, grade'
)->fetchAll();

$productSearch = isset($_GET['product_search']) && is_string($_GET['product_search'])
    ? trim($_GET['product_search'])
    : '';
$inventoryFilter = '';
$inventoryParams = [];
if ($productSearch !== '') {
    $inventoryFilter = " AND (sku LIKE ? ESCAPE '!' OR size_mm LIKE ? ESCAPE '!' OR grade LIKE ? ESCAPE '!' OR standard_name LIKE ? ESCAPE '!')";
    $searchTerm = '%' . strtr($productSearch, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $inventoryParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}
$inventoryCount = db()->prepare('SELECT COUNT(*) FROM products WHERE active = 1' . $inventoryFilter);
$inventoryCount->execute($inventoryParams);
$inventoryTotal = (int) $inventoryCount->fetchColumn();
$inventoryPages = max(1, (int) ceil($inventoryTotal / 10));
$inventoryPage = filter_var($_GET['product_page'] ?? 1, FILTER_VALIDATE_INT);
$inventoryPage = $inventoryPage === false ? 1 : max(1, min($inventoryPages, $inventoryPage));
$inventoryOffset = ($inventoryPage - 1) * 10;
$inventoryQuery = db()->prepare(
    'SELECT * FROM products WHERE active = 1' . $inventoryFilter .
    ' ORDER BY size_mm + 0, length_mm, grade LIMIT 10 OFFSET ' . $inventoryOffset
);
$inventoryQuery->execute($inventoryParams);
$inventoryProducts = $inventoryQuery->fetchAll();

$totals = db()->query(
    'SELECT COUNT(*) skus, COALESCE(SUM(qty), 0) pieces, SUM(CASE WHEN qty <= min_qty THEN 1 ELSE 0 END) low_count
     FROM products WHERE active = 1'
)->fetch();

$transactionTotal = (int) db()->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
$transactionPages = max(1, (int) ceil($transactionTotal / 10));
$transactionPage = filter_var($_GET['txn_page'] ?? 1, FILTER_VALIDATE_INT);
$transactionPage = $transactionPage === false ? 1 : max(1, min($transactionPages, $transactionPage));
$transactionOffset = ($transactionPage - 1) * 10;

$recent = db()->query(
    'SELECT t.*, p.sku, p.size_mm, p.length_mm, t.user_full_name AS full_name, t.user_username AS username
     FROM transactions t
     JOIN products p ON p.id = t.product_id
     ORDER BY t.id DESC
     LIMIT 10 OFFSET ' . $transactionOffset
)->fetchAll();

$low = db()->query(
    'SELECT * FROM products WHERE active = 1 AND qty <= min_qty ORDER BY qty ASC LIMIT 10'
)->fetchAll();
require dirname(__DIR__) . '/views/layout.php';
require dirname(__DIR__) . '/views/dashboard/overview.php';
require dirname(__DIR__) . '/views/dashboard/issue.php';
require dirname(__DIR__) . '/views/dashboard/inventory.php';
require dirname(__DIR__) . '/views/dashboard/history.php';
require dirname(__DIR__) . '/views/dialogs.php';
require dirname(__DIR__) . '/views/scripts.php';