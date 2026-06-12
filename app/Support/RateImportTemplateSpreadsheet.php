<?php

namespace App\Support;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use ZipArchive;

class RateImportTemplateSpreadsheet
{
    /**
     * Create a supplier-specific XLSX template.
     */
    public function create(Supplier $supplier): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rate-template-');
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $rows = [
            ['', 'OUTLET CAR RENTAL'],
            [],
            ['Nombre:', $supplier->name],
            ['Número:', $supplier->code],
            [],
            RateImportSpreadsheet::REQUIRED_HEADERS,
            ['CUN', 'SUV', 'IFAR', 'STD', 'MXN', '1250.00', '2026-07-01', '2026-07-31'],
            ['CUN', 'COMPACT', 'CDAR', 'WEEKEND', 'MXN', '890.00', '2026-07-01', '2026-07-31'],
        ];

        $strings = collect($rows)->flatten()->map(fn (?string $value): string => (string) $value)->values();

        $this->addContentTypes($archive);
        $this->addRelationships($archive);
        $this->addWorkbook($archive);
        $this->addStyles($archive);
        $this->addSharedStrings($archive, $strings);
        $this->addSheet($archive, $rows, $strings);
        $this->addLogo($archive);

        $archive->close();

        return $path;
    }

    protected function addContentTypes(ZipArchive $archive): void
    {
        $archive->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
</Types>
XML);
    }

    protected function addRelationships(ZipArchive $archive): void
    {
        $archive->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);

        $archive->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML);

        $archive->addFromString('xl/worksheets/_rels/sheet1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>
XML);

        $archive->addFromString('xl/drawings/_rels/drawing1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/outlet-logo.png"/>
</Relationships>
XML);
    }

    protected function addWorkbook(ZipArchive $archive): void
    {
        $archive->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Precios" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);
    }

    protected function addStyles(ZipArchive $archive): void
    {
        $archive->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="18"/><color rgb="FF111827"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="4">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFill="1"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left"/></xf>
  </cellXfs>
</styleSheet>
XML);
    }

    /**
     * @param  Collection<int, string>  $strings
     */
    protected function addSharedStrings(ZipArchive $archive, $strings): void
    {
        $sharedStrings = $strings
            ->map(fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>')
            ->implode('');

        $archive->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$strings->count().'" uniqueCount="'.$strings->count().'">'.$sharedStrings.'</sst>');
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  Collection<int, string>  $strings
     */
    protected function addSheet(ZipArchive $archive, array $rows, $strings): void
    {
        $index = 0;
        $sheetRows = collect($rows)
            ->map(function (array $row, int $rowIndex) use (&$index): string {
                $cells = collect($row)
                    ->map(function (?string $value, int $cellIndex) use (&$index, $rowIndex): string {
                        $reference = chr(65 + $cellIndex).($rowIndex + 1);
                        $style = match (true) {
                            $rowIndex === 0 && $cellIndex === 1 => ' s="1"',
                            $rowIndex === 5 => ' s="2"',
                            default => ' s="3"',
                        };

                        return '<c r="'.$reference.'" t="s"'.$style.'><v>'.($index++).'</v></c>';
                    })
                    ->implode('');

                return '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
            })
            ->implode('');

        $archive->addFromString('xl/worksheets/sheet1.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <cols>
    <col min="1" max="1" width="18" customWidth="1"/>
    <col min="2" max="8" width="20" customWidth="1"/>
  </cols>
  <sheetData>{$sheetRows}</sheetData>
  <drawing r:id="rId1"/>
</worksheet>
XML);
    }

    protected function addLogo(ZipArchive $archive): void
    {
        $logoPath = public_path('favicon_outlet.png');

        if (is_file($logoPath)) {
            $archive->addFile($logoPath, 'xl/media/outlet-logo.png');
        }

        $archive->addFromString('xl/drawings/drawing1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <xdr:twoCellAnchor>
    <xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>
    <xdr:to><xdr:col>1</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>4</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>
    <xdr:pic>
      <xdr:nvPicPr><xdr:cNvPr id="1" name="Outlet Logo"/><xdr:cNvPicPr/></xdr:nvPicPr>
      <xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>
      <xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>
    </xdr:pic>
    <xdr:clientData/>
  </xdr:twoCellAnchor>
</xdr:wsDr>
XML);
    }
}
