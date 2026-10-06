<?php
if (!defined('ABSPATH')) exit;

/** Preserve legacy EMIS data while adopting the shared OLAMA table convention. */
final class Olama_EMIS_Schema {
    public static function table_suffixes() {
        return array('students', 'schools', 'buildings', 'building_years', 'floors',
            'floor_years', 'rooms', 'room_years', 'classrooms', 'facility_rooms',
            'staff', 'staff_assignments', 'staff_qualifications', 'staff_workloads');
    }

    public static function migrate_table_names() {
        global $wpdb;
        $tables = $wpdb->get_col('SHOW TABLES');
        if (!is_array($tables) || $wpdb->last_error) return false;
        $renames = array();
        foreach (self::table_suffixes() as $suffix) {
            $old = $wpdb->prefix . 'emis_' . $suffix;
            $new = $wpdb->prefix . 'olama_emis_' . $suffix;
            if (!in_array($old, $tables, true)) continue;
            // Never merge or overwrite two independently populated tables.
            if (in_array($new, $tables, true)) return false;
            $renames[] = '`' . str_replace('`', '``', $old) . '` TO `' . str_replace('`', '``', $new) . '`';
        }
        // One statement preserves IDs, indexes, and relationships together.
        return !$renames || false !== $wpdb->query('RENAME TABLE ' . implode(', ', $renames));
    }
}
