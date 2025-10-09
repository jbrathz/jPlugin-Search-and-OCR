<?php
/**
 * Plugin Name: jSearch – Smart Search for WordPress Content & PDFs
 * Plugin URI: https://jirathsoft.com
 * Description: Smart full-text search for WordPress content and PDF files with OCR technology
 * Version: 1.0.0
 * Author: JIRATH BURAPARATH
 * Author URI: https://dev.jirath.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jsearch
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('JSEARCH_VERSION', '1.0.0');
define('JSEARCH_PLUGIN_FILE', __FILE__);
define('JSEARCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JSEARCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JSEARCH_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('JSEARCH_OPTION_KEY', 'jsearch_settings');
define('JSEARCH_MAIN_SLUG', 'jsearch');
define('JSEARCH_OCR_SLUG', 'jsearch-ocr');
define('JSEARCH_FOLDERS_SLUG', 'jsearch-folders');
define('JSEARCH_SETTINGS_SLUG', 'jsearch-settings');
define('JSEARCH_DEBUG_SLUG', 'jsearch-debug');
define('JSEARCH_AJAX_CLEAR_CACHE', 'jsearch_clear_cache');
define('JSEARCH_NONCE_ACTION', 'jsearch_nonce');
define('JSEARCH_REST_NAMESPACE', 'jsearch/v1');

/**
 * Plugin Activation
 */
function jsearch_activate() {
    require_once JSEARCH_PLUGIN_DIR . 'includes/class-activator.php';
    PDFS_Activator::activate();
}
register_activation_hook(__FILE__, 'jsearch_activate');

/**
 * Plugin Deactivation
 */
function jsearch_deactivate() {
    require_once JSEARCH_PLUGIN_DIR . 'includes/class-deactivator.php';
    PDFS_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'jsearch_deactivate');

/**
 * Main Plugin Class
 */
class PDF_Search {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load Dependencies
     */
    private function load_dependencies() {
        // Core
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-settings.php';
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-logger.php';
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-database.php';
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-folders.php';
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-helper.php';
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-queue-service.php';
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-ocr-service.php';
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-hooks.php';

        // Admin
        if (is_admin()) {
            require_once JSEARCH_PLUGIN_DIR . 'admin/class-admin.php';
        }

        // Public
        require_once JSEARCH_PLUGIN_DIR . 'public/class-public.php';
    }

    /**
     * Initialize Hooks
     */
    private function init_hooks() {
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Initialize REST API early (before rest_api_init fires)
        PDFS_REST_API::get_instance();

        // Initialize components
        add_action('init', array($this, 'init_components'));
    }

    /**
     * Load Text Domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'jsearch',
            false,
            dirname(JSEARCH_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize Components
     */
    public function init_components() {
        // Admin
        if (is_admin()) {
            PDFS_Admin::get_instance();
        }

        // Public
        PDFS_Public::get_instance();

        // Hooks (save_post, etc.)
        PDFS_Hooks::get_instance();
    }
}

/**
 * Initialize Plugin
 */
function jsearch_init() {
    return PDF_Search::get_instance();
}
add_action('plugins_loaded', 'jsearch_init');
