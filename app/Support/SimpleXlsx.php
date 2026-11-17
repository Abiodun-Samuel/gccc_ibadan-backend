<?php

namespace App\Support;

use DateTimeInterface;

/**
 * A small, dependency-free .xlsx writer: typed columns, a bold frozen header
 * and fixed column widths, enough for an Excel export that opens cleanly.
 *
 * Text is written as inline strings, so nothing a member typed can ever run
 * as a formula, and phone numbers keep their leading zero. The package is a
 * plain (stored) zip, so PHP's zip extension isn't needed.
 *
 *   $xlsx = (new SimpleXlsx())
 *       ->addSheet('People', [['title' => 'Name', 'type' => 'text', 'width' => 24]], [['Ada']])
 *       ->toString();
 *
 * Column types: text, integer, money (₦), date (DateTimeInterface). A row is a
 * list of values in column order, or ['values' => [...], 'bold' => true].
 */
final class SimpleXlsx
{
    // cellXfs indexes in styles(): [normal, bold] per column type.
    private const STYLE = [
        'header'  => [1, 1],
        'general' => [0, 6],
        'text'    => [2, 6],
        'money'   => [3, 7],
        'date'    => [4, 4],
        'integer' => [5, 8],
    ];

    /** @var array<int, array{name: string, xml: string}> */
    private array $sheets = [];

    /**
     * @param array<int, array{title: string, type?: string, width?: int|float}> $columns
     * @param iterable<array> $rows
     */
    public function addSheet(string $name, array $columns, iterable $rows): self
    {
        $this->sheets[] = ['name' => $this->sheetName($name), 'xml' => $this->sheetXml($columns, $rows)];

        return $this;
    }

    public function toString(): string
    {
        $files = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRels(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(),
            'xl/styles.xml' => $this->styles(),
        ];
        foreach ($this->sheets as $i => $sheet) {
            $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $sheet['xml'];
        }

        return self::zip($files);
    }

    // ─── Sheets ──────────────────────────────────────────────────────────────

    private function sheetXml(array $columns, iterable $rows): string
    {
        $cols = '';
        foreach ($columns as $i => $column) {
            $n = $i + 1;
            $width = $column['width'] ?? 14;
            $cols .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$width}\" customWidth=\"1\"/>";
        }

        $header = '';
        foreach ($columns as $i => $column) {
            $header .= $this->cell(self::ref($i, 1), $column['title'], 'header', false);
        }
        $data = "<row r=\"1\">{$header}</row>";

        $r = 1;
        foreach ($rows as $row) {
            $r++;
            $bold = isset($row['values']) && !empty($row['bold']);
            $values = $row['values'] ?? $row;
            $cells = '';
            foreach (array_values($values) as $i => $value) {
                $cells .= $this->cell(self::ref($i, $r), $value, $columns[$i]['type'] ?? 'general', $bold);
            }
            $data .= "<row r=\"{$r}\">{$cells}</row>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . "<cols>{$cols}</cols>"
            . "<sheetData>{$data}</sheetData>"
            . '</worksheet>';
    }

    private function cell(string $ref, mixed $value, string $type, bool $bold): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        [$normal, $boldStyle] = self::STYLE[$type] ?? self::STYLE['general'];
        $style = $bold ? $boldStyle : $normal;

        if ($value instanceof DateTimeInterface) {
            // Excel counts days from 1899-12-30, in the time the sheet shows.
            $serial = ($value->getTimestamp() + $value->getOffset()) / 86400 + 25569;

            return "<c r=\"{$ref}\" s=\"{$style}\"><v>{$serial}</v></c>";
        }
        if (in_array($type, ['money', 'integer', 'general'], true) && (is_int($value) || is_float($value))) {
            return "<c r=\"{$ref}\" s=\"{$style}\"><v>{$value}</v></c>";
        }

        // Everything else, numbers in text columns included, is text.
        if ($type !== 'header') {
            $style = $bold ? self::STYLE['text'][1] : self::STYLE['text'][0];
        }

        return "<c r=\"{$ref}\" t=\"inlineStr\" s=\"{$style}\"><is><t xml:space=\"preserve\">" . self::escape((string) $value) . '</t></is></c>';
    }

    private static function ref(int $index, int $row): string
    {
        $letters = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26) . $letters;
        }

        return $letters . $row;
    }

    private static function escape(string $text): string
    {
        // XML 1.0 can't carry most control characters at all.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sheetName(string $name): string
    {
        $name = trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name) ?? 'Sheet');

        return mb_substr($name !== '' ? $name : 'Sheet' . (count($this->sheets) + 1), 0, 31);
    }

    // ─── Package parts ───────────────────────────────────────────────────────

    private function contentTypes(): string
    {
        $sheets = '';
        foreach (array_keys($this->sheets) as $i) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $sheets
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $sheets .= '<sheet name="' . self::escape($sheet['name']) . "\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . "<sheets>{$sheets}</sheets>"
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach (array_keys($this->sheets) as $i) {
            $n = $i + 1;
            $rels .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
        }
        $stylesId = count($this->sheets) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . "<Relationship Id=\"rId{$stylesId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>"
            . '</Relationships>';
    }

    private function styles(): string
    {
        $xf = fn(int $numFmt, int $font, int $fill = 0) => "<xf numFmtId=\"{$numFmt}\" fontId=\"{$font}\" fillId=\"{$fill}\" borderId=\"0\" xfId=\"0\""
            . ($numFmt ? ' applyNumberFormat="1"' : '') . ($font ? ' applyFont="1"' : '') . ($fill ? ' applyFill="1"' : '') . '/>';

        $cellXfs = [
            $xf(0, 0),       // 0 general
            $xf(0, 1, 2),    // 1 header: bold on pale blue
            $xf(49, 0),      // 2 text (@)
            $xf(164, 0),     // 3 money
            $xf(165, 0),     // 4 date and time
            $xf(1, 0),       // 5 integer
            $xf(0, 1),       // 6 bold text
            $xf(164, 1),     // 7 bold money
            $xf(1, 1),       // 8 bold integer
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;₦&quot;#,##0.00"/><numFmt numFmtId="165" formatCode="dd mmm yyyy hh:mm"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDFF1FC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($cellXfs) . '">' . implode('', $cellXfs) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    // ─── Zip (stored, no compression) ────────────────────────────────────────

    /** @param array<string, string> $files path => contents */
    private static function zip(array $files): string
    {
        $now = getdate();
        $time = ($now['hours'] << 11) | ($now['minutes'] << 5) | intdiv($now['seconds'], 2);
        $date = (max($now['year'] - 1980, 0) << 9) | ($now['mon'] << 5) | $now['mday'];
        $utf8Names = 0x0800;

        $body = '';
        $directory = '';
        foreach ($files as $name => $contents) {
            $offset = strlen($body);
            $crc = crc32($contents);
            $size = strlen($contents);
            $nameLength = strlen($name);

            $body .= pack('VvvvvvVVVvv', 0x04034B50, 20, $utf8Names, 0, $time, $date, $crc, $size, $size, $nameLength, 0)
                . $name . $contents;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, $utf8Names, 0, $time, $date, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset)
                . $name;
        }

        $count = count($files);

        return $body . $directory . pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, strlen($directory), strlen($body), 0);
    }
}
