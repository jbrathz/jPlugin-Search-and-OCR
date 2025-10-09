<?php
/**
 * Plugin Deactivator
 *
 * ทำความสะอาดเมื่อ deactivate (ไม่ลบตาราง)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Deactivator {

    /**
     * Deactivate Plugin
     */
    public static function deactivate() {
        // ลบ transients/cache
        self::clear_cache();

        // Flush rewrite rules
        flush_rewrite_rules();

        // หมายเหตุ: ไม่ลบตาราง wp_jsearch_* เพื่อเก็บข้อมูล OCR ไว้
    }

    /**
     * ลบ cache ทั้งหมด
     */
    private static function clear_cache() {
        global $wpdb;

        // ลบ transients ที่เกี่ยวข้อง (รวม lock transient)
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_jsearch_%'
             OR option_name LIKE '_transient_timeout_jsearch_%'"
        );
    }
}
