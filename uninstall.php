<?php
/**
 * Uninstall Script for jSearch Plugin
 *
 * This file is called when the plugin is uninstalled via WordPress admin.
 * It cleans up plugin data (logs, options) but preserves database tables by default.
 *
 * @package jSearch
 * @version 1.0.0
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin log files and uploads directory
 */
function jsearch_delete_log_files() {
    $upload_dir = wp_upload_dir();
    $jsearch_dir = $upload_dir['basedir'] . '/jsearch';

    if (is_dir($jsearch_dir)) {
        // Delete all files in directory
        $files = glob($jsearch_dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        // Remove directory
        @rmdir($jsearch_dir);
    }
}

/**
 * Delete plugin options from wp_options table
 */
function jsearch_delete_options() {
    delete_option('jsearch_settings');
    delete_option('jsearch_version');

    // Delete transients (cached search queries)
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_jsearch_%'
         OR option_name LIKE '_transient_timeout_jsearch_%'"
    );
}

/**
 * Delete plugin database tables
 *
 * COMMENTED OUT BY DEFAULT - Uncomment to enable database cleanup
 * This preserves OCR data for reinstallation or migration
 */
function jsearch_delete_database_tables() {
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'jsearch_pdf_index',
        $wpdb->prefix . 'jsearch_jobs',
        $wpdb->prefix . 'jsearch_job_batches',
        $wpdb->prefix . 'jsearch_folders',
    );

    // UNCOMMENT THE LINES BELOW TO DELETE DATABASE TABLES
    /*
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }
    */
}

// Execute cleanup
jsearch_delete_log_files();
jsearch_delete_options();

// Database cleanup is disabled by default
// Uncomment the line below to enable database deletion
// jsearch_delete_database_tables();
