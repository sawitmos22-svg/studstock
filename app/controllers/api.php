<?php
require dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
require_login();

const MAX_IMPORT_PRODUCTS = 10000;

$u = $_SESSION['user'];
$isImportRequest = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'import_products';
$data = $isImportRequest
    ? $_POST
    : (json_decode(file_get_contents('php://input'), true) ?? []);
$pdo = null;

function respond(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function importError(string $message): never
{
    throw new RuntimeException($message);
}

function normalizeImportHeader(string $header): string
{
    $header = strtolower(trim($header));
    $header = preg_replace('/[\s_\-().\/]+/u', '', $header) ?? $header;

    $aliases = [
        'sku' => 'sku',
        'productsku' => 'sku',
        'รหัสสินค้า' => 'sku',
        'size' => 'size_mm',
        'sizemm' => 'size_mm',
        'ขนาด' => 'size_mm',
        'diameter' => 'diameter_mm',
        'diametermm' => 'diameter_mm',
        'เส้นผ่านศูนย์กลางmm' => 'diameter_mm',
        'length' => 'length_mm',
        'lengthmm' => 'length_mm',
        'ความยาวmm' => 'length_mm',
        'grade' => 'grade',
        'เกรด' => 'grade',
        'standard' => 'standard_name',
        'standardname' => 'standard_name',
        'มาตรฐาน' => 'standard_name',
        'description' => 'description',
        'รายละเอียด' => 'description',
        'qty' => 'qty',
        'quantity' => 'qty',
        'จำนวน' => 'qty',
        'minqty' => 'min_qty',
        'minimumqty' => 'min_qty',
        'จำนวนขั้นต่ำ' => 'min_qty',
        'location' => 'location_code',
        'locationcode' => 'location_code',
        'ตำแหน่ง' => 'location_code',
    ];

    return $aliases[$header] ?? '';
}

function xmlText(SimpleXMLElement $element, string $namespace): string
{
    $text = '';
    foreach ($element->children($namespace) as $child) {
        if ($child->getName() === 't') {
            $text .= (string) $child;
        } else {
            $text .= xmlText($child, $namespace);
        }
    }
    return $text;
}

function parseSpreadsheetXml(string $xml): SimpleXMLElement
{
    $previousErrorMode = libxml_use_internal_errors(true);
    $result = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorMode);

    if (!$result) {
        $detail = $errors ? trim($errors[0]->message) : 'รูปแบบ XML ไม่ถูกต้อง';
        importError('อ่านโครงสร้าง Excel ไม่สำเร็จ: ' . $detail);
    }
    return $result;
}

