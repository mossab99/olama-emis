<?php
if (!defined('ABSPATH')) exit;

/** Portable text-cell XLSX exports preserve identifiers and avoid formula execution. */
final class Olama_EMIS_Staff_Export {
    private static function xml($value) { return htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8'); }
    public static function xlsx($headers,$rows) {
        if (!class_exists('ZipArchive')) return new WP_Error('emis_staff_zip','يلزم ZipArchive لتصدير Excel.');
        $path=wp_tempnam('emis-staff.xlsx'); $zip=new ZipArchive();
        if (!$path || $zip->open($path,ZipArchive::OVERWRITE)!==true) { if ($path) unlink($path); return new WP_Error('emis_staff_export','تعذر إنشاء ملف Excel.'); }
        $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="'.count($headers).'" width="24" customWidth="1" style="0"/></cols><sheetData>';
        foreach (array_merge(array($headers),$rows) as $i=>$row) {
            $sheet.='<row r="'.($i+1).'">';
            foreach (array_values($row) as $j=>$value) {
                $n=$j+1; $letters=''; while ($n>0) { $n--; $letters=chr(65+$n%26).$letters; $n=intdiv($n,26); }
                $sheet.='<c r="'.$letters.($i+1).'" t="inlineStr"><is><t xml:space="preserve">'.self::xml($value).'</t></is></c>';
            }
            $sheet.='</row>';
        }
        $sheet.='</sheetData></worksheet>';
        $files=array(
            '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="الموظفون" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Arial"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml'=>$sheet
        );
        foreach ($files as $name=>$content) if (!$zip->addFromString($name,$content)) { $zip->close(); unlink($path); return new WP_Error('emis_staff_export','تعذر كتابة Excel.'); }
        if (!$zip->close()) { unlink($path); return new WP_Error('emis_staff_export','تعذر إتمام Excel.'); }
        return $path;
    }
    public static function csv_value($value) {
        return preg_match('/^[\s\x{FEFF}]*[=+@-]/u',(string)$value) ? "'".$value : $value;
    }
}
