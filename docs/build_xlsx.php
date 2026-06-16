<?php

/**
 * Generates docs/plantilla-precios-ALAMO.xlsx with 10 sample rates.
 * Run: php docs/build_xlsx.php
 */
$outputPath = __DIR__.'/plantilla-precios-ALAMO-new.xlsx';

$rates = [
    ['CUN', 'ECONOMY',      'ECAR', 'STD',     'MXN',  '620.00',  '2026-07-01', '2026-07-31'],
    ['CUN', 'COMPACT',      'CDAR', 'STD',     'MXN',  '850.00',  '2026-07-01', '2026-07-31'],
    ['CUN', 'COMPACT',      'CDAR', 'WEEKEND', 'MXN',  '890.00',  '2026-07-01', '2026-07-31'],
    ['CUN', 'INTERMEDIATE', 'IDAR', 'STD',     'MXN', '1050.00',  '2026-07-01', '2026-07-31'],
    ['CUN', 'SUV',          'IFAR', 'STD',     'MXN', '1250.00',  '2026-07-01', '2026-07-31'],
    ['CUN', 'SUV',          'IFAR', 'WEEKLY',  'MXN', '6800.00',  '2026-07-01', '2026-07-31'],
    ['MEX', 'ECONOMY',      'ECAR', 'STD',     'MXN',  '580.00',  '2026-07-01', '2026-07-31'],
    ['MEX', 'COMPACT',      'CDAR', 'STD',     'MXN',  '780.00',  '2026-07-01', '2026-07-31'],
    ['MEX', 'STANDARD',     'SDAR', 'STD',     'MXN', '1100.00',  '2026-07-01', '2026-07-31'],
    ['GDL', 'MINIVAN',      'MVAR', 'STD',     'MXN', '1800.00',  '2026-07-01', '2026-07-31'],
];

// --- Build shared strings (all text cells) ---
$strings = [];
$addStr = function (string $s) use (&$strings): int {
    $idx = array_search($s, $strings, true);
    if ($idx === false) {
        $strings[] = $s;
        $idx = count($strings) - 1;
    }

    return $idx;
};

// Header meta strings
$addStr('OUTLET CAR RENTAL');
$addStr('Nombre:');
$addStr('ALAMO CAR RENTAL');
$addStr('Número:');
$addStr('ALAMO');

// Column headers
$headers = ['office_code', 'vehicle_class', 'acriss_code', 'rate_plan_code', 'currency', 'base_price', 'valid_from', 'valid_to'];
foreach ($headers as $h) {
    $addStr($h);
}

// Rate string cells (text columns: office, class, acriss, plan, currency, dates)
foreach ($rates as $row) {
    $addStr($row[0]); // office_code
    $addStr($row[1]); // vehicle_class
    $addStr($row[2]); // acriss_code
    $addStr($row[3]); // rate_plan_code
    $addStr($row[4]); // currency
    $addStr($row[7]); // valid_to  (dates stored as strings for simplicity)
    $addStr($row[6]); // valid_from
}

// --- Shared strings XML ---
$ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
$ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
foreach ($strings as $s) {
    $ssXml .= '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>';
}
$ssXml .= '</sst>';

// --- Helper: build a shared-string cell ---
$s = function (string $col, int $row, string $val) use ($strings): string {
    $idx = array_search($val, $strings, true);

    return "<c r=\"{$col}{$row}\" t=\"s\"><v>{$idx}</v></c>";
};

// --- Helper: build a numeric cell ---
$n = function (string $col, int $row, string $val): string {
    return "<c r=\"{$col}{$row}\"><v>{$val}</v></c>";
};

// --- Build sheet rows ---
$sheetRows = '';

// Row 1: B1 = "OUTLET CAR RENTAL"
$sheetRows .= '<row r="1"><c r="B1" t="s"><v>'.array_search('OUTLET CAR RENTAL', $strings, true).'</v></c></row>';

// Row 2: blank

// Row 3: A3 = "Nombre:", B3 = "ALAMO CAR RENTAL"
$sheetRows .= '<row r="3">'
    .'<c r="A3" t="s"><v>'.array_search('Nombre:', $strings, true).'</v></c>'
    .'<c r="B3" t="s"><v>'.array_search('ALAMO CAR RENTAL', $strings, true).'</v></c>'
    .'</row>';

// Row 4: A4 = "Número:", B4 = "ALAMO"
$sheetRows .= '<row r="4">'
    .'<c r="A4" t="s"><v>'.array_search('Número:', $strings, true).'</v></c>'
    .'<c r="B4" t="s"><v>'.array_search('ALAMO', $strings, true).'</v></c>'
    .'</row>';

// Row 5: blank

// Row 6: column headers
$cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
$headerRow = '<row r="6">';
foreach ($headers as $i => $h) {
    $headerRow .= '<c r="'.$cols[$i].'6" t="s"><v>'.array_search($h, $strings, true).'</v></c>';
}
$headerRow .= '</row>';
$sheetRows .= $headerRow;

// Rows 7–16: data
foreach ($rates as $i => $row) {
    $r = $i + 7;
    $sheetRows .= '<row r="'.$r.'">'
        .'<c r="A'.$r.'" t="s"><v>'.array_search($row[0], $strings, true).'</v></c>'
        .'<c r="B'.$r.'" t="s"><v>'.array_search($row[1], $strings, true).'</v></c>'
        .'<c r="C'.$r.'" t="s"><v>'.array_search($row[2], $strings, true).'</v></c>'
        .'<c r="D'.$r.'" t="s"><v>'.array_search($row[3], $strings, true).'</v></c>'
        .'<c r="E'.$r.'" t="s"><v>'.array_search($row[4], $strings, true).'</v></c>'
        .'<c r="F'.$r.'"><v>'.$row[5].'</v></c>'
        .'<c r="G'.$r.'" t="s"><v>'.array_search($row[6], $strings, true).'</v></c>'
        .'<c r="H'.$r.'" t="s"><v>'.array_search($row[7], $strings, true).'</v></c>'
        .'</row>';
}

// --- Sheet XML ---
$sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
    .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    .'<sheetData>'.$sheetRows.'</sheetData>'
    .'</worksheet>';

// --- Workbook XML ---
$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
    .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    .'<sheets><sheet name="Precios" sheetId="1" r:id="rId1"/></sheets>'
    .'</workbook>';

// --- Minimal styles XML ---
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
    .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    .'<fonts><font><sz val="11"/></font></fonts>'
    .'<fills><fill><patternFill patternType="none"/></fill></fills>'
    .'<borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
    .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
    .'</styleSheet>';

// --- Content Types ---
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
    .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    .'<Default Extension="xml" ContentType="application/xml"/>'
    .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
    .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    .'</Types>';

// --- Root .rels ---
$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    .'</Relationships>';

// --- Workbook .rels ---
$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
    .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    .'</Relationships>';

// --- Write ZIP ---
$zip = new ZipArchive;
$zip->open($outputPath, ZipArchive::CREATE);
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->addFromString('xl/sharedStrings.xml', $ssXml);
$zip->addFromString('xl/styles.xml', $stylesXml);
$zip->close();

echo "Generated: {$outputPath}\n";
