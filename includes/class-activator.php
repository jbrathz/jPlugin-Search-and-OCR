<?php
/**
 * Plugin Activator
 *
 * สร้างตารางและ default settings เมื่อ activate plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Activator {

    /**
     * Activate Plugin
     */
    public static function activate() {
        self::create_tables();
        self::set_default_settings();
        self::create_upload_directory();

        // บันทึกเวอร์ชัน
        update_option('jsearch_version', JSEARCH_VERSION);
        update_option('jsearch_activated_time', current_time('mysql'));

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * สร้างตารางฐานข้อมูล
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table 1: PDF Index
        $table_pdf = $wpdb->prefix . 'jsearch_pdf_index';
        $sql_pdf = "CREATE TABLE IF NOT EXISTS `{$table_pdf}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `file_id` varchar(255) NOT NULL COMMENT 'Google Drive file ID',
            `folder_id` varchar(255) DEFAULT NULL COMMENT 'Google Drive folder ID',
            `folder_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อ folder',
            `post_id` bigint(20) DEFAULT NULL COMMENT 'WordPress post ID',
            `post_title` varchar(255) DEFAULT NULL COMMENT 'ชื่อโพสต์',
            `post_url` text DEFAULT NULL COMMENT 'URL ของโพสต์',
            `pdf_title` varchar(255) NOT NULL COMMENT 'ชื่อไฟล์ PDF',
            `pdf_url` text NOT NULL COMMENT 'URL ของ PDF',
            `content` longtext NOT NULL COMMENT 'เนื้อหาที่แปลงจาก PDF',
            `ocr_method` varchar(50) DEFAULT NULL COMMENT 'pymupdf or tesseract',
            `char_count` int DEFAULT 0 COMMENT 'จำนวนตัวอักษร',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_file_id` (`file_id`),
            KEY `idx_folder_id` (`folder_id`),
            KEY `idx_post_id` (`post_id`),
            KEY `idx_pdf_url` (`pdf_url`(255)),
            KEY `idx_last_updated` (`last_updated`),
            FULLTEXT KEY `ft_content` (`content`)
        ) ENGINE=InnoDB {$charset_collate} COMMENT='PDF index table';";

        dbDelta($sql_pdf);

        // Table 2: Folders
        $table_folders = $wpdb->prefix . 'jsearch_folders';
        $sql_folders = "CREATE TABLE IF NOT EXISTS `{$table_folders}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `folder_id` varchar(255) NOT NULL COMMENT 'Google Drive folder ID',
            `folder_name` varchar(255) NOT NULL COMMENT 'ชื่อ folder',
            `is_default` tinyint(1) DEFAULT 0 COMMENT 'เป็น default folder หรือไม่',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_folder_id` (`folder_id`)
        ) ENGINE=InnoDB {$charset_collate} COMMENT='Google Drive folders';";

        dbDelta($sql_folders);

        // Table 3: OCR Jobs (สำหรับ background processing)
        $table_jobs = $wpdb->prefix . 'jsearch_jobs';
        $sql_jobs = "CREATE TABLE IF NOT EXISTS `{$table_jobs}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_id` varchar(255) NOT NULL COMMENT 'Unique job identifier',
            `folder_id` varchar(255) NOT NULL COMMENT 'Google Drive folder ID',
            `total_files` int UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนไฟล์ทั้งหมด',
            `processed_files` int UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนไฟล์ที่ทำเสร็จแล้ว',
            `failed_files` int UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนไฟล์ที่ล้มเหลว',
            `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, failed, cancelled',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_job_id` (`job_id`),
            KEY `idx_folder_id` (`folder_id`),
            KEY `idx_status` (`status`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB {$charset_collate} COMMENT='OCR background jobs';";

        dbDelta($sql_jobs);

        // Table 4: OCR Job Batches (แต่ละ batch มี 10 ไฟล์)
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';
        $sql_batches = "CREATE TABLE IF NOT EXISTS `{$table_batches}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_id` varchar(255) NOT NULL COMMENT 'Job identifier',
            `batch_number` int UNSIGNED NOT NULL COMMENT 'Batch number (1, 2, 3...)',
            `file_ids` longtext NOT NULL COMMENT 'JSON array of Google Drive file IDs',
            `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, failed',
            `error` text DEFAULT NULL COMMENT 'Error message if failed',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `processed_at` datetime DEFAULT NULL COMMENT 'เวลาที่ process เสร็จ',
            PRIMARY KEY (`id`),
            KEY `idx_job_id` (`job_id`),
            KEY `idx_status` (`status`),
            KEY `idx_batch_number` (`batch_number`)
        ) ENGINE=InnoDB {$charset_collate} COMMENT='OCR job batches';";

        dbDelta($sql_batches);
    }

    /**
     * ตั้งค่าเริ่มต้น (Flexible Settings)
     */
    private static function set_default_settings() {
        $default_settings = array(
            // API Configuration
            'api' => array(
                'url' => 'http://localhost:8000',
                'key' => '',
                'timeout' => 30,
            ),

            // Google Drive
            'gdrive' => array(
                'ocr_language' => 'tha+eng',
            ),

            // Search Settings
            'search' => array(
                'results_per_page' => 10,
                'popular_keywords' => array(),
                'autocomplete' => true,
                'show_title' => true,
                'title_text' => 'Search PDF Documents',
                'placeholder_text' => 'Type keywords to search...',
                'open_new_tab' => true,
                'cache_duration' => 60, // minutes
                'exclude_pages' => array(),
                'post_types' => array('post', 'page'),
            ),

            // Display Settings
            'display' => array(
                'show_thumbnail' => true,
                'thumbnail_size' => 'medium',
                'snippet_length' => 200,
                'highlight_color' => '#FFFF00',
            ),

            // Automation
            'automation' => array(
                'auto_ocr' => false, // ปิดตอนแรก เพื่อความปลอดภัย
            ),

            // Advanced
            'advanced' => array(
                'debug_mode' => false,
                'public_api' => true,
                'rate_limit' => 100,
                'log_retention_days' => 30,
            ),
        );

        // เช็คว่ามี settings อยู่แล้วหรือไม่
        if (!get_option(JSEARCH_OPTION_KEY)) {
            add_option(JSEARCH_OPTION_KEY, $default_settings);
        }
    }

    /**
     * สร้างโฟลเดอร์สำหรับอัปโหลด
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $jsearch_dir = $upload_dir['basedir'] . '/jsearch';

        if (!file_exists($jsearch_dir)) {
            wp_mkdir_p($jsearch_dir);

            // สร้าง .htaccess เพื่อป้องกัน
            $htaccess_content = "Options -Indexes\n<Files *.log>\nOrder allow,deny\nDeny from all\n</Files>";
            file_put_contents($jsearch_dir . '/.htaccess', $htaccess_content);

            // สร้าง index.php เปล่าๆ
            file_put_contents($jsearch_dir . '/index.php', '<?php // Silence is golden');
        }
    }
}