function xlsxRows(string $filePath): array
{
    if (!class_exists(ZipArchive::class)) {
        importError('PHP ยังไม่ได้เปิดใช้งาน ZipArchive จึงอ่านไฟล์ .xlsx ไม่ได้');
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        importError('ไฟล์ Excel เสียหายหรือเปิดไม่ได้');
    }

    try {
        if ($zip->numFiles > 1000) {
            importError('ไฟล์ Excel มีองค์ประกอบมากเกินไป');
        }
        $uncompressedSize = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            if ($entry === false) {
                importError('อ่านรายการภายในไฟล์ Excel ไม่สำเร็จ');
            }
            $uncompressedSize += (int) $entry['size'];
            if ($uncompressedSize > 30 * 1024 * 1024) {
                importError('ขนาดข้อมูลภายในไฟล์ Excel ใหญ่เกินไป');
            }
        }

        $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $packageNamespace = 'http://schemas.openxmlformats.org/package/2006/relationships';

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relationsXml === false) {
            importError('ไฟล์นี้ไม่มีโครงสร้าง Excel .xlsx ที่ถูกต้อง');
        }

        $workbook = parseSpreadsheetXml($workbookXml);
        $relations = parseSpreadsheetXml($relationsXml);

        $workbook->registerXPathNamespace('x', $mainNamespace);
        $sheets = $workbook->xpath('/x:workbook/x:sheets/x:sheet') ?: [];
        $firstSheet = $sheets[0] ?? null;
        if (!$firstSheet) {
            importError('ไม่พบ worksheet ในไฟล์ Excel');
        }

        $relationshipId = (string) $firstSheet->attributes($relationshipNamespace)['id'];
        $target = '';
        $relations->registerXPathNamespace('p', $packageNamespace);
        foreach ($relations->xpath('/p:Relationships/p:Relationship') ?: [] as $relation) {
            $attributes = $relation->attributes();
            if ((string) $attributes['Id'] === $relationshipId) {
                $target = (string) $attributes['Target'];
                break;
            }
        }
        if ($target === '') {
            importError('ไม่พบ worksheet แรกในไฟล์ Excel');
        }

        $target = str_replace('\\', '/', $target);
        $parts = strpos($target, '/') === 0 ? [] : ['xl'];
        foreach (explode('/', trim($target, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        $target = implode('/', $parts);
        $worksheetXml = $zip->getFromName($target);
        if ($worksheetXml === false) {
            importError('เปิด worksheet แรกในไฟล์ Excel ไม่สำเร็จ');
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $sharedXml = parseSpreadsheetXml($sharedStringsXml);
            $sharedXml->registerXPathNamespace('x', $mainNamespace);
            foreach ($sharedXml->xpath('/x:sst/x:si') ?: [] as $string) {
                $sharedStrings[] = xmlText($string, $mainNamespace);
            }
        }

        $worksheet = parseSpreadsheetXml($worksheetXml);
        $worksheet->registerXPathNamespace('x', $mainNamespace);

        $rows = [];
        foreach ($worksheet->xpath('/x:worksheet/x:sheetData/x:row') ?: [] as $row) {
            $row->registerXPathNamespace('x', $mainNamespace);
            $lineNumber = (int) $row['r'];
            $cells = [];
            foreach ($row->xpath('./x:c') ?: [] as $cell) {
                $reference = (string) $cell['r'];
                if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) {
                    continue;
                }

                $column = 0;
                foreach (str_split(strtoupper($matches[1])) as $letter) {
                    $column = $column * 26 + ord($letter) - 64;
                }

                $value = '';
                $type = (string) $cell['t'];
                $cell->registerXPathNamespace('x', $mainNamespace);
                if ($type === 'inlineStr') {
                    $inlineStrings = $cell->xpath('./x:is') ?: [];
                    $value = isset($inlineStrings[0]) ? xmlText($inlineStrings[0], $mainNamespace) : '';
                } else {
                    $values = $cell->xpath('./x:v') ?: [];
                    if (isset($values[0])) {
                        $value = (string) $values[0];
                    }
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }
                }

                $cells[$column - 1] = $value;
            }

            if (count($rows) >= MAX_IMPORT_PRODUCTS + 1) {
                importError('นำเข้าได้ไม่เกิน 10,000 รายการต่อครั้ง');
            }
            $rows[] = ['line' => $lineNumber ?: count($rows) + 1, 'cells' => $cells];
        }

        return $rows;
    } finally {
        $zip->close();
    }
}

function csvRows(string $filePath): array
{
    $contents = file_get_contents($filePath);
    if ($contents === false || $contents === '') {
        importError('ไฟล์ CSV ว่างหรืออ่านไม่ได้');
    }

    if (strncmp($contents, "\xEF\xBB\xBF", 3) === 0) {
        $contents = substr($contents, 3);
    }
    if (preg_match('//u', $contents) !== 1) {
        $contents = iconv('Windows-874', 'UTF-8', $contents);
        if ($contents === false) {
            importError('ไฟล์ CSV ต้องบันทึกเป็น UTF-8 หรือ Windows-874');
        }
    }

    $firstLine = strtok($contents, "\r\n") ?: '';
    $commaCount = count(str_getcsv($firstLine, ',', '"', ''));
    $semicolonCount = count(str_getcsv($firstLine, ';', '"', ''));
    $delimiter = $semicolonCount > $commaCount ? ';' : ',';
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $contents);
    rewind($stream);

    $rows = [];
    $line = 0;
    while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
        $line++;
        if ($line > MAX_IMPORT_PRODUCTS + 1) {
            fclose($stream);
            importError('นำเข้าได้ไม่เกิน 10,000 รายการต่อครั้ง');
        }
        $rows[] = ['line' => $line, 'cells' => $cells];
    }
    fclose($stream);
    return $rows;
}

