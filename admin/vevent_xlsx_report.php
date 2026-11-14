<?php

declare(strict_types=1);

final class VeventAttendanceXlsx
{
    /** @var array<string,string> */
    private array $files = [];

    private static function xml($value): string
    {
        $s = (string) ($value ?? '');
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function cell(string $column, int $row, array $cell): string
    {
        $ref = $column . $row;
        $style = (int) ($cell['style'] ?? 0);
        if (!array_key_exists('value', $cell) || $cell['value'] === null) {
            return '<c r="' . $ref . '" s="' . $style . '"/>';
        }
        if (($cell['type'] ?? 'text') === 'number') {
            $num = (float) $cell['value'];
            if (!is_finite($num)) {
                return '<c r="' . $ref . '" s="' . $style . '"/>';
            }
            return '<c r="' . $ref . '" s="' . $style . '"><v>' .
                rtrim(rtrim(sprintf('%.12F', $num), '0'), '.') . '</v></c>';
        }
        // Inline strings không bị Excel thực thi như công thức (kể cả dữ liệu bắt đầu bằng =, +, @).
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' .
            self::xml($cell['value']) . '</t></is></c>';
    }

    private static function put(array &$rows, string $col, int $row, $value, int $style, string $type = 'text'): void
    {
        $rows[$row][$col] = ['value' => $value, 'style' => $style, 'type' => $type];
    }

    private static function paint(array &$rows, array $cols, int $first, int $last, int $style): void
    {
        for ($r = $first; $r <= $last; $r++) {
            foreach ($cols as $col) {
                $rows[$r][$col] = ['style' => $style];
            }
        }
    }

    private static function sheet(array $rows, array $widths, array $merges, int $freezeRows = 0,
                                  ?string $autoFilter = null, bool $landscape = false): string
    {
        ksort($rows, SORT_NUMERIC);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $lastColumn = array_key_last($widths);
        $lastRow = $rows ? max(array_keys($rows)) : 1;
        $xml .= '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>';
        if ($freezeRows > 0) {
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $freezeRows
                . '" topLeftCell="A' . ($freezeRows + 1)
                . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        } else {
            $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        }
        $xml .= '<sheetFormatPr defaultRowHeight="19"/><cols>';
        $i = 0;
        foreach ($widths as $width) {
            $i++;
            $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . (float) $width . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        foreach ($rows as $n => $row) {
            $height = $n <= 3 ? 28 : ($n === 8 || $n === 9 ? 27 : 23);
            $xml .= '<row r="' . $n . '" ht="' . $height . '" customHeight="1">';
            foreach (array_keys($widths) as $col) {
                if (isset($row[$col])) {
                    $xml .= self::cell($col, $n, $row[$col]);
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($autoFilter !== null) {
            $xml .= '<autoFilter ref="' . self::xml($autoFilter) . '"/>';
        }
        if ($merges) {
            $xml .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $ref) {
                $xml .= '<mergeCell ref="' . self::xml($ref) . '"/>';
            }
            $xml .= '</mergeCells>';
        }
        $xml .= '<printOptions horizontalCentered="1"/>';
        $xml .= '<pageMargins left="0.28" right="0.28" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>';
        $xml .= '<pageSetup orientation="' . ($landscape ? 'landscape' : 'portrait')
            . '" paperSize="9" fitToWidth="1"/>';
        return $xml . '</worksheet>';
    }

    private static function styles(): string
    {
        // Chỉ dùng các phông phổ biến để WPS Office và Microsoft Excel đọc nhất quán.
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm:ss"/><numFmt numFmtId="165" formatCode="0.0%"/></numFmts>
<fonts count="10">
 <font><sz val="11"/><name val="Calibri"/><color rgb="FF263448"/></font>
 <font><b/><sz val="18"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
 <font><b/><sz val="12"/><name val="Calibri"/><color rgb="FFB8FFF0"/></font>
 <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF385B6A"/></font>
 <font><b/><sz val="24"/><name val="Calibri"/><color rgb="FF142A45"/></font>
 <font><b/><sz val="24"/><name val="Calibri"/><color rgb="FF11876B"/></font>
 <font><b/><sz val="24"/><name val="Calibri"/><color rgb="FFB77914"/></font>
 <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
 <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF137A59"/></font>
 <font><i/><sz val="10"/><name val="Calibri"/><color rgb="FF64748B"/></font>
</fonts>
<fills count="7"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>
 <fill><patternFill patternType="solid"><fgColor rgb="FF111827"/><bgColor indexed="64"/></patternFill></fill>
 <fill><patternFill patternType="solid"><fgColor rgb="FFEAF6FA"/><bgColor indexed="64"/></patternFill></fill>
 <fill><patternFill patternType="solid"><fgColor rgb="FFE4F7EE"/><bgColor indexed="64"/></patternFill></fill>
 <fill><patternFill patternType="solid"><fgColor rgb="FFFFF2DB"/><bgColor indexed="64"/></patternFill></fill>
 <fill><patternFill patternType="solid"><fgColor rgb="FFF4F7FB"/><bgColor indexed="64"/></patternFill></fill>
</fills>
<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD8E2E9"/></left><right style="thin"><color rgb="FFD8E2E9"/></right><top style="thin"><color rgb="FFD8E2E9"/></top><bottom style="thin"><color rgb="FFD8E2E9"/></bottom><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="18">
 <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
 <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>
 <xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>
 <xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>
 <xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
 <xf numFmtId="0" fontId="5" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
 <xf numFmtId="0" fontId="6" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
 <xf numFmtId="165" fontId="5" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
 <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"/>
 <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
 <xf numFmtId="0" fontId="7" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
 <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
 <xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
 <xf numFmtId="0" fontId="8" fillId="0" borderId="1" xfId="0"/>
 <xf numFmtId="0" fontId="8" fillId="6" borderId="1" xfId="0"/>
 <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"/>
 <xf numFmtId="164" fontId="0" fillId="6" borderId="1" xfId="0" applyNumberFormat="1"/>
 <xf numFmtId="0" fontId="9" fillId="0" borderId="0" xfId="0"/>
</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML;
    }

    private static function excelDate($input): ?float
    {
        $str = trim((string) ($input ?? ''));
        if ($str === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $str, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d H:i:s') !== $str) {
            return null;
        }
        // Số ngày kể từ 30/12/1899; dữ liệu ngày giờ được lưu theo giờ VN trong DB.
        return ($date->getTimestamp() / 86400) + 25569;
    }

    public static function create(array $event, array $registrations, int $eventId,
                                  int $total, int $attended, int $notAttended,
                                  float $ratio, string $path): void
    {
        $eventName = (string) ($event['ten_sukien'] ?? 'Sự kiện chưa có tên');
        $venue = (string) ($event['ten_dia_diem'] ?? 'Chưa cập nhật');
        if ($venue === '') {
            $venue = 'Chưa cập nhật';
        }
        $startTime = (string) ($event['thoi_gian_bat_dau'] ?? 'Chưa cập nhật');
        $all = ['A','B','C','D','E','F','G','H'];
        $summary = [];
        $merge = ['A1:H2','A3:H3','A5:B5','C5:D5','E5:F5','G5:H5','A6:B6','C6:H6',
            'A8:B8','C8:D8','E8:F8','G8:H8','A9:B10','C9:D10','E9:F10','G9:H10',
            'A12:H12','A13:H13','A14:H14','A16:H16'];
        self::paint($summary, $all, 1, 3, 1);
        self::put($summary, 'A', 1, 'HỆ THỐNG QUẢN LÝ SỰ KIỆN SINH VIÊN | BÁO CÁO ĐIỂM DANH', 1);
        self::put($summary, 'A', 3, $eventName, 2);
        foreach ([['A','B'],['C','D'],['E','F'],['G','H']] as $pair) {
            self::paint($summary, $pair, 5, 5, 3);
        }
        self::put($summary, 'A', 5, 'MÃ SỰ KIỆN', 3);
        self::put($summary, 'C', 5, (string) $eventId, 3);
        self::put($summary, 'E', 5, 'BẮT ĐẦU', 3);
        self::put($summary, 'G', 5, $startTime, 3);
        self::paint($summary, $all, 6, 6, 3);
        self::put($summary, 'A', 6, 'ĐỊA ĐIỂM', 3);
        self::put($summary, 'C', 6, $venue, 3);
        foreach ([['A','B','TỔNG ĐĂNG KÝ', $total, 4],
                  ['C','D','ĐÃ ĐIỂM DANH', $attended, 5],
                  ['E','F','CHƯA ĐIỂM DANH', $notAttended, 6],
                  ['G','H','TỶ LỆ THAM GIA', $ratio, 7]] as $group) {
            [$left, $right, $label, $value, $valueStyle] = $group;
            self::paint($summary, [$left,$right], 8, 8, 3);
            self::put($summary, $left, 8, $label, 3);
            self::paint($summary, [$left,$right], 9, 10, $valueStyle);
            self::put($summary, $left, 9, $value, $valueStyle, 'number');
        }
        self::put($summary, 'A', 12, 'GHI CHÚ BÁO CÁO', 8);
        self::put($summary, 'A', 13, 'Thống kê theo mã sinh viên (MSSV) duy nhất; danh sách xuất từng bản ghi đăng ký.', 17);
        self::put($summary, 'A', 14, 'Dấu "---" nghĩa là lượt điểm danh cũ chưa ghi nhận được thời gian chính xác.', 17);
        self::put($summary, 'A', 16, 'Thời điểm xuất báo cáo (giờ Việt Nam): ' . date('d/m/Y H:i:s'), 17);
        $sheet1 = self::sheet($summary, array_fill_keys($all, 15), $merge, 0, null, true);

        $list = [];
        $cols = ['A','B','C','D','E'];
        self::paint($list, $cols, 1, 3, 1);
        self::put($list, 'A', 1, 'HỆ THỐNG QUẢN LÝ SỰ KIỆN SINH VIÊN | BÁO CÁO ĐIỂM DANH', 1);
        self::put($list, 'A', 3, $eventName, 2);
        self::put($list, 'A', 5, 'Sự kiện #' . $eventId . '  •  ' . $venue, 17);
        self::put($list, 'A', 6, 'Đăng ký: ' . $total . '   |   Đã điểm danh: ' . $attended
            . '   |   Chưa điểm danh: ' . $notAttended . '   |   Tỷ lệ: '
            . number_format($ratio * 100, 1, ',', '') . '%', 17);
        foreach (['A'=>'STT','B'=>'Họ tên','C'=>'Mã sinh viên','D'=>'Trạng thái','E'=>'Thời gian điểm danh'] as $col => $header) {
            self::put($list, $col, 9, $header, 10);
        }
        foreach ($registrations as $index => $registration) {
            $r = $index + 10;
            $even = ($index % 2) === 1;
            $baseStyle = $even ? 12 : 11;
            $checked = self::hasCheckedIn($registration);
            foreach ($cols as $col) {
                self::put($list, $col, $r, null, $baseStyle);
            }
            self::put($list, 'A', $r, $index + 1, $baseStyle, 'number');
            self::put($list, 'B', $r, (string) ($registration['ho_ten'] ?? 'Chưa cập nhật'), $baseStyle);
            self::put($list, 'C', $r, (string) ($registration['ma_sv'] ?? ''), $baseStyle); // bắt buộc là text
            self::put($list, 'D', $r, $checked ? 'Đã điểm danh' : 'Chưa điểm danh',
                $checked ? ($even ? 14 : 13) : $baseStyle);
            $date = $checked ? self::excelDate($registration['thoi_gian_diem_danh'] ?? null) : null;
            if ($date !== null) {
                self::put($list, 'E', $r, $date, $even ? 16 : 15, 'number');
            } else {
                self::put($list, 'E', $r, $checked ? '---' : '', $baseStyle);
            }
        }
        if (!$registrations) {
            self::put($list, 'A', 10, 'Chưa có sinh viên đăng ký sự kiện này.', 17);
        }
        $sheet2 = self::sheet($list, ['A'=>8,'B'=>34,'C'=>23,'D'=>24,'E'=>28],
            ['A1:E2','A3:E3','A5:E5','A6:E6', ...(!$registrations ? ['A10:E10'] : [])],
            9, $registrations ? ('A9:E' . (count($registrations) + 9)) : null, true);

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>VEVENT</dc:creator><cp:lastModifiedBy>VEVENT</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
                . '</Relationships>',
            'docProps/core.xml' => $core,
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
                . ' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
                . '<Application>VEVENT</Application></Properties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<bookViews><workbookView activeTab="0"/></bookViews><sheets>'
                . '<sheet name="Báo cáo" sheetId="1" r:id="rId1"/>'
                . '<sheet name="Danh sách" sheetId="2" r:id="rId2"/>'
                . '</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
                . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => self::styles(),
            'xl/worksheets/sheet1.xml' => $sheet1,
            'xl/worksheets/sheet2.xml' => $sheet2,
        ];
        self::storeZip($files, $path);
    }

    private static function hasCheckedIn(array $registration): bool
    {
        $status = trim((string) ($registration['trang_thai'] ?? ''));
        return in_array(strtolower($status), ['da_tham_gia','present'], true)
            || in_array($status, ['Đã tham gia','đã tham gia'], true);
    }

    private static function storeZip(array $files, string $path): void
    {
        // ZIP "store" (không nén) là chuẩn OOXML hợp lệ, không cần ext-zip hay Composer.
        $out = fopen($path, 'wb');
        if (!$out) {
            throw new RuntimeException('Không thể tạo file Excel tạm.');
        }
        $central = '';
        $offset = 0;
        foreach ($files as $name => $data) {
            $length = strlen($data);
            $nameLength = strlen($name);
            $crc = crc32($data);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc,
                $length, $length, $nameLength, 0) . $name;
            if (fwrite($out, $local) !== strlen($local) || fwrite($out, $data) !== $length) {
                fclose($out);
                @unlink($path);
                throw new RuntimeException('Không thể ghi dữ liệu Excel.');
            }
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0,
                $crc, $length, $length, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($local) + $length;
        }
        $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files),
            strlen($central), $offset, 0);
        fwrite($out, $central . $eocd);
        fclose($out);
    }
}
