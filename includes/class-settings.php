<?php
/**
 * Settings Class
 *
 * จัดการ settings ที่ flexible สำหรับแจกจ่าย plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Settings {

    /**
     * Load settings with backward compatibility.
     *
     * @return array
     */
    protected static function load_settings() {
        $settings = get_option(JSEARCH_OPTION_KEY, array());

        if (!is_array($settings)) {
            $settings = array();
        }

        return $settings;
    }

    /**
     * Persist settings array.
     *
     * @param array $settings
     * @return bool
     */
    protected static function save_settings($settings) {
        return update_option(JSEARCH_OPTION_KEY, $settings);
    }

    /**
     * Get Setting
     *
     * @param string $key ชื่อ setting (เช่น 'api.url' หรือ 'search.results_per_page')
     * @param mixed $default ค่า default
     * @return mixed
     */
    public static function get($key, $default = null) {
        $settings = self::load_settings();

        // รองรับ dot notation (เช่น 'api.url')
        $keys = explode('.', $key);
        $value = $settings;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set Setting
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function set($key, $value) {
        $settings = self::load_settings();

        // รองรับ dot notation
        $keys = explode('.', $key);
        $current = &$settings;

        for ($i = 0; $i < count($keys) - 1; $i++) {
            $k = $keys[$i];
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = array();
            }
            $current = &$current[$k];
        }

        $current[end($keys)] = $value;

        return self::save_settings($settings);
    }

    /**
     * Get All Settings
     *
     * @return array
     */
    public static function get_all() {
        return self::load_settings();
    }

    /**
     * Update All Settings
     *
     * @param array $settings
     * @return bool
     */
    public static function update_all($settings) {
        if (!is_array($settings)) {
            $settings = array();
        }

        return self::save_settings($settings);
    }

    /**
     * Reset to Default
     *
     * @return bool
     */
    public static function reset_to_default() {
        delete_option(JSEARCH_OPTION_KEY);
        // Load activator สำหรับ default settings
        require_once JSEARCH_PLUGIN_DIR . 'includes/class-activator.php';

        // เรียกใช้ method ภายใน (ต้องทำให้เป็น public หรือสร้างใหม่)
        $default = array(
            'api' => array('url' => 'http://localhost:8000', 'key' => '', 'timeout' => 30),
            'gdrive' => array('folder_id' => '', 'ocr_language' => 'tha+eng'),
            'search' => array(
                'results_per_page' => 10,
                'popular_keywords' => array(),
                'show_title' => true,
                'title_text' => 'Search PDF Documents',
                'placeholder_text' => 'Type keywords to search...',
                'open_new_tab' => true,
                'cache_duration' => 60,
                'exclude_pages' => array(),
            ),
            'display' => array(
                'show_thumbnail' => true,
                'thumbnail_size' => 'medium',
                'snippet_length' => 200,
                'highlight_color' => '#FFFF00',
                'date_format' => 'Y-m-d H:i:s',
            ),
            'automation' => array(
                'auto_ocr' => false,
            ),
            'advanced' => array(
                'debug_mode' => false,
                'public_api' => true,
                'log_retention_days' => 30,
            ),
        );

        return add_option(JSEARCH_OPTION_KEY, $default);
    }

    /**
     * Export Settings (JSON)
     *
     * @return string JSON
     */
    public static function export() {
        $settings = self::get_all();

        // ลบ API key ออก (security)
        if (isset($settings['api']['key'])) {
            $settings['api']['key'] = '*** REMOVED ***';
        }

        return json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Import Settings (JSON)
     *
     * @param string $json
     * @return bool|WP_Error
     */
    public static function import($json) {
        $settings = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON format', 'jsearch'));
        }

        // Validate settings structure
        if (!is_array($settings)) {
            return new WP_Error('invalid_structure', __('Invalid settings structure', 'jsearch'));
        }

        // Merge กับ existing (เพื่อไม่ให้ข้อมูลสูญหาย)
        $existing = self::get_all();
        $merged = array_replace_recursive($existing, $settings);

        return self::update_all($merged);
    }

    /**
     * Encrypt API Key
     *
     * Uses AES-256-CBC encryption for secure storage
     *
     * @param string $key Plain text API key
     * @return string Encrypted API key (base64 encoded)
     */
    public static function encrypt_api_key($key) {
        if (empty($key)) {
            return '';
        }

        // Check if OpenSSL extension is available
        if (!function_exists('openssl_encrypt')) {
            // Fallback to base64 (less secure, but better than nothing)
            return base64_encode($key);
        }

        // Use WordPress AUTH_KEY as encryption key
        if (!defined('AUTH_KEY') || empty(AUTH_KEY)) {
            return base64_encode($key);
        }

        $cipher = 'AES-256-CBC';
        $encryption_key = hash('sha256', AUTH_KEY);

        // Generate a random IV (Initialization Vector)
        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);

        // Encrypt the API key
        $encrypted = openssl_encrypt($key, $cipher, $encryption_key, 0, $iv);

        if ($encrypted === false) {
            // Encryption failed, fallback to base64
            return base64_encode($key);
        }

        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt API Key
     *
     * Supports backward compatibility with old base64-only encryption
     *
     * @param string $encrypted Encrypted API key
     * @return string Decrypted API key
     */
    public static function decrypt_api_key($encrypted) {
        if (empty($encrypted)) {
            return '';
        }

        $decoded = base64_decode($encrypted, true);

        if ($decoded === false) {
            // Not valid base64, return as-is
            return $encrypted;
        }

        // Check if OpenSSL extension is available
        if (!function_exists('openssl_decrypt')) {
            // No OpenSSL, assume old format (base64 only)
            return self::decrypt_old_format($decoded);
        }

        // Check if WordPress AUTH_KEY is defined
        if (!defined('AUTH_KEY') || empty(AUTH_KEY)) {
            return self::decrypt_old_format($decoded);
        }

        // Check if this is new format (contains '::' separator)
        if (strpos($decoded, '::') === false) {
            // Old format, decrypt using old method
            return self::decrypt_old_format($decoded);
        }

        // New format: IV::encrypted_data
        $parts = explode('::', $decoded, 2);

        if (count($parts) !== 2) {
            // Invalid format, fallback
            return self::decrypt_old_format($decoded);
        }

        list($iv, $encrypted_data) = $parts;

        $cipher = 'AES-256-CBC';
        $encryption_key = hash('sha256', AUTH_KEY);

        // Decrypt the API key
        $decrypted = openssl_decrypt($encrypted_data, $cipher, $encryption_key, 0, $iv);

        if ($decrypted === false) {
            // Decryption failed, fallback
            return self::decrypt_old_format($decoded);
        }

        return $decrypted;
    }

    /**
     * Decrypt old format API key (backward compatibility)
     *
     * @param string $decoded Base64 decoded string
     * @return string Decrypted API key
     */
    private static function decrypt_old_format($decoded) {
        // Old format: key|AUTH_KEY or just key
        if (!defined('AUTH_KEY') || empty(AUTH_KEY)) {
            return $decoded;
        }

        $parts = explode('|', $decoded);
        return $parts[0] ?? $decoded;
    }
}
