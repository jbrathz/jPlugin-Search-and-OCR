<?php
/**
 * Logger Class
 *
 * Centralized logging for PDF Search plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Logger {

    /**
     * Log Levels
     */
    const ERROR   = 'ERROR';
    const WARNING = 'WARNING';
    const INFO    = 'INFO';
    const DEBUG   = 'DEBUG';

    /**
     * Log Error (always logged regardless of debug mode)
     *
     * Critical errors that need immediate attention
     *
     * @param string $message
     * @param array $context
     */
    public static function error($message, $context = array()) {
        self::log(self::ERROR, $message, $context, true);
    }

    /**
     * Log Warning (only when debug mode is enabled)
     *
     * Non-critical issues that should be reviewed
     *
     * @param string $message
     * @param array $context
     */
    public static function warning($message, $context = array()) {
        if (self::is_debug_enabled()) {
            self::log(self::WARNING, $message, $context, false);
        }
    }

    /**
     * Log Info (only when debug mode is enabled)
     *
     * General informational messages
     *
     * @param string $message
     * @param array $context
     */
    public static function info($message, $context = array()) {
        if (self::is_debug_enabled()) {
            self::log(self::INFO, $message, $context, false);
        }
    }

    /**
     * Log Debug (only when debug mode is enabled)
     *
     * Detailed debugging information
     *
     * @param string $message
     * @param array $context
     */
    public static function debug($message, $context = array()) {
        if (self::is_debug_enabled()) {
            self::log(self::DEBUG, $message, $context, false);
        }
    }

    /**
     * Write log entry
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @param bool $force Always log regardless of debug mode (for errors)
     */
    private static function log($level, $message, $context = array(), $force = false) {
        // Format: [PDFSEARCH ERROR] Message | {"key":"value"}
        $log_message = sprintf(
            '[PDFSEARCH %s] %s',
            $level,
            $message
        );

        if (!empty($context)) {
            $log_message .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Save to custom log file
        self::write_to_file($level, $log_message, $force);
    }

    /**
     * Write to custom log file
     *
     * @param string $level
     * @param string $message
     * @param bool $force Always log regardless of debug mode (for errors)
     */
    private static function write_to_file($level, $message, $force = false) {
        // Skip logging if debug mode is disabled and not forced (errors are always logged)
        if (!$force && !self::is_debug_enabled()) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/jsearch';
        $log_file = $log_dir . '/jsearch.log';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf("[%s] %s\n", $timestamp, $message);

        // Append to log file
        file_put_contents($log_file, $log_entry, FILE_APPEND);

        // Rotate log if too large (> 10MB)
        if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) {
            self::rotate_log($log_file);
        }

        // Clean up old logs based on retention setting
        $retention_days = PDFS_Settings::get('advanced.log_retention_days', 30);
        if ($retention_days > 0) {
            self::cleanup_old_logs($log_dir, $retention_days);
        }
    }

    /**
     * Rotate log file
     *
     * @param string $log_file
     */
    private static function rotate_log($log_file) {
        $backup = $log_file . '.' . date('YmdHis');
        rename($log_file, $backup);
    }

    /**
     * Clean up old log files
     *
     * @param string $log_dir
     * @param int $retention_days
     */
    private static function cleanup_old_logs($log_dir, $retention_days) {
        $files = glob($log_dir . '/jsearch.log.*');

        foreach ($files as $file) {
            if (filemtime($file) < strtotime("-{$retention_days} days")) {
                unlink($file);
            }
        }
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    private static function is_debug_enabled() {
        return PDFS_Settings::get('advanced.debug_mode', false);
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/jsearch';
        $files = glob($log_dir . '/jsearch.log*');

        foreach ($files as $file) {
            unlink($file);
        }
    }
}
