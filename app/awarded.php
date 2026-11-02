<?php

function awardedHeaderKey(string $header): string
{
    $header = strtolower(trim($header));
    return preg_replace('/\s+/u', '', $header) ?? $header;
}

function awardedCatalogSku(string $partNumber, string $jde): string
{
    $partNumber = trim($partNumber);
    $jde = trim($jde);
    if ($partNumber !== '' && strcasecmp($partNumber, 'SEE ITEM NOTE') !== 0) {
        return $partNumber;
    }
    return $jde;
}

function awardedInchValue(string $value): ?float
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $value, $match)) {
        return (float) $match[1] + ((float) $match[2] / (float) $match[3]);
    }
    if (preg_match('/^(\d+)-(\d+)\/(\d+)$/', $value, $match)) {
        return (float) $match[1] + ((float) $match[2] / (float) $match[3]);
    }
    if (preg_match('/^(\d+)\/(\d+)$/', $value, $match)) {
        return (float) $match[1] / (float) $match[2];
    }
    if (is_numeric($value)) {
        return (float) $value;
    }
    return null;
}

function awardedParseSize(string $size): array
{
    if (!preg_match('/DIA\s*(.+?)\s*IN\s*X\s*(.+?)\s*IN/i', $size, $match)) {
        return [null, null];
    }
    $diameterIn = awardedInchValue($match[1]);
    $lengthIn = awardedInchValue($match[2]);
    if ($diameterIn === null || $lengthIn === null || $diameterIn < 0 || $lengthIn <= 0) {
        return [null, null];
    }
    return [round($diameterIn * 25.4, 2), round($lengthIn * 25.4, 2)];
}

function awardedClip(string $value, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function awardedCells(array $row, int $width = 22): array
{
    $cells = $row['cells'] ?? $row;
    $padded = array_fill(0, $width, '');
    foreach ($cells as $index => $value) {
        if (!is_int($index) && !ctype_digit((string) $index)) {
            continue;
        }
        $index = (int) $index;
        if ($index >= 0 && $index < $width) {
            $padded[$index] = trim((string) ($value ?? ''));
        }
    }
    return $padded;
}

function awardedIsMasterHeader(array $cells): bool
{
    $keys = array_map('awardedHeaderKey', $cells);
    return ($keys[0] ?? '') === 'no.'
        && in_array('partnumber', $keys, true)
        && in_array('size', $keys, true)
        && in_array('studmaterial', $keys, true);
}

function awardedHeaderIndexes(array $cells): array
{
    $indexes = [];
    foreach ($cells as $index => $header) {
        $key = awardedHeaderKey((string) $header);
        if ($key !== '' && !isset($indexes[$key])) {
            $indexes[$key] = (int) $index;
        }
    }
    return $indexes;
}

function awardedCellAt(array $cells, array $indexes, string $key): string
{
    if (!isset($indexes[$key])) {
        return '';
    }
    return trim((string) ($cells[$indexes[$key]] ?? ''));
}

function awardedToProductImportRows(array $rows): array
{
    if ($rows === []) {
        return $rows;
    }

    $header = awardedCells($rows[0]);
    $indexes = awardedHeaderIndexes($header);
    $converted = [[
        'line' => 1,
        'cells' => ['sku', 'size_mm', 'diameter_mm', 'length_mm', 'grade', 'standard_name', 'description', 'qty', 'min_qty', 'location_code'],
    ]];

    foreach (array_slice($rows, 1) as $row) {
        $cells = awardedCells($row);
        if (count(array_filter($cells, static fn($value) => $value !== '')) === 0) {
            continue;
        }

        $partNumber = awardedCellAt($cells, $indexes, 'partnumber');
        $jde = awardedCellAt($cells, $indexes, 'jde');
        $sku = awardedCatalogSku($partNumber, $jde);
        if ($sku === '') {
            continue;
        }

        $size = awardedCellAt($cells, $indexes, 'size');
        [$diameterMm, $lengthMm] = awardedParseSize($size);
        $grade = awardedCellAt($cells, $indexes, 'studmaterial');
        if ($grade === '') {
            $grade = awardedCellAt($cells, $indexes, 'coating');
        }
        if ($grade === '') {
            $grade = 'UNSPECIFIED';
        }

        $name = awardedCellAt($cells, $indexes, 'awardedlineitemname');
        $description = $jde !== '' && strcasecmp($sku, $jde) !== 0
            ? 'JDE ' . $jde . ' · ' . $name
            : $name;

        $converted[] = [
            'line' => (int) ($row['line'] ?? count($converted) + 1),
            'cells' => [
                $sku,
                $size !== '' ? awardedClip($size, 30) : $sku,
                $diameterMm === null ? '' : (string) $diameterMm,
                $lengthMm === null ? '' : (string) $lengthMm,
                awardedClip($grade, 80),
                awardedClip(awardedCellAt($cells, $indexes, 'thread'), 80),
                awardedClip($description, 500),
                '0',
                '0',
                awardedClip(awardedCellAt($cells, $indexes, 'lotname'), 80),
            ],
        ];
    }

    return $converted;
}

function loadAwardedItems(): array
{
    $file = fopen(__DIR__ . '/data/awarded_items.csv', 'rb');
    if ($file === false) {
        throw new RuntimeException('เปิดรายการ Excel Awarded items ไม่สำเร็จ');
    }

    try {
        $headers = fgetcsv($file, null, ',', '"', '');
        if ($headers === false || count($headers) !== 22 || $headers[0] !== 'No.') {
            throw new RuntimeException('รูปแบบรายการ Excel Awarded items ไม่ถูกต้อง');
        }

        $headers[18] = 'สั่งรวมล็อตที่ 1 + 2' . "\nPO-KT 2608055 + KT2610064";
        array_splice($headers, 19, 2);
        $items = [];
        while (($row = fgetcsv($file, null, ',', '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            if (count($row) !== 22 || trim((string) $row[0]) === '') {
                throw new RuntimeException('พบแถวข้อมูล Awarded items ที่ไม่สมบูรณ์');
            }

            $lotOne = trim((string) $row[18]);
            $lotTwo = trim((string) $row[19]);
            $lotTotal = 0.0;
            $hasQuantity = false;
            $lotNotes = [];
            foreach ([$lotOne, $lotTwo] as $lotValue) {
                if ($lotValue === '') {
                    continue;
                }
                if (is_numeric($lotValue)) {
                    $lotTotal += (float) $lotValue;
                    $hasQuantity = true;
                } else {
                    $lotNotes[] = $lotValue;
                }
            }

            $combinedLot = $hasQuantity ? number_format($lotTotal, 0, '.', '') : '';
            if ($lotNotes) {
                $combinedLot .= ($combinedLot !== '' ? ' · ' : '') . implode(' · ', $lotNotes);
            }
            $row[18] = $combinedLot;
            array_splice($row, 19, 2);
            $items[] = array_map(static fn($value): string => (string) ($value ?? ''), $row);
        }

        return [$headers, $items];
    } finally {
        fclose($file);
    }
}
