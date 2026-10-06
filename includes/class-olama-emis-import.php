<?php
if (!defined('ABSPATH')) exit;

final class Olama_EMIS_Import {
    /** Import a UTF-8 CSV or a single-sheet XLSX with header keys from the template. */
    public static function rows($path, $filename, $fields = null, $required = 'student_uid', $dates = array('birth_date')) {
        if (!is_readable($path) || filesize($path) > 5 * 1024 * 1024) return new WP_Error('emis_file', 'File must be readable and under 5 MB.');
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($fields === null) $fields = Olama_EMIS_Students::fields();
        if ($ext === 'csv') return self::csv($path, $fields, $required, $dates);
        if ($ext === 'xlsx') return self::xlsx($path, $fields, $required, $dates);
        return new WP_Error('emis_type', 'Upload a CSV or XLSX file.');
    }

    private static function csv($path, $fields, $required, $dates) {
        $handle = fopen($path, 'rb');
        if (!$handle) return new WP_Error('emis_csv', 'Unable to read CSV.');
        $rows = array();
        while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($rows) >= 2001) { fclose($handle); return new WP_Error('emis_limit', 'Maximum 2000 rows.'); }
            if (isset($line[0])) $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', $line[0]);
            $rows[] = $line;
        }
        fclose($handle);
        return self::associate($rows, $fields, $required, $dates);
    }

    private static function xlsx($path, $fields, $required, $dates) {
        if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) return new WP_Error('emis_xlsx_runtime', 'XLSX support needs ZipArchive and SimpleXML.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return new WP_Error('emis_xlsx', 'Unable to open XLSX.');
        $shared = array();
        $strings = $zip->getFromName('xl/sharedStrings.xml');
        if ($strings !== false) {
            if (strlen($strings) > 8 * 1024 * 1024) { $zip->close(); return new WP_Error('emis_limit', 'Shared strings are too large.'); }
            $xml = simplexml_load_string($strings, 'SimpleXMLElement', LIBXML_NONET);
            if (!$xml) { $zip->close(); return new WP_Error('emis_xlsx', 'Invalid shared strings.'); }
            foreach ($xml->si as $item) {
                $item->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $parts = $item->xpath('.//main:t');
                $value = '';
                foreach ((array) $parts as $part) $value .= (string) $part;
                $shared[] = $value;
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false || strlen($sheet) > 12 * 1024 * 1024) return new WP_Error('emis_xlsx', 'First worksheet is missing or too large.');
        $xml = simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET);
        if (!$xml) return new WP_Error('emis_xlsx', 'Invalid worksheet XML.');
        $rows = array();
        foreach ($xml->sheetData->row as $row) {
            if (count($rows) >= 2001) return new WP_Error('emis_limit', 'Maximum 2000 rows.');
            $cells = array();
            foreach ($row->c as $cell) {
                preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
                if (!$match) continue;
                $index = 0;
                foreach (str_split($match[0]) as $letter) $index = $index * 26 + ord($letter) - 64;
                if ($index > 100) continue;
                $type = (string) $cell['t'];
                $value = $type === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v;
                if ($type === 's') $value = isset($shared[(int) $value]) ? $shared[(int) $value] : '';
                $cells[$index - 1] = $value;
            }
            if ($cells) {
                $last = max(array_keys($cells));
                $rows[] = array_replace(array_fill(0, $last + 1, ''), $cells);
            }
        }
        return self::associate($rows, $fields, $required, $dates);
    }

    private static function associate(array $rows, $allowed, $required, $dates) {
        if (!$rows) return new WP_Error('emis_empty', 'Import file is empty.');
        $headers = array_map('trim', array_shift($rows));
        $lookup = array_flip($allowed);
        $keys = array();
        foreach ($headers as $heading) $keys[] = isset($allowed[$heading]) ? $heading : (isset($lookup[$heading]) ? $lookup[$heading] : '');
        if (!in_array($required, $keys, true)) return new WP_Error('emis_header', $required . ' column is required. Use the downloadable template.');
        $known = array_filter($keys);
        if (count($known) !== count(array_unique($known))) return new WP_Error('emis_header', 'Duplicate mapped column headings.');
        $out = array();
        foreach ($rows as $line) {
            $entry = array();
            foreach ($keys as $i => $key) if ($key) $entry[$key] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            foreach ($dates as $date) if (isset($entry[$date]) && is_numeric($entry[$date]) && (float) $entry[$date] > 0 && (float) $entry[$date] < 100000) {
                $entry[$date] = gmdate('Y-m-d', (int) round(((float) $entry[$date] - 25569) * DAY_IN_SECONDS));
            }
            if (implode('', $entry) !== '') $out[] = $entry;
        }
        return $out;
    }
}
