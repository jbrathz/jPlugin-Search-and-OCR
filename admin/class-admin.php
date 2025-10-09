<?php
/**
 * Admin Class - ควบคุม admin pages ทั้งหมด
 */

if (!defined('ABSPATH')) exit;

class PDFS_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_' . JSEARCH_AJAX_CLEAR_CACHE, array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_jsearch_reset_settings', array($this, 'ajax_reset_settings'));

        // Handle export settings (must run before any HTML output)
        add_action('admin_init', array($this, 'handle_export_settings'), 5);

        // Auto-cleanup completed jobs (1 hour) when loading admin pages
        add_action('admin_init', array($this, 'auto_cleanup_jobs'));
    }

    public function add_menu() {
        add_menu_page(
            __('jSearch', 'jsearch'),
            __('jSearch', 'jsearch'),
            'manage_options',
            JSEARCH_MAIN_SLUG,
            array($this, 'dashboard_page'),
            'dashicons-search',
            30
        );

        add_submenu_page(
            JSEARCH_MAIN_SLUG,
            __('Dashboard', 'jsearch'),
            __('Dashboard', 'jsearch'),
            'manage_options',
            JSEARCH_MAIN_SLUG,
            array($this, 'dashboard_page')
        );

        add_submenu_page(
            JSEARCH_MAIN_SLUG,
            __('Manual OCR', 'jsearch'),
            __('Manual OCR', 'jsearch'),
            'manage_options',
            JSEARCH_OCR_SLUG,
            array($this, 'ocr_page')
        );

        add_submenu_page(
            JSEARCH_MAIN_SLUG,
            __('Manage Folders', 'jsearch'),
            __('Manage Folders', 'jsearch'),
            'manage_options',
            JSEARCH_FOLDERS_SLUG,
            array($this, 'folders_page')
        );

        add_submenu_page(
            JSEARCH_MAIN_SLUG,
            __('Settings', 'jsearch'),
            __('Settings', 'jsearch'),
            'manage_options',
            JSEARCH_SETTINGS_SLUG,
            array($this, 'settings_page')
        );

        add_submenu_page(
            JSEARCH_MAIN_SLUG,
            __('REST API Debug', 'jsearch'),
            __('REST API Debug', 'jsearch'),
            'manage_options',
            JSEARCH_DEBUG_SLUG,
            array($this, 'debug_page')
        );
    }

    public function dashboard_page() {
        require_once JSEARCH_PLUGIN_DIR . 'admin/dashboard.php';
    }

    public function ocr_page() {
        require_once JSEARCH_PLUGIN_DIR . 'admin/manual-ocr.php';
    }

    public function folders_page() {
        require_once JSEARCH_PLUGIN_DIR . 'admin/manage-folders.php';
    }

    public function settings_page() {
        require_once JSEARCH_PLUGIN_DIR . 'admin/settings.php';
    }

    public function debug_page() {
        require_once JSEARCH_PLUGIN_DIR . 'admin/debug.php';
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, JSEARCH_MAIN_SLUG) === false) {
            return;
        }

        // SweetAlert2
        wp_enqueue_style('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', array(), '11.0.0');
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js', array(), '11.0.0', true);

        // Admin assets
        wp_enqueue_style('jsearch-admin', JSEARCH_PLUGIN_URL . 'assets/css/admin.css', array('sweetalert2'), JSEARCH_VERSION);
        wp_enqueue_script('jsearch-admin', JSEARCH_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'sweetalert2'), JSEARCH_VERSION, true);

        // Folder OCR script (realtime processing - only on OCR page)
        if ($hook === 'jsearch_page_' . JSEARCH_OCR_SLUG) {
            wp_enqueue_script(
                'jsearch-folder-ocr',
                JSEARCH_PLUGIN_URL . 'admin/js/folder-ocr.js',
                array('jquery'),
                JSEARCH_VERSION,
                true
            );
        }

        wp_localize_script('jsearch-admin', 'jsearchAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(JSEARCH_NONCE_ACTION),
            'api_url' => rest_url(JSEARCH_REST_NAMESPACE),
            'restUrl' => rest_url(JSEARCH_REST_NAMESPACE . '/'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'i18n' => array(
                'reset_confirm' => __('Are you sure you want to reset all settings to default?', 'jsearch'),
                'delete_confirm' => __('Are you sure you want to delete this PDF?', 'jsearch'),
                'delete_title' => __('Delete PDF', 'jsearch'),
                'delete_text' => __('This will permanently delete "%s" from the database.', 'jsearch'),
                'delete_button' => __('Yes, delete it!', 'jsearch'),
                'cancel_button' => __('Cancel', 'jsearch'),
                'deleted_title' => __('Deleted!', 'jsearch'),
                'deleted_text' => __('PDF has been deleted successfully.', 'jsearch'),
                'error_title' => __('Error!', 'jsearch'),
                'error_text' => __('Failed to delete PDF. Please try again.', 'jsearch'),
                'delete_folder_title' => __('Delete Folder', 'jsearch'),
                'delete_folder_text' => __('Are you sure you want to delete folder "%s"?', 'jsearch'),
                'delete_folder_note' => __('Note: PDFs from this folder will NOT be deleted, only the folder reference.', 'jsearch'),
            ),
        ));
    }

    /**
     * AJAX: Clear Search Cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer(JSEARCH_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;

        // Delete all search cache transients
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_jsearch_query_%'
             OR option_name LIKE '_transient_timeout_jsearch_query_%'"
        );

        wp_send_json_success(array(
            'message' => sprintf(__('Successfully cleared %d cached search queries.', 'jsearch'), $deleted / 2)
        ));
    }

    /**
     * AJAX: Reset Settings to Default
     */
    public function ajax_reset_settings() {
        check_ajax_referer(JSEARCH_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        PDFS_Settings::reset_to_default();

        wp_send_json_success();
    }

    /**
     * Handle Export Settings (must run before any HTML output)
     */
    public function handle_export_settings() {
        // Check if this is an export request
        if (!isset($_POST['jsearch_export'])) {
            return;
        }

        // Verify nonce
        if (!check_admin_referer('jsearch_export_nonce')) {
            wp_die(__('Security check failed', 'jsearch'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'jsearch'));
        }

        // Clear any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Export settings
        $json = PDFS_Settings::export();

        // Send headers
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="jsearch-settings-' . date('Y-m-d') . '.json"');
        header('Content-Length: ' . strlen($json));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output JSON and exit
        echo $json;
        exit;
    }

    /**
     * Auto-cleanup completed jobs (เกิน 1 ชั่วโมง)
     * รันทุกครั้งที่โหลดหน้า admin (light check)
     */
    public function auto_cleanup_jobs() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // ใช้ transient เพื่อลด load (check ทุก 10 นาที)
        $last_cleanup = get_transient('jsearch_last_cleanup');
        if ($last_cleanup !== false) {
            return; // ทำ cleanup ไปแล้วใน 10 นาทีที่แล้ว
        }

        PDFS_Queue_Service::cleanup_completed_jobs();

        // เซ็ต transient ให้หมดอายุใน 10 นาที
        set_transient('jsearch_last_cleanup', time(), 10 * MINUTE_IN_SECONDS);
    }
}
