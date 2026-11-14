<?php

declare(strict_types=1);

/** Xuất danh sách điểm danh thành một sheet Excel đơn giản, không cần thư viện ngoài. */
final class VeventAttendanceXlsx
{
    private static function xml($value): string
    {
        $text = (string) ($value ?? '');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '';
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function textCell(string $ref, $value, int $style = 0): string
    {
        // Lưu chuỗi theo inlineStr, tránh Excel hiểu MSSV là số hoặc công thức.
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
            . self::xml($value) . '</t></is></c>';
    }

    private static function numberCell(string $ref, int $value, int $style = 0): string
    {
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
    }

    private static function hasCheckedIn(array $registration): bool
    {
        $status = trim((string) ($registration['trang_thai'] ?? ''));
        return in_array(strtolower($status), ['da_tham_gia', 'present'], true)
            || in_array($status, ['Đã tham gia', 'đã tham gia'], true);
    }

    private static function displayDate($value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        return $date !== false && $date->format('Y-m-d H:i:s') === $value
            ? $date->format('d/m/Y H:i:s') : $value;
    }

    /**
     * Giữ nguyên chữ ký hàm để các trang PHP đang gọi create() không phải sửa.
     * Chỉ dùng $registrations để xuất bảng; các tham số khác giữ để tương thích.
     */
    public static function create(array $event, array $registrations, int $eventId,
                                  int $total, int $attended, int $notAttended,
                                  float $ratio, string $path): void
    {
        $rows = [];
        $headers = ['STT', 'Mã sinh viên', 'Họ và tên', 'Trạng thái', 'Thời gian điểm danh'];
        $columns = ['A', 'B', 'C', 'D', 'E'];
        $headerCells = '';
        foreach ($headers as $index => $header) {
            $headerCells .= self::textCell($columns[$index] . '1', $header, 1);
        }
        $rows[] = '<row r="1" ht="26" customHeight="1">' . $headerCells . '</row>';

        $rowNumber = 2;
        foreach ($registrations as $registration) {
            $checked = self::hasCheckedIn($registration);
            $values = [
                (string) ($registration['ma_sv'] ?? ''),
                (string) ($registration['ho_ten'] ?? 'Chưa cập nhật'),
                $checked ? 'Đã điểm danh' : 'Chưa điểm danh',
                $checked ? self::displayDate($registration['thoi_gian_diem_danh'] ?? null) : '',
            ];
            $cells = self::numberCell('A' . $rowNumber, $rowNumber - 1, 2);
            foreach ($values as $index => $value) {
                $cells .= self::textCell($columns[$index + 1] . $rowNumber, $value, 2);
            }
            $rows[] = '<row r="' . $rowNumber . '" ht="22" customHeight="1">' . $cells . '</row>';
            $rowNumber++;
        }
        $lastRow = max(1, $rowNumber - 1);
        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:E' . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="20"/><cols>'
            . '<col min="1" max="1" width="8" customWidth="1"/>'
            . '<col min="2" max="2" width="22" customWidth="1"/>'
            . '<col min="3" max="3" width="32" customWidth="1"/>'
            . '<col min="4" max="4" width="22" customWidth="1"/>'
            . '<col min="5" max="5" width="25" customWidth="1"/>'
            . '</cols><sheetData>' . implode('', $rows) . '</sheetData>'
            . '<autoFilter ref="A1:E' . $lastRow . '"/>'
            . '</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                . '</Relationships>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
                . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
                . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . '<dc:creator>SEM</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
                . '</cp:coreProperties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Danh sách sinh viên" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $worksheet,
        ];
        self::storeZip($files, $path);
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
