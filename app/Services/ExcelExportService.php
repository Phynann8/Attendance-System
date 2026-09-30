<?php

namespace App\Services;

use ZipArchive;

class ExcelExportService
{
    /**
     * Generate a styled native XLSX file and return temporary file path.
     *
     * @param  string  $title  Sheet title
     * @param  array<string>  $headers  Header column names
     * @param  array<array<string|int|float|null>>  $rows  Data rows
     * @param  array<string, string|int>  $summary  Optional summary KPI metrics
     * @return string Path to generated temp .xlsx file
     */
    public function generateXlsx(string $title, array $headers, array $rows, array $summary = []): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive;
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // 1. [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->getContentTypesXml());

        // 2. _rels/.rels
        $zip->addFromString('_rels/.rels', $this->getRelsXml());

        // 3. xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRelsXml());

        // 4. xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', $this->getWorkbookXml($title));

        // 5. xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->getStylesXml());

        // 6. xl/worksheets/sheet1.xml
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->getSheetXml($title, $headers, $rows, $summary));

        $zip->close();

        return $tempFile;
    }

    private function getContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function getRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function getWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function getWorkbookXml(string $sheetName): string
    {
        $safeName = htmlspecialchars(substr($sheetName, 0, 31), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="'.$safeName.'" sheetId="1" r:id="rId1"/>'
            .'</sheets>'
            .'</workbook>';
    }

    private function getStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/><color rgb="FF1E293B"/></font>' // 0: Normal
            .'<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>' // 1: Header bold white
            .'<font><b/><sz val="14"/><name val="Calibri"/><color rgb="FF0F172A"/></font>' // 2: Title bold
            .'<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF0F172A"/></font>' // 3: Subtitle bold
            .'</fonts>'
            .'<fills count="7">'
            .'<fill><patternFill patternType="none"/></fill>' // 0: None
            .'<fill><patternFill patternType="gray125"/></fill>' // 1: Gray125
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0284C7"/></patternFill></fill>' // 2: Brand blue (header)
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>' // 3: Green soft (present)
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>' // 4: Amber soft (late)
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>' // 5: Red soft (absent)
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>' // 6: Zebra light
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/></border>' // 0: None
            .'<border>' // 1: Thin border
            .'<left style="thin"><color rgb="FFE2E8F0"/></left>'
            .'<right style="thin"><color rgb="FFE2E8F0"/></right>'
            .'<top style="thin"><color rgb="FFE2E8F0"/></top>'
            .'<bottom style="thin"><color rgb="FFE2E8F0"/></bottom>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            .'</cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' // 0: Normal bordered
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 1: Title
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 2: Header cell
            .'<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 3: Zebra light
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 4: Present badge
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 5: Late badge
            .'<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 6: Absent badge
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function getSheetXml(string $title, array $headers, array $rows, array $summary = []): string
    {
        $colCount = count($headers);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Column widths
        $xml .= '<cols>';
        for ($i = 1; $i <= $colCount; $i++) {
            $width = match ($i) {
                1 => 14, // Date
                2 => 16, // Class
                3 => 12, // Period
                4 => 18, // Subject
                5 => 24, // Student Name
                6 => 15, // Teacher Mark
                7 => 14, // Arrival Time
                8 => 14, // Minutes Late
                9 => 18, // Final Status
                10 => 20, // Finalized By
                default => 28, // Admin Note
            };
            $xml .= '<col min="'.$i.'" max="'.$i.'" width="'.$width.'" customWidth="1"/>';
        }
        $xml .= '</cols>';

        $xml .= '<sheetData>';

        $r = 1;

        // Title row
        $xml .= '<row r="'.$r.'" ht="26" customHeight="1">';
        $xml .= '<c r="A'.$r.'" s="1" t="inlineStr"><is><t>'.htmlspecialchars($title, ENT_QUOTES | ENT_XML1, 'UTF-8').'</t></is></c>';
        $xml .= '</row>';
        $r++;

        // Subtitle / metadata
        $xml .= '<row r="'.$r.'">';
        $metaText = 'Generated on '.date('Y-m-d H:i').' | Total Records: '.count($rows);
        $xml .= '<c r="A'.$r.'" t="inlineStr"><is><t>'.htmlspecialchars($metaText, ENT_QUOTES | ENT_XML1, 'UTF-8').'</t></is></c>';
        $xml .= '</row>';
        $r++;

        // Optional KPI summary row
        if (! empty($summary)) {
            $summaryText = 'Present: '.($summary['present'] ?? 0)
                .' | Late: '.($summary['late'] ?? 0)
                .' | Excused: '.($summary['excused'] ?? 0)
                .' | Absent: '.($summary['absent_without_permission'] ?? 0)
                .' | Permission: '.($summary['permission'] ?? 0);
            $xml .= '<row r="'.$r.'">';
            $xml .= '<c r="A'.$r.'" t="inlineStr"><is><t>'.htmlspecialchars($summaryText, ENT_QUOTES | ENT_XML1, 'UTF-8').'</t></is></c>';
            $xml .= '</row>';
            $r++;
        }

        // Blank space
        $r++;

        // Table header row
        $xml .= '<row r="'.$r.'" ht="22" customHeight="1">';
        foreach ($headers as $colIdx => $colName) {
            $colLetter = $this->getColumnLetter($colIdx + 1);
            $xml .= '<c r="'.$colLetter.$r.'" s="2" t="inlineStr"><is><t>'.htmlspecialchars($colName, ENT_QUOTES | ENT_XML1, 'UTF-8').'</t></is></c>';
        }
        $xml .= '</row>';
        $r++;

        // Data rows
        foreach ($rows as $rowIdx => $rowValues) {
            $isZebra = ($rowIdx % 2 === 1);
            $defaultStyle = $isZebra ? 3 : 0;

            $xml .= '<row r="'.$r.'">';
            foreach (array_values($rowValues) as $colIdx => $val) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $strVal = (string) ($val ?? '');

                // Status styling for status columns
                $style = $defaultStyle;
                $low = strtolower($strVal);
                if (in_array($low, ['present'])) {
                    $style = 4;
                } elseif (in_array($low, ['late'])) {
                    $style = 5;
                } elseif (in_array($low, ['absent', 'absent without permission', 'unexcused'])) {
                    $style = 6;
                }

                $safeVal = htmlspecialchars($strVal, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $xml .= '<c r="'.$colLetter.$r.'" s="'.$style.'" t="inlineStr"><is><t>'.$safeVal.'</t></is></c>';
            }
            $xml .= '</row>';
            $r++;
        }

        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    private function getColumnLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex > 0) {
            $mod = ($colIndex - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $colIndex = intval(($colIndex - $mod) / 26);
        }

        return $letter;
    }
}