function validatedImportProducts(array $rows): array
{
    if (count($rows) < 2) {
        importError('ไฟล์ต้องมีแถวหัวตารางและข้อมูลสินค้าอย่างน้อย 1 รายการ');
    }

    $headerCells = $rows[0]['cells'];
    $columns = [];
    foreach ($headerCells as $index => $header) {
        $field = normalizeImportHeader((string) $header);
        if ($field === '') {
            continue;
        }
        if (isset($columns[$field])) {
            importError('พบหัวคอลัมน์ซ้ำ: ' . $field);
        }
        $columns[$field] = $index;
    }

    foreach (['sku', 'size_mm', 'length_mm', 'grade'] as $required) {
        if (!isset($columns[$required])) {
            importError('ไม่พบคอลัมน์ที่จำเป็น: ' . $required);
        }
    }

    $products = [];
    $seenSkus = [];
    $errors = [];
    foreach (array_slice($rows, 1) as $row) {
        $values = [];
        foreach ($columns as $field => $index) {
            $values[$field] = trim((string) ($row['cells'][$index] ?? ''));
        }
        if (count(array_filter($values, static fn($value) => $value !== '')) === 0) {
            continue;
        }

        $line = $row['line'];
        $sku = $values['sku'] ?? '';
        $size = $values['size_mm'] ?? '';
        $grade = $values['grade'] ?? '';
        $lengthText = $values['length_mm'] ?? '';
        $qtyText = ($values['qty'] ?? '') === '' ? '0' : $values['qty'];
        $minQtyText = ($values['min_qty'] ?? '') === '' ? '0' : $values['min_qty'];
        $diameterText = $values['diameter_mm'] ?? '';

        if ($sku === '' || $size === '' || $grade === '' || !is_numeric($lengthText)
            || (float) $lengthText <= 0 || (float) $lengthText > 999999.99) {
            $errors[] = "แถว {$line}: ต้องระบุ sku, size_mm, grade และ length_mm ที่มากกว่า 0";
            continue;
        }
        if (strlen($sku) > 80 || strlen($size) > 30 || strlen($grade) > 80
            || strlen($values['standard_name'] ?? '') > 80 || strlen($values['description'] ?? '') > 255
            || strlen($values['location_code'] ?? '') > 80) {
            $errors[] = "แถว {$line}: ข้อมูลข้อความยาวเกินขนาดที่ระบบรองรับ";
            continue;
        }

        $qty = filter_var($qtyText, FILTER_VALIDATE_INT);
        $minQty = filter_var($minQtyText, FILTER_VALIDATE_INT);
        if ($qty === false || $qty < 0 || $qty > 2147483647
            || $minQty === false || $minQty < 0 || $minQty > 2147483647) {
            $errors[] = "แถว {$line}: qty และ min_qty ต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป";
            continue;
        }
        if ($diameterText !== '' && (!is_numeric($diameterText)
            || (float) $diameterText < 0 || (float) $diameterText > 999999.99)) {
            $errors[] = "แถว {$line}: diameter_mm ต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป";
            continue;
        }

        $skuKey = strtolower($sku);
        if (isset($seenSkus[$skuKey])) {
            $errors[] = "แถว {$line}: SKU ซ้ำกับแถว {$seenSkus[$skuKey]} ({$sku})";
            continue;
        }
        $seenSkus[$skuKey] = $line;

        $products[] = [
            'sku' => $sku,
            'size_mm' => $size,
            'diameter_mm' => $diameterText === '' ? null : (float) $diameterText,
            'length_mm' => (float) $lengthText,
            'grade' => $grade,
            'standard_name' => $values['standard_name'] ?? '',
            'description' => $values['description'] ?? '',
            'qty' => $qty,
            'min_qty' => $minQty,
            'location_code' => $values['location_code'] ?? '',
        ];
    }

    if ($errors) {
        respond(['error' => 'ข้อมูลในไฟล์ไม่ถูกต้อง ไม่มีรายการใดถูกนำเข้า', 'errors' => array_slice($errors, 0, 20)], 422);
    }
    if (!$products) {
        importError('ไม่พบข้อมูลสินค้าในไฟล์');
    }
    return $products;
}

