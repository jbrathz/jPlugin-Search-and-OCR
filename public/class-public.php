<?php
/**
 * Public Class - Frontend Controller
 */

if (!defined('ABSPATH')) exit;

class PDFS_Public {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('jsearch', array($this, 'search_shortcode'));
    }

    /**
     * Enqueue Frontend Assets
     */
    public function enqueue_assets() {
        // Always enqueue styles and scripts when on singular posts/pages
        // WordPress will handle loading only when needed
        if (!is_singular()) {
            return;
        }

        // Enqueue dashicons (required for icons)
        wp_enqueue_style('dashicons');

        wp_enqueue_style('jsearch-public', JSEARCH_PLUGIN_URL . 'assets/css/public.css', array('dashicons'), JSEARCH_VERSION);
        wp_enqueue_script('jsearch-search', JSEARCH_PLUGIN_URL . 'assets/js/search.js', array('jquery'), JSEARCH_VERSION, true);

        wp_localize_script('jsearch-search', 'jsearch', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => rest_url('jsearch/v1'),
            'nonce' => wp_create_nonce('jsearch_nonce'),
            'settings' => array(
                'results_per_page' => PDFS_Settings::get('search.results_per_page', 10),
                'highlight_color' => PDFS_Settings::get('display.highlight_color', '#ffff00'),
                'open_new_tab' => PDFS_Settings::get('search.open_new_tab', true),
            ),
            'i18n' => array(
                'searching' => __('Searching...', 'jsearch'),
                'no_results' => __('No results found.', 'jsearch'),
                'error' => __('An error occurred. Please try again.', 'jsearch'),
            ),
        ));
    }

    /**
     * Search Shortcode
     *
     * @param array $atts
     * @return string
     */
    public function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => PDFS_Settings::get('search.results_per_page', 10),
            'show_popular' => 'yes',
            'show_thumbnail' => PDFS_Settings::get('display.show_thumbnail', true) ? 'yes' : 'no',
        ), $atts);

        ob_start();
        require JSEARCH_PLUGIN_DIR . 'public/shortcode.php';
        return ob_get_clean();
    }
}