try {
    if (($data['action'] ?? '') === 'clear_all_stock') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['error' => 'คำสั่งนี้ต้องใช้ POST'], 405);
        }
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้นที่เคลียร์สต๊อกได้'], 403);
        }
        $csrfToken = (string) ($data['csrf_token'] ?? '');
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            respond(['error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'], 403);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $productsToClear = $pdo->query(
            'SELECT id, qty FROM products WHERE qty > 0 ORDER BY id FOR UPDATE'
        )->fetchAll();

        if (!$productsToClear) {
            $pdo->commit();
            respond(['ok' => true, 'cleared' => 0, 'units' => 0]);
        }

        $update = $pdo->prepare('UPDATE products SET qty = 0 WHERE id = ?');
        $insert = $pdo->prepare(
            "INSERT INTO transactions (product_id, user_id, user_username, user_full_name, txn_type, qty, balance_after, note)
             VALUES (?, ?, ?, ?, 'ADJUST', 0, 0, ?)"
        );
        $unitsCleared = 0;
        foreach ($productsToClear as $product) {
            $previousQty = (int) $product['qty'];
            $update->execute([$product['id']]);
            $insert->execute([
                $product['id'],
                $u['id'],
                $u['username'],
                $u['full_name'],
                'เคลียร์สต๊อกทั้งหมด ยอดเดิม ' . number_format($previousQty) . ' ชิ้น',
            ]);
            $unitsCleared += $previousQty;
        }

        $log = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
             VALUES (?, ?, ?, 'STOCK_CLEAR', ?)"
        );
        $log->execute([
            $u['id'],
            $u['username'],
            $u['full_name'],
            'เคลียร์สต๊อก ' . count($productsToClear) . ' SKU รวม ' . number_format($unitsCleared) . ' ชิ้น',
        ]);

        $pdo->commit();
        respond(['ok' => true, 'cleared' => count($productsToClear), 'units' => $unitsCleared]);
    }

    if (($data['action'] ?? '') === 'clear_logs') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['error' => 'คำสั่งนี้ต้องใช้ POST'], 405);
        }
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้นที่เคลียร์ log ได้'], 403);
        }
        $csrfToken = (string) ($data['csrf_token'] ?? '');
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            respond(['error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'], 403);
        }
        $logType = $data['log_type'] ?? '';
        $tableByType = ['activity' => 'activity_logs', 'transactions' => 'transactions'];
        if (!is_string($logType) || !isset($tableByType[$logType])) {
            respond(['error' => 'ประเภท log ที่เลือกไม่ถูกต้อง'], 422);
        }
        $logIds = $data['log_ids'] ?? null;
        if (!is_array($logIds) || !$logIds || count($logIds) > 100) {
            respond(['error' => 'กรุณาเลือก log อย่างน้อยหนึ่งรายการ'], 422);
        }
        foreach ($logIds as $id) {
            if (!is_string($id) && !is_int($id)) {
                respond(['error' => 'รายการ log ที่เลือกไม่ถูกต้อง'], 422);
            }
            if (!preg_match('/^[1-9][0-9]{0,19}$/D', (string) $id)) {
                respond(['error' => 'รายการ log ที่เลือกไม่ถูกต้อง'], 422);
            }
        }
        $logIds = array_values(array_unique($logIds));

        $pdo = db();
        $pdo->beginTransaction();
        $placeholders = implode(', ', array_fill(0, count($logIds), '?'));
        $delete = $pdo->prepare('DELETE FROM ' . $tableByType[$logType] . ' WHERE id IN (' . $placeholders . ')');
        $delete->execute($logIds);
        $deletedCount = $delete->rowCount();
        $log = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
             VALUES (?, ?, ?, 'LOG_CLEAR', ?)"
        );
        $log->execute([
            $u['id'],
            $u['username'],
            $u['full_name'],
            'ลบ log ' . ($logType === 'activity' ? 'ประวัติการเข้าใช้งาน' : 'ประวัติรับเข้า-เบิกออก')
                . ' ที่เลือก ' . $deletedCount . ' รายการ',
        ]);
        $pdo->commit();
        respond(['ok' => true]);
    }

    if (($data['action'] ?? '') === 'import_products') {
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้น'], 403);
        }
        if (!isset($_FILES['file'])) {
            respond(['error' => 'กรุณาเลือกไฟล์ CSV หรือ Excel'], 422);
        }

        $upload = $_FILES['file'];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $message = $upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5 MB)'
                : 'อัปโหลดไฟล์ไม่สำเร็จ';
            respond(['error' => $message], 422);
        }
        if ($upload['size'] > 5 * 1024 * 1024) {
            respond(['error' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5 MB)'], 422);
        }

        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            respond(['error' => 'รองรับเฉพาะไฟล์ .csv และ .xlsx'], 422);
        }

        $rows = $extension === 'csv'
            ? csvRows($upload['tmp_name'])
            : xlsxRows($upload['tmp_name']);
        $products = validatedImportProducts($rows);

        $pdo = db();
        $pdo->beginTransaction();
        $insert = $pdo->prepare(
            'INSERT INTO products (sku, size_mm, diameter_mm, length_mm, grade, standard_name, description, qty, min_qty, location_code)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $initialStock = $pdo->prepare(
            "INSERT INTO transactions (product_id, user_id, user_username, user_full_name, txn_type, qty, balance_after, note)
             VALUES (?, ?, ?, ?, 'IN', ?, ?, ?)"
        );

        foreach ($products as $product) {
            $insert->execute([
                $product['sku'],
                $product['size_mm'],
                $product['diameter_mm'],
                $product['length_mm'],
                $product['grade'],
                $product['standard_name'],
                $product['description'],
                $product['qty'],
                $product['min_qty'],
                $product['location_code'],
            ]);
            $productId = (int) $pdo->lastInsertId();
            if ($product['qty'] > 0) {
                $initialStock->execute([
                    $productId,
                    $u['id'],
                    $u['username'],
                    $u['full_name'],
                    $product['qty'],
                    $product['qty'],
                    'ยอดตั้งต้นจากการนำเข้าไฟล์',
                ]);
            }
        }

        $log = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
             VALUES (?, ?, ?, 'IMPORT', ?)"
        );
        $log->execute([
            $u['id'],
            $u['username'],
            $u['full_name'],
            'นำเข้าสินค้า ' . count($products) . ' รายการ',
        ]);

        $pdo->commit();
        respond(['ok' => true, 'imported' => count($products)]);
    }

    if (($data['action'] ?? '') === 'pending_stock_request_count') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['error' => 'คำสั่งนี้ต้องใช้ POST'], 405);
        }
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้นที่ดูคำขอได้'], 403);
        }
        $csrfToken = $data['csrf_token'] ?? '';
        if (!is_string($csrfToken) || !isset($_SESSION['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            respond(['error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'], 403);
        }
        $count = (int) db()->query("SELECT COUNT(*) FROM stock_requests WHERE status = 'PENDING'")->fetchColumn();
        respond(['ok' => true, 'count' => $count]);
    }

    if (($data['action'] ?? '') === 'review_stock_request') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['error' => 'คำสั่งนี้ต้องใช้ POST'], 405);
        }
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้นที่ยืนยันคำขอได้'], 403);
        }
        $csrfToken = $data['csrf_token'] ?? '';
        if (!is_string($csrfToken) || !isset($_SESSION['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            respond(['error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'], 403);
        }
        $requestId = filter_var($data['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $decision = $data['decision'] ?? '';
        if ($requestId === false || !in_array($decision, ['approve', 'reject'], true)) {
            respond(['error' => 'คำขอหรือการตัดสินใจไม่ถูกต้อง'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $findRequest = $pdo->prepare(
            "SELECT id, product_id, requester_user_id, requester_username, requester_full_name,
                    request_type, qty, job_ref, note
             FROM stock_requests
             WHERE id = ? AND status = 'PENDING'
             FOR UPDATE"
        );
        $findRequest->execute([$requestId]);
        $request = $findRequest->fetch();
        if (!$request) {
            $pdo->rollBack();
            respond(['error' => 'ไม่พบคำขอที่รออนุมัติ อาจมีผู้จัดการดำเนินการไปแล้ว'], 404);
        }

        if ($decision === 'reject') {
            $review = $pdo->prepare(
                "UPDATE stock_requests
                 SET status = 'REJECTED', reviewer_user_id = ?, reviewer_username = ?, reviewer_full_name = ?, reviewed_at = NOW()
                 WHERE id = ? AND status = 'PENDING'"
            );
            $review->execute([$u['id'], $u['username'], $u['full_name'], $requestId]);
            $reviewText = 'ปฏิเสธคำขอ #' . $requestId . ' ' . $request['request_type'] . ' '
                . number_format((int) $request['qty']) . ' ชิ้น โดย ' . $request['requester_full_name']
                . ' (' . $request['requester_username'] . ')';
            $log = $pdo->prepare(
                "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
                 VALUES (?, ?, ?, 'STOCK_REQUEST_REVIEW', ?)"
            );
            $log->execute([$u['id'], $u['username'], $u['full_name'], $reviewText]);
            $pdo->commit();
            respond(['ok' => true, 'status' => 'REJECTED']);
        }

        $findProduct = $pdo->prepare('SELECT sku, qty, active FROM products WHERE id = ? FOR UPDATE');
        $findProduct->execute([$request['product_id']]);
        $product = $findProduct->fetch();
        if (!$product || (int) $product['active'] !== 1) {
            $pdo->rollBack();
            respond(['error' => 'สินค้านี้ไม่มีอยู่หรือถูกปิดใช้งานแล้ว จึงอนุมัติไม่ได้'], 422);
        }
        $currentQty = (int) $product['qty'];
        $requestQty = (int) $request['qty'];
        if ($request['request_type'] === 'OUT' && $currentQty < $requestQty) {
            $pdo->rollBack();
            respond(['error' => 'สต๊อกปัจจุบันไม่เพียงพอสำหรับคำขอนี้ คงเหลือ ' . number_format($currentQty) . ' ชิ้น'], 422);
        }
        if ($request['request_type'] === 'IN' && $currentQty > 2147483647 - $requestQty) {
            $pdo->rollBack();
            respond(['error' => 'จำนวนคงเหลือเกินขีดจำกัดที่ระบบรองรับ'], 422);
        }
        $balance = $request['request_type'] === 'OUT'
            ? $currentQty - $requestQty
            : ($request['request_type'] === 'IN' ? $currentQty + $requestQty : $requestQty);

        $updateProduct = $pdo->prepare('UPDATE products SET qty = ? WHERE id = ?');
        $updateProduct->execute([$balance, $request['product_id']]);
        $insertTransaction = $pdo->prepare(
            'INSERT INTO transactions (product_id, user_id, user_username, user_full_name, txn_type, qty, balance_after, job_ref, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertTransaction->execute([
            $request['product_id'],
            $request['requester_user_id'],
            $request['requester_username'],
            $request['requester_full_name'],
            $request['request_type'],
            $requestQty,
            $balance,
            $request['job_ref'],
            $request['note'],
        ]);
        $review = $pdo->prepare(
            "UPDATE stock_requests
             SET status = 'APPROVED', reviewer_user_id = ?, reviewer_username = ?, reviewer_full_name = ?, reviewed_at = NOW()
             WHERE id = ? AND status = 'PENDING'"
        );
        $review->execute([$u['id'], $u['username'], $u['full_name'], $requestId]);
        $reviewText = 'อนุมัติคำขอ #' . $requestId . ' ' . $request['request_type'] . ' '
            . number_format($requestQty) . ' ชิ้น · ' . $product['sku'] . ' โดย '
            . $request['requester_full_name'] . ' (' . $request['requester_username'] . ')';
        $log = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
             VALUES (?, ?, ?, 'STOCK_REQUEST_REVIEW', ?)"
        );
        $log->execute([$u['id'], $u['username'], $u['full_name'], $reviewText]);
        $pdo->commit();
        respond(['ok' => true, 'status' => 'APPROVED', 'balance_after' => $balance]);
    }

    if (($data['action'] ?? '') === 'transaction') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['error' => 'คำสั่งนี้ต้องใช้ POST'], 405);
        }
        $csrfToken = $data['csrf_token'] ?? '';
        if (!is_string($csrfToken) || !isset($_SESSION['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            respond(['error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'], 403);
        }
        $pid = (int) ($data['product_id'] ?? 0);
        $qty = filter_var($data['qty'] ?? null, FILTER_VALIDATE_INT);
        $type = $data['type'] ?? 'OUT';
        if (!in_array($type, ['OUT', 'IN', 'ADJUST'], true)) {
            respond(['error' => 'ประเภทไม่ถูกต้อง'], 422);
        }
        if ($pid < 1 || $qty === false || $qty > 2147483647
            || ($type === 'ADJUST' ? $qty < 0 : $qty < 1)) {
            respond(['error' => 'กรุณาเลือกสินค้าและระบุจำนวนที่ถูกต้อง'], 422);
        }
        if ($type === 'ADJUST' && !in_array($u['role'], ['manager', 'stock_staff'], true)) {
            respond(['error' => 'เฉพาะผู้จัดการและพนักงานคลังเท่านั้นที่ปรับยอดคงเหลือได้'], 403);
        }
        if ($type === 'IN' && !in_array($u['role'], ['manager', 'stock_staff'], true)) {
            respond(['error' => 'สิทธิ์นี้ทำได้เฉพาะรายการเบิกออก'], 403);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT id, sku, qty FROM products WHERE id = ? AND active = 1 FOR UPDATE');
        $statement->execute([$pid]);
        $product = $statement->fetch();
        if (!$product) {
            $pdo->rollBack();
            respond(['error' => 'ไม่พบสินค้า'], 404);
        }
        if ($type === 'OUT' && (int) $product['qty'] < $qty) {
            $pdo->rollBack();
            respond(['error' => 'สต๊อกไม่เพียงพอ คงเหลือ ' . $product['qty'] . ' ชิ้น'], 422);
        }

        if ($u['role'] !== 'manager') {
            $insertRequest = $pdo->prepare(
                "INSERT INTO stock_requests
                    (product_id, requester_user_id, requester_username, requester_full_name, request_type, qty, job_ref, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertRequest->execute([
                $pid,
                $u['id'],
                $u['username'],
                $u['full_name'],
                $type,
                $qty,
                trim($data['job_ref'] ?? ''),
                trim($data['note'] ?? ''),
            ]);
            $requestId = (int) $pdo->lastInsertId();
            $requestTypeLabel = $type === 'OUT' ? 'เบิกออก' : ($type === 'IN' ? 'รับเข้า' : 'ปรับยอด');
            $log = $pdo->prepare(
                "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
                 VALUES (?, ?, ?, 'STOCK_REQUEST', ?)"
            );
            $log->execute([
                $u['id'],
                $u['username'],
                $u['full_name'],
                'ส่งคำขอ' . $requestTypeLabel . ' ' . number_format($qty) . ' ชิ้น · '
                    . $product['sku'] . ' รอผู้จัดการอนุมัติ',
            ]);
            $pdo->commit();
            respond(['ok' => true, 'status' => 'PENDING', 'request_id' => $requestId]);
        }

        if ($type === 'OUT') {
            $balance = (int) $product['qty'] - $qty;
        } elseif ($type === 'IN') {
            $balance = (int) $product['qty'] + $qty;
        } else {
            $balance = $qty;
        }
        if ($balance > 2147483647) {
            $pdo->rollBack();
            respond(['error' => 'จำนวนคงเหลือเกินขีดจำกัดที่ระบบรองรับ'], 422);
        }

        $update = $pdo->prepare('UPDATE products SET qty = ? WHERE id = ?');
        $update->execute([$balance, $pid]);
        $insert = $pdo->prepare(
            'INSERT INTO transactions (product_id, user_id, user_username, user_full_name, txn_type, qty, balance_after, job_ref, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $pid,
            $u['id'],
            $u['username'],
            $u['full_name'],
            $type,
            $qty,
            $balance,
            trim($data['job_ref'] ?? ''),
            trim($data['note'] ?? ''),
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        respond(['ok' => true, 'id' => $id, 'balance_after' => $balance]);
    }

    if (($data['action'] ?? '') === 'create_product') {
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้น'], 403);
        }
        $sku = trim($data['sku'] ?? '');
        $size = trim($data['size_mm'] ?? '');
        $length = (float) ($data['length_mm'] ?? 0);
        $grade = trim($data['grade'] ?? '');
        $qty = (int) ($data['qty'] ?? 0);
        $min = (int) ($data['min_qty'] ?? 0);
        if (!$sku || !$size || $length <= 0 || !$grade || $qty < 0 || $min < 0) {
            respond(['error' => 'กรุณากรอก SKU ขนาด ความยาว เกรด และจำนวนให้ถูกต้อง'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'INSERT INTO products (sku, size_mm, diameter_mm, length_mm, grade, standard_name, description, qty, min_qty, location_code)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $sku,
            $size,
            ($data['diameter_mm'] ?? '') !== '' ? (float) $data['diameter_mm'] : null,
            $length,
            $grade,
            trim($data['standard_name'] ?? ''),
            trim($data['description'] ?? ''),
            $qty,
            $min,
            trim($data['location_code'] ?? ''),
        ]);
        $productId = (int) $pdo->lastInsertId();
        if ($qty > 0) {
            $initialStock = $pdo->prepare(
                "INSERT INTO transactions (product_id, user_id, user_username, user_full_name, txn_type, qty, balance_after, note)
                 VALUES (?, ?, ?, ?, 'IN', ?, ?, ?)"
            );
            $initialStock->execute([
                $productId,
                $u['id'],
                $u['username'],
                $u['full_name'],
                $qty,
                $qty,
                'ยอดตั้งต้นตอนเพิ่มสินค้า',
            ]);
        }
        $pdo->commit();
        respond(['ok' => true, 'id' => $productId]);
    }

    if (($data['action'] ?? '') === 'create_user') {
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้น'], 403);
        }

        $username = trim((string) ($data['username'] ?? ''));
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = $data['role'] ?? 'employee';

        if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/D', $username)) {
            respond(['error' => 'ชื่อผู้ใช้ต้องมี 3–80 ตัว และใช้ได้เฉพาะ a-z, 0-9, จุด, ขีดกลาง หรือขีดล่าง'], 422);
        }
        if ($fullName === '' || strlen($fullName) > 640) {
            respond(['error' => 'กรุณาระบุชื่อ-นามสกุลไม่เกิน 160 ตัวอักษร'], 422);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            respond(['error' => 'รหัสผ่านต้องมีความยาว 8–72 ไบต์'], 422);
        }
        if (!in_array($role, ['manager', 'stock_staff', 'employee'], true)) {
            respond(['error' => 'ประเภทบัญชีไม่ถูกต้อง'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $insert = $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
        );
        $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $role]);

        $log = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
             VALUES (?, ?, ?, 'USER_CREATE', ?)"
        );
        $log->execute([
            $u['id'],
            $u['username'],
            $u['full_name'],
            'สร้างบัญชีผู้ใช้ ' . $username . ' (' . $fullName . ')',
        ]);

        $pdo->commit();
        respond(['ok' => true]);
    }

    if (($data['action'] ?? '') === 'change_user_password') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['error' => 'คำสั่งนี้ต้องใช้ POST'], 405);
        }
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้นที่เปลี่ยนรหัสผ่านได้'], 403);
        }
        $csrfToken = $data['csrf_token'] ?? '';
        if (!is_string($csrfToken)) {
            respond(['error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'], 403);
        }
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            respond(['error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'], 403);
        }
        $userIdValue = $data['user_id'] ?? null;
        if (!is_string($userIdValue) && !is_int($userIdValue)) {
            respond(['error' => 'บัญชีผู้ใช้ที่เลือกไม่ถูกต้อง'], 422);
        }
        $userId = filter_var($userIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($userId === false) {
            respond(['error' => 'บัญชีผู้ใช้ที่เลือกไม่ถูกต้อง'], 422);
        }
        $password = $data['password'] ?? null;
        $confirmPassword = $data['confirm_password'] ?? null;
        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
            respond(['error' => 'รหัสผ่านต้องมีความยาว 8–72 ไบต์'], 422);
        }
        if (!is_string($confirmPassword) || !hash_equals($password, $confirmPassword)) {
            respond(['error' => 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $findUser = $pdo->prepare('SELECT username, full_name FROM users WHERE id = ? FOR UPDATE');
        $findUser->execute([$userId]);
        $targetUser = $findUser->fetch();
        if (!$targetUser) {
            $pdo->rollBack();
            respond(['error' => 'ไม่พบบัญชีผู้ใช้'], 404);
        }
        $updatePassword = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updatePassword->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        $log = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
             VALUES (?, ?, ?, 'PASSWORD_CHANGE', ?)"
        );
        $log->execute([
            $u['id'],
            $u['username'],
            $u['full_name'],
            'เปลี่ยนรหัสผ่านบัญชี ' . $targetUser['username'] . ' (' . $targetUser['full_name'] . ')',
        ]);
        $pdo->commit();
        respond(['ok' => true]);
    }

    if (($data['action'] ?? '') === 'delete_user') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['error' => 'คำสั่งนี้ต้องใช้ POST'], 405);
        }
        if ($u['role'] !== 'manager') {
            respond(['error' => 'เฉพาะผู้จัดการเท่านั้นที่ลบบัญชีผู้ใช้ได้'], 403);
        }
        $csrfToken = (string) ($data['csrf_token'] ?? '');
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            respond(['error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'], 403);
        }
        $userId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($userId === false) {
            respond(['error' => 'บัญชีผู้ใช้ที่เลือกไม่ถูกต้อง'], 422);
        }
        if ($userId === (int) $u['id']) {
            respond(['error' => 'ไม่สามารถลบบัญชีของตัวเองได้'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $findUser = $pdo->prepare('SELECT id, username, full_name, role FROM users WHERE id = ? FOR UPDATE');
        $findUser->execute([$userId]);
        $targetUser = $findUser->fetch();
        if (!$targetUser) {
            $pdo->rollBack();
            respond(['error' => 'ไม่พบบัญชีผู้ใช้ที่ต้องการลบ'], 404);
        }
        if ($targetUser['role'] === 'manager') {
            $otherManagers = $pdo->prepare(
                "SELECT COUNT(*) FROM users WHERE role = 'manager' AND active = 1 AND id <> ?"
            );
            $otherManagers->execute([$userId]);
            if ((int) $otherManagers->fetchColumn() === 0) {
                $pdo->rollBack();
                respond(['error' => 'ไม่สามารถลบผู้จัดการคนสุดท้ายที่ยังใช้งานได้'], 422);
            }
        }

        $delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $delete->execute([$userId]);
        $log = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, username, full_name, event_type, details)
             VALUES (?, ?, ?, 'USER_DELETE', ?)"
        );
        $log->execute([
            $u['id'],
            $u['username'],
            $u['full_name'],
            'ลบบัญชีผู้ใช้ ' . $targetUser['username'] . ' (' . $targetUser['full_name'] . ')',
        ]);
        $pdo->commit();
        respond(['ok' => true]);
    }

    respond(['error' => 'ไม่รู้จักคำสั่ง'], 400);
} catch (PDOException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((string) $e->getCode() === '23000') {
        if (($data['action'] ?? '') === 'create_user') {
            respond(['error' => 'ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น'], 409);
        }
        if (($data['action'] ?? '') === 'delete_user') {
            respond(['error' => 'ไม่สามารถลบบัญชีได้ เนื่องจากยังมีข้อมูลที่อ้างอิงบัญชีนี้อยู่'], 409);
        }
        respond(['error' => 'SKU นี้มีอยู่แล้ว หรือพบ SKU ซ้ำในไฟล์ ระบบยกเลิกรายการทั้งหมด'], 409);
    }
    error_log($e->getMessage());
    respond(['error' => 'เกิดข้อผิดพลาดในฐานข้อมูล'], 500);
} catch (RuntimeException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    respond(['error' => 'เกิดข้อผิดพลาดในระบบ'], 500);
}
